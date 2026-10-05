<?php

namespace App\Services;

use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ponte entre o CRM e o Assistente de Matrícula: monta os dados iniciais do
 * wizard a partir do lead e registra a conversão quando a matrícula é feita.
 */
class InteressadoMatriculaService
{
    /**
     * Dados para pré-preencher o `EnrollmentWizard` (estado do formulário) com o lead.
     *
     * Quando o contato do lead tem o mesmo nome de um dependente, o lead é o próprio
     * aluno (preencheu o formulário "por conta própria"): o contato vai para o aluno e
     * nenhum responsável é presumido. Caso contrário, o contato é o responsável.
     *
     * @return array<string, mixed>
     */
    public static function dadosParaWizard(Interessado $interessado): array
    {
        $interessado->loadMissing(['pessoa', 'dependentes.serie.curso']);

        $pessoa = $interessado->pessoa;
        $dependentes = $interessado->dependentes;

        $nomesDependentes = $dependentes
            ->map(fn ($dependente) => mb_strtolower(trim((string) $dependente->nome_crianca)))
            ->all();

        $contatoEhAluno = $pessoa !== null
            && in_array(mb_strtolower(trim((string) $pessoa->nome)), $nomesDependentes, true);

        // Dados completos preenchidos pela família no convite de pré-matrícula online (se houver).
        $pre = $interessado->dados_pre_matricula ?? [];

        $alunos = $dependentes->map(function ($dependente) use ($pessoa, $contatoEhAluno, $pre) {
            $aluno = [
                'pessoa_id_existente' => null,
                'nome' => $dependente->nome_crianca,
                'data_nascimento' => filled($dependente->data_nascimento) ? Carbon::parse($dependente->data_nascimento)->toDateString() : null,
            ];

            if ($contatoEhAluno && mb_strtolower(trim((string) $pessoa->nome)) === mb_strtolower(trim((string) $dependente->nome_crianca))) {
                $aluno = array_merge($aluno, [
                    'cpf' => $pessoa->cpf,
                    'email' => $pessoa->email,
                    'telefone' => $pessoa->telefone,
                    'pessoa_id_existente' => $pessoa->id,
                ]);
            }

            $dadosAluno = $pre['alunos'][$dependente->id] ?? null;

            if ($dadosAluno) {
                $aluno = array_merge($aluno, array_filter($dadosAluno, fn ($valor) => filled($valor)));

                if (filled($dadosAluno['cpf'] ?? null)) {
                    $aluno['pessoa_id_existente'] = Pessoa::where('cpf', $dadosAluno['cpf'])->value('id') ?? $aluno['pessoa_id_existente'];
                }
            }

            return $aluno;
        })->values()->all();

        $responsaveis = [];

        if ($pessoa && ! $contatoEhAluno) {
            $responsaveis[] = [
                'nome' => $pessoa->nome,
                'cpf' => $pessoa->cpf,
                'email' => $pessoa->email,
                'telefone' => $pessoa->telefone,
                'pessoa_id_existente' => $pessoa->id,
                'is_financeiro' => true,
                'percentual' => 100,
            ];
        }

        if (! empty($pre['responsaveis'])) {
            $responsaveis = collect($pre['responsaveis'])
                ->map(function (array $responsavel, int $indice) use ($pessoa, $contatoEhAluno) {
                    $responsavel['pessoa_id_existente'] = (filled($responsavel['cpf'] ?? null)
                        ? Pessoa::where('cpf', $responsavel['cpf'])->value('id')
                        : null) ?? ($indice === 0 && $pessoa && ! $contatoEhAluno ? $pessoa->id : null);

                    return $responsavel;
                })
                ->values()
                ->all();
        }

        $dados = [];

        if ($alunos !== []) {
            $dados['alunos'] = $alunos;
        }

        if ($responsaveis !== []) {
            $dados['responsaveis'] = $responsaveis;
        }

        $serie = $dependentes->first(fn ($dependente) => $dependente->serie !== null)?->serie;

        if ($serie?->curso) {
            $dados['curso_id'] = $serie->curso->id;
            $dados['unidade_id'] = $serie->curso->unidade_id;
        }

        return $dados;
    }

    /**
     * Marca o lead como convertido: data de conversão, status "ganho" e novo lead score.
     * Atualiza indicação MGM se houver e migra documentos de pré-admissão para a matrícula.
     * Idempotente: um lead já convertido mantém a data original.
     *
     * @param  array<int, Matricula>|Collection<int, Matricula>  $matriculas
     */
    public static function registrarConversao(Interessado $interessado, array|Collection $matriculas = []): void
    {
        $atualizacoes = [];

        if ($interessado->data_conversao === null) {
            $atualizacoes['data_conversao'] = now();
        }

        $statusGanho = self::statusGanho();

        if ($statusGanho && $interessado->status_interessado_id !== $statusGanho->id) {
            $atualizacoes['status_interessado_id'] = $statusGanho->id;
        }

        // Minimização de dados (LGPD): a pré-matrícula já virou cadastro, o rascunho não é mais necessário.
        if ($interessado->dados_pre_matricula !== null) {
            $atualizacoes['dados_pre_matricula'] = null;
        }

        if ($atualizacoes !== []) {
            $interessado->update($atualizacoes);
        }

        // Se o lead veio de uma indicação (Família Indica Família), atualiza o status da indicação
        $interessado->loadMissing('indicacao');

        if ($interessado->indicacao) {
            $interessado->indicacao->marcarMatriculado();
        }

        // Migração suave de documentos de pré-admissão para a nova matrícula
        $matriculasCol = collect($matriculas);

        if ($matriculasCol->isNotEmpty() && $interessado->documentosInseridos()->whereNull('matricula_id')->exists()) {
            $documentosPendentes = $interessado->documentosInseridos()->whereNull('matricula_id')->with('dependente')->get();

            foreach ($documentosPendentes as $doc) {
                $matriculaAlvo = null;

                // Tenta associar pela correspondência do nome do dependente
                if ($doc->dependente) {
                    $nomeDependente = mb_strtolower(trim((string) $doc->dependente->nome_crianca));
                    $matriculaAlvo = $matriculasCol->first(function ($m) use ($nomeDependente) {
                        return mb_strtolower(trim((string) ($m->pessoa?->nome ?? ''))) === $nomeDependente;
                    });
                }

                // Fallback: primeira matrícula criada no processo
                $matriculaAlvo ??= $matriculasCol->first();

                if ($matriculaAlvo) {
                    $doc->update([
                        'matricula_id' => $matriculaAlvo->id,
                    ]);
                }
            }
        }

        LeadScoreService::recalcular($interessado);
    }

    private static function statusGanho(): ?StatusInteressado
    {
        return StatusInteressado::ganho();
    }
}
