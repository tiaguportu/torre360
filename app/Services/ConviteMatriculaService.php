<?php

namespace App\Services;

use App\Models\Cidade;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use Illuminate\Support\Str;

/**
 * Convite de matrícula online: um link único e temporário enviado a um lead já
 * qualificado pelo CRM, para que a própria família confirme/complete os dados
 * (telefone, e-mail, série e turno de cada dependente) sem precisar navegar pelo
 * formulário público completo nem ver dados de qualquer outro registro.
 *
 * A confirmação pelo link NÃO efetiva a matrícula sozinha — quem efetiva continua sendo
 * a secretaria, pela ação "Matricular" já existente (abre o Assistente de Matrícula
 * pré-preenchido via `InteressadoMatriculaService::dadosParaWizard()`). O convite só
 * resolve a coleta de dados, reduzindo a ida e volta por telefone.
 */
class ConviteMatriculaService
{
    private const DIAS_VALIDADE_PADRAO = 7;

    /**
     * Gera (ou renova) o token de convite do lead e retorna a URL pública completa.
     */
    public function gerarConvite(Interessado $interessado, int $diasValidade = self::DIAS_VALIDADE_PADRAO): string
    {
        do {
            $token = Str::random(48);
        } while (Interessado::where('token_convite', $token)->exists());

        $interessado->update([
            'token_convite' => $token,
            'token_convite_expira_em' => now()->addDays($diasValidade),
            'token_convite_usado_em' => null,
        ]);

        return route('captacao.interessado.convite', ['token' => $token]);
    }

    /**
     * Resolve um token para o Interessado correspondente, só se o convite ainda for
     * válido (existe, não expirou, ainda não foi usado).
     */
    public function validarToken(string $token): ?Interessado
    {
        $interessado = Interessado::where('token_convite', $token)->first();

        if (! $interessado || ! $interessado->conviteValido()) {
            return null;
        }

        return $interessado;
    }

    /**
     * Converte os dados validados do formulário público no payload guardado em
     * `interessado.dados_pre_matricula` (chaves no mesmo formato do Assistente de Matrícula).
     * Os alunos usam o endereço do primeiro responsável; a secretaria pode ajustar no assistente.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function montarPreMatricula(array $validated, ?string $ip = null): array
    {
        $principal = $validated['responsavel'];
        $segundo = $validated['segundo_responsavel'] ?? [];
        $temSegundo = filled($segundo['nome'] ?? null);

        $endereco = [
            'cep' => $principal['cep'],
            'logradouro' => $principal['logradouro'],
            'numero' => $principal['numero'],
            'complemento' => $principal['complemento'] ?? null,
            'bairro' => $principal['bairro'],
            'cidade_id' => filled($principal['cidade_ibge'] ?? null)
                ? Cidade::where('codigo_ibge', $principal['cidade_ibge'])->value('id')
                : null,
        ];

        $principalFinanceiro = (bool) ($principal['is_financeiro'] ?? false);
        $segundoFinanceiro = $temSegundo && (bool) ($segundo['is_financeiro'] ?? false);

        // Ao menos um responsável financeiro: sem marcação, o principal assume.
        if (! $principalFinanceiro && ! $segundoFinanceiro) {
            $principalFinanceiro = true;
        }

        $percentualSegundo = $segundoFinanceiro && $principalFinanceiro
            ? (int) ($segundo['percentual'] ?? 50)
            : ($segundoFinanceiro ? 100 : 0);

        $responsaveis = [[
            'nome' => $principal['nome'],
            'cpf' => preg_replace('/\D/', '', $principal['cpf']),
            'data_nascimento' => $principal['data_nascimento'],
            'email' => $principal['email'] ?? null,
            'telefone' => $principal['telefone'],
            'tipo_vinculo_id' => (int) $principal['tipo_vinculo_id'],
            'is_financeiro' => $principalFinanceiro,
            'percentual' => $principalFinanceiro ? 100 - $percentualSegundo : 0,
        ] + $endereco];

        if ($temSegundo) {
            $responsaveis[] = [
                'nome' => $segundo['nome'],
                'cpf' => preg_replace('/\D/', '', $segundo['cpf']),
                'email' => $segundo['email'] ?? null,
                'telefone' => $segundo['telefone'] ?? null,
                'tipo_vinculo_id' => (int) $segundo['tipo_vinculo_id'],
                'is_financeiro' => $segundoFinanceiro,
                'percentual' => $percentualSegundo,
            ] + $endereco;
        }

        $alunos = [];
        foreach ($validated['dependentes'] as $dependente) {
            $alunos[$dependente['id']] = [
                'data_nascimento' => $dependente['data_nascimento'],
                'cpf' => filled($dependente['cpf'] ?? null) ? preg_replace('/\D/', '', $dependente['cpf']) : null,
                'sexo' => $dependente['sexo'] ?? null,
            ] + $endereco;
        }

        return [
            'responsaveis' => $responsaveis,
            'alunos' => $alunos,
            'lgpd_aceite_em' => now()->toIso8601String(),
            'lgpd_ip' => $ip,
        ];
    }

    /**
     * Aplica os dados confirmados pela família: atualiza o contato do responsável e a
     * série de cada dependente informado (o turno de preferência, assim como no
     * formulário público original, não é uma coluna estruturada — vai para o relato do
     * histórico de contato), marca o convite como usado e recalcula o lead score (o
     * preenchimento é, em si, um sinal de engajamento).
     *
     * @param  array{telefone?: ?string, email?: ?string}  $dadosResponsavel
     * @param  array<int, array{id: int, serie_id?: ?int, turno_preferencia?: ?string}>  $dependentes  indexado por InteressadoDependente::id
     * @param  array<string, mixed>  $preMatricula  dados completos coletados (responsáveis, alunos, aceite LGPD),
     *                                              guardados em `interessado.dados_pre_matricula` para pré-preencher o Assistente de Matrícula
     */
    public function confirmar(Interessado $interessado, array $dadosResponsavel, array $dependentes, array $preMatricula = []): void
    {
        if ($interessado->pessoa && ($dadosResponsavel['telefone'] ?? null)) {
            $interessado->pessoa->update(['telefone' => $dadosResponsavel['telefone']]);
        }

        if ($interessado->pessoa && ($dadosResponsavel['email'] ?? null)) {
            $interessado->pessoa->update(['email' => $dadosResponsavel['email']]);
        }

        $dependentesDoInteressado = $interessado->dependentes()->get()->keyBy('id');
        $relatoLinhas = [];

        foreach ($dependentes as $dadosDependente) {
            $dependenteId = $dadosDependente['id'] ?? null;
            $dependente = $dependenteId ? $dependentesDoInteressado->get($dependenteId) : null;

            // Nunca atualiza um dependente que não pertença a este interessado — é
            // exatamente o que impede o convite de um lead de alterar dados de outro.
            if (! $dependente) {
                continue;
            }

            if (! empty($dadosDependente['serie_id'])) {
                $dependente->update(['serie_id' => $dadosDependente['serie_id']]);
            }

            if (! empty($dadosDependente['data_nascimento'])) {
                $dependente->update(['data_nascimento' => $dadosDependente['data_nascimento']]);
            }

            $linha = $dependente->nome_crianca;
            if (! empty($dadosDependente['turno_preferencia'])) {
                $linha .= ' | Turno: '.$dadosDependente['turno_preferencia'];
            }
            $relatoLinhas[] = $linha;
        }

        $interessado->update([
            'token_convite_usado_em' => now(),
            'dados_pre_matricula' => $preMatricula !== []
                ? $preMatricula + ['confirmado_em' => now()->toIso8601String()]
                : $interessado->dados_pre_matricula,
        ]);

        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Confirmação via Convite Online']);

        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => ($preMatricula !== []
                    ? "A família preencheu a pré-matrícula online (responsáveis, alunos e endereço) pelo link de convite. Os dados já estão disponíveis no Assistente de Matrícula.\n"
                    : "A família confirmou os dados de matrícula pelo link de convite online.\n")
                .implode("\n", $relatoLinhas),
            'data_contato' => now(),
        ]);

        LeadScoreService::recalcular($interessado);
    }
}
