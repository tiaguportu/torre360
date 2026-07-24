<?php

namespace App\Services;

use App\Enums\Sexo;
use App\Models\Cidade;
use App\Models\Pais;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GovCpfService
{
    protected string $clientId;

    protected string $clientSecret;

    protected string $userCpf;

    protected string $environment;

    protected string $baseUrlToken;

    protected string $baseUrlConsulta;

    public function __construct()
    {
        $config = config('services.gov_cpf');

        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->userCpf = preg_replace('/[^0-9]/', '', $config['user_cpf'] ?? '');
        $this->environment = strtolower($config['environment'] ?? 'homologacao');

        if ($this->environment === 'producao') {
            $this->baseUrlToken = 'https://apigateway.conectagov.estaleiro.serpro.gov.br/oauth2/jwt-token';
            $this->baseUrlConsulta = 'https://apigateway.conectagov.estaleiro.serpro.gov.br/api-cpf-light/v2/consulta/cpf';
        } else {
            $this->baseUrlToken = 'https://h-apigateway.conectagov.np.estaleiro.serpro.gov.br/oauth2/jwt-token';
            $this->baseUrlConsulta = 'https://h-apigateway.conectagov.np.estaleiro.serpro.gov.br/api-cpf-light/v2/consulta/cpf';
        }
    }

    /**
     * Obtém um token de acesso OAuth2 válido (geralmente mantido em cache).
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'gov_cpf_access_token_'.$this->environment;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Credenciais da API do Governo (GOV_CPF_CLIENT_ID / GOV_CPF_CLIENT_SECRET) não configuradas.');
        }

        $basicAuth = base64_encode($this->clientId.':'.$this->clientSecret);

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$basicAuth,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->baseUrlToken, [
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful()) {
            Log::error('Erro ao obter token na API de CPF do Governo', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Não foi possível autenticar na API de CPF do Governo. Status: '.$response->status());
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;

        if (! $token) {
            throw new Exception('Token de acesso não retornado pela API do Governo.');
        }

        $expiresIn = $data['expires_in'] ?? $this->extractExpFromJwt($token) ?? 3600;
        // Armazena no cache com uma margem de segurança de 300 segundos (5 minutos)
        $ttl = max(60, $expiresIn - 300);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Tenta extrair o tempo restante em segundos a partir do campo 'exp' do payload JWT.
     */
    protected function extractExpFromJwt(string $jwtToken): ?int
    {
        $parts = explode('.', $jwtToken);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            if (isset($payload['exp'])) {
                return $payload['exp'] - time();
            }
        }

        return null;
    }

    /**
     * Consulta a API do Governo por um CPF e retorna os dados brutos.
     */
    public function consultarCpfRaw(string $cpf): array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpfLimpo) !== 11) {
            throw new Exception('O CPF informado deve conter exatamente 11 dígitos.');
        }

        $token = $this->getAccessToken();
        $cpfUsuario = ! empty($this->userCpf) ? $this->userCpf : (auth()->user()?->pessoa?->cpf ?? '00000000191');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'x-cpf-usuario' => preg_replace('/[^0-9]/', '', $cpfUsuario),
            'Content-Type' => 'application/json',
        ])->post($this->baseUrlConsulta, [
            'listaCpf' => [$cpfLimpo],
        ]);

        if (! $response->successful()) {
            Log::error('Erro ao consultar CPF na API do Governo', [
                'cpf' => $cpfLimpo,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Falha ao consultar CPF na API do Governo ('.$response->status().').');
        }

        $body = $response->json();

        // O retorno pode vir envelopado em listaCpfCompleto ou diretamente em um array de registros
        $registros = $body['listaCpfCompleto'] ?? $body['cpfCompleto'] ?? (is_array($body) && isset($body[0]) ? $body : []);

        if (empty($registros) && is_array($body)) {
            // Se o retorno for uma lista direta ou um único objeto de CPF
            $registros = isset($body['CPF']) || isset($body['cpf']) ? [$body] : $body;
        }

        $dadosCpf = $registros[0] ?? null;

        if (! $dadosCpf) {
            throw new Exception('Nenhum registro encontrado para o CPF informado.');
        }

        return $dadosCpf;
    }

    /**
     * Consulta o CPF na API e mapeia o resultado para os atributos do modelo Pessoa.
     */
    public function consultarEPopularPessoa(string $cpf): array
    {
        $raw = $this->consultarCpfRaw($cpf);

        $nome = $raw['Nome'] ?? $raw['nome'] ?? null;

        // Data de nascimento no formato AAAAMMDD -> YYYY-MM-DD
        $dataNascimentoRaw = $raw['DataNascimento'] ?? $raw['dataNascimento'] ?? null;
        $dataNascimento = null;

        if (! empty($dataNascimentoRaw) && strlen($dataNascimentoRaw) === 8) {
            try {
                $dataNascimento = Carbon::createFromFormat('Ymd', $dataNascimentoRaw)->format('Y-m-d');
            } catch (Exception $e) {
                $dataNascimento = null;
            }
        }

        // Mapeamento de Sexo (1 = Masculino, 2 = Feminino, 9 = Não especificado)
        $sexoRaw = (int) ($raw['Sexo'] ?? $raw['sexo'] ?? 9);
        $sexo = match ($sexoRaw) {
            1 => Sexo::MASCULINO->value,
            2 => Sexo::FEMININO->value,
            default => Sexo::NAO_DECLARADO->value,
        };

        // Mapeamento de Nacionalidade
        $nomePais = $raw['NomePaisNacionalidade'] ?? $raw['nomePaisNacionalidade'] ?? 'BRASIL';
        $nacionalidadeId = $this->resolverPaisId($nomePais);

        // Mapeamento de Naturalidade (Município e UF)
        $nomeMunicipio = $raw['NomeMunicipioNaturalidade'] ?? $raw['nomeMunicipioNaturalidade'] ?? null;
        $ufMunicipio = $raw['UFMunicipioNaturalidade'] ?? $raw['ufMunicipioNaturalidade'] ?? null;
        $naturalidadeId = $this->resolverCidadeId($nomeMunicipio, $ufMunicipio);

        return [
            'sucesso' => true,
            'cpf' => preg_replace('/[^0-9]/', '', $cpf),
            'nome' => $nome,
            'data_nascimento' => $dataNascimento,
            'sexo' => $sexo,
            'nacionalidade_id' => $nacionalidadeId,
            'naturalidade_id' => $naturalidadeId,
            'raw' => $raw,
        ];
    }

    /**
     * Tenta encontrar o ID do País baseado no nome retornado.
     */
    protected function resolverPaisId(?string $nomePais): ?int
    {
        if (empty($nomePais)) {
            return Pais::where('nome', 'Brasil')->value('id');
        }

        $pais = Pais::where('nome', 'LIKE', '%'.trim($nomePais).'%')->first();

        return $pais ? $pais->id : Pais::where('nome', 'Brasil')->value('id');
    }

    /**
     * Tenta encontrar a Cidade no banco com base no nome do município e sigla do estado.
     */
    protected function resolverCidadeId(?string $nomeMunicipio, ?string $uf): ?int
    {
        if (empty($nomeMunicipio)) {
            return null;
        }

        $query = Cidade::query();

        if (! empty($uf)) {
            $ufClean = strtoupper(trim($uf));
            $query->whereHas('estado', function ($q) use ($ufClean) {
                $q->where('sigla', $ufClean);
            });
        }

        $nomeClean = trim($nomeMunicipio);
        $cidade = $query->where('nome', 'LIKE', '%'.$nomeClean.'%')->first();

        if (! $cidade) {
            // Tentativa sem acentos / com busca genérica por nome
            $cidade = Cidade::where('nome', 'LIKE', '%'.$nomeClean.'%')->first();
        }

        return $cidade?->id;
    }
}
