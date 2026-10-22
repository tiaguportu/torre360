<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cidade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViaCepService
{
    /**
     * Tempo limite de resposta HTTP em segundos.
     */
    public const TIMEOUT_SECONDS = 3;

    /**
     * Tempo limite para estabelecer conexão em segundos.
     */
    public const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * Duração do cache em dias para consultas de CEP.
     */
    public const CACHE_DAYS = 7;

    /**
     * Consulta os dados de endereço correspondentes a um CEP no ViaCEP.
     *
     * @return array{cep: string, logradouro: string, complemento: string, bairro: string, localidade: string, uf: string, ibge: string}|null
     */
    public function buscar(string $cep): ?array
    {
        $cepLimpo = preg_replace('/\D/', '', $cep);

        if (strlen($cepLimpo) !== 8) {
            return null;
        }

        $cacheKey = "viacep:{$cepLimpo}";

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_DAYS), function () use ($cepLimpo): ?array {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->retry(1, 100, throw: false)
                    ->get("https://viacep.com.br/ws/{$cepLimpo}/json/");

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();

                if (! is_array($data) || ! empty($data['erro'])) {
                    return null;
                }

                return [
                    'cep' => (string) ($data['cep'] ?? $cepLimpo),
                    'logradouro' => (string) ($data['logradouro'] ?? ''),
                    'complemento' => (string) ($data['complemento'] ?? ''),
                    'bairro' => (string) ($data['bairro'] ?? ''),
                    'localidade' => (string) ($data['localidade'] ?? ''),
                    'uf' => (string) ($data['uf'] ?? ''),
                    'ibge' => (string) ($data['ibge'] ?? ''),
                ];
            } catch (Throwable $e) {
                Log::warning('Falha de conexão com a API ViaCEP: '.$e->getMessage(), [
                    'cep' => $cepLimpo,
                ]);

                return null;
            }
        });
    }

    /**
     * Consulta o CEP e localiza o registro da Cidade no banco local pelo código IBGE.
     *
     * @return array{cep: string, logradouro: string, complemento: string, bairro: string, localidade: string, uf: string, ibge: string, cidade_id: int|null, cidade: Cidade|null}|null
     */
    public function buscarComCidade(string $cep): ?array
    {
        $dados = $this->buscar($cep);

        if (! $dados) {
            return null;
        }

        $cidade = null;
        $cidadeId = null;

        if (! empty($dados['ibge'])) {
            $cidade = Cidade::where('codigo_ibge', $dados['ibge'])->first();
            $cidadeId = $cidade?->id;
        }

        $dados['cidade_id'] = $cidadeId;
        $dados['cidade'] = $cidade;

        return $dados;
    }
}
