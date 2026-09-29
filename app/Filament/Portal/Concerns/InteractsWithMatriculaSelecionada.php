<?php

namespace App\Filament\Portal\Concerns;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Seleção do aluno (matrícula) nas páginas do Portal da Família.
 *
 * O id vem do cliente (querystring/Livewire) e por isso nunca é confiável: a
 * matrícula só é devolvida se pertencer às pessoas acessíveis do usuário logado
 * (ele mesmo e seus dependentes); qualquer outro valor cai na primeira matrícula.
 */
trait InteractsWithMatriculaSelecionada
{
    #[Url(as: 'aluno')]
    public ?int $matriculaId = null;

    /**
     * @var Collection<int, Matricula>|null
     */
    protected ?Collection $matriculasAcessiveis = null;

    /**
     * Matrículas do usuário e de seus dependentes: ativas primeiro, depois as mais recentes.
     *
     * @return Collection<int, Matricula>
     */
    public function getMatriculasAcessiveis(): Collection
    {
        return $this->matriculasAcessiveis ??= Matricula::query()
            ->whereIn('pessoa_id', auth()->user()->pessoasAcessiveis()->pluck('id'))
            ->with(['pessoa', 'turma.serie.curso', 'periodoLetivo'])
            ->get()
            ->sortByDesc(fn (Matricula $matricula): array => [
                $matricula->situacao === SituacaoMatricula::ATIVA ? 1 : 0,
                (int) $matricula->periodo_letivo_id,
                $matricula->id,
            ])
            ->keyBy('id');
    }

    public function getMatriculaSelecionada(): ?Matricula
    {
        $matriculas = $this->getMatriculasAcessiveis();

        if ($this->matriculaId !== null && $matriculas->has($this->matriculaId)) {
            return $matriculas->get($this->matriculaId);
        }

        return $matriculas->first();
    }

    /**
     * Opções do seletor de aluno: id => "Aluno — Turma (Período)".
     *
     * @return array<int, string>
     */
    public function getOpcoesAlunos(): array
    {
        return $this->getMatriculasAcessiveis()
            ->mapWithKeys(fn (Matricula $matricula): array => [
                $matricula->id => trim(sprintf(
                    '%s — %s%s',
                    $matricula->pessoa?->nome ?? 'Aluno',
                    $matricula->turma?->nome ?? 'Sem turma',
                    $matricula->periodoLetivo ? " ({$matricula->periodoLetivo->nome})" : '',
                )),
            ])
            ->all();
    }
}
