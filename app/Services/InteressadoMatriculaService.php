<?php

namespace App\Services;

use App\Models\Interessado;
use App\Models\StatusInteressado;
use Carbon\Carbon;

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

        $alunos = $dependentes->map(function ($dependente) use ($pessoa, $contatoEhAluno) {
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
     * Idempotente: um lead já convertido mantém a data original.
     */
    public static function registrarConversao(Interessado $interessado): void
    {
        $atualizacoes = [];

        if ($interessado->data_conversao === null) {
            $atualizacoes['data_conversao'] = now();
        }

        $statusGanho = self::statusGanho();

        if ($statusGanho && $interessado->status_interessado_id !== $statusGanho->id) {
            $atualizacoes['status_interessado_id'] = $statusGanho->id;
        }

        if ($atualizacoes !== []) {
            $interessado->update($atualizacoes);
        }

        LeadScoreService::recalcular($interessado);
    }

    private static function statusGanho(): ?StatusInteressado
    {
        return StatusInteressado::where('nome', 'Matriculado')->first()
            ?? StatusInteressado::where('is_ganho', true)->orderBy('ordem')->first();
    }
}
