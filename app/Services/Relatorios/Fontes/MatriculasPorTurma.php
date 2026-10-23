<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\SituacaoMatricula;
use App\Enums\TipoColunaRelatorio;
use App\Models\Matricula;
use App\Models\Turma;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Matrículas com aluno, turma, série e curso: a lista que a secretaria mais pede por turma ou por situação.
 */
final class MatriculasPorTurma implements FonteDeRelatorio
{
    use IndexaItens;

    public function chave(): string
    {
        return 'matriculas';
    }

    public function titulo(): string
    {
        return 'Matrículas por turma';
    }

    public function descricao(): string
    {
        return 'Matrículas com aluno, turma, série, curso e situação.';
    }

    public function permissao(): string
    {
        return 'ViewAny:Matricula';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('matricula', 'Matrícula', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('aluno', 'Aluno', sensivel: true),
            new ColunaRelatorio('nascimento', 'Nascimento', TipoColunaRelatorio::Data, sensivel: true, padrao: false),
            new ColunaRelatorio('turma', 'Turma'),
            new ColunaRelatorio('serie', 'Série'),
            new ColunaRelatorio('curso', 'Curso', padrao: false),
            new ColunaRelatorio('situacao', 'Situação'),
            new ColunaRelatorio('ativacao', 'Ativada em', TipoColunaRelatorio::Data, padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('turma', 'Turma', 'opcao', Turma::query()->orderBy('nome')->limit(300)->pluck('nome', 'id')->map(fn ($nome): string => (string) $nome)->all()),
            new FiltroRelatorio('situacao', 'Situação', 'opcao', collect(SituacaoMatricula::cases())->mapWithKeys(fn (SituacaoMatricula $s): array => [$s->value => (string) $s->getLabel()])->all()),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = Matricula::query()
            ->with(['pessoa', 'turma.serie.curso'])
            ->orderBy('turma_id')
            ->orderBy('id');

        if (filled($filtros['turma'] ?? null)) {
            $consulta->where('turma_id', (int) $filtros['turma']);
        }

        if (filled($filtros['situacao'] ?? null) && SituacaoMatricula::tryFrom((string) $filtros['situacao'])) {
            $consulta->where('situacao', (string) $filtros['situacao']);
        }

        return $consulta->limit($limite)->get()->map(fn (Matricula $matricula): array => [
            'matricula' => $matricula->id,
            'aluno' => $matricula->pessoa?->nome,
            'nascimento' => $matricula->pessoa?->data_nascimento,
            'turma' => $matricula->turma?->nome,
            'serie' => $matricula->turma?->serie?->nome,
            'curso' => $matricula->turma?->serie?->curso?->nome_externo ?? $matricula->turma?->serie?->curso?->nome_interno,
            'situacao' => $matricula->situacao instanceof SituacaoMatricula ? $matricula->situacao->getLabel() : (string) $matricula->situacao,
            'ativacao' => $matricula->data_ativacao,
        ])->all();
    }
}
