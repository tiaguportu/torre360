<?php

namespace App\Services;

use App\Enums\Sexo;
use App\Models\Matricula;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnsalamentoService
{
    /**
     * Retorna as turmas com dados calculados de lotação e equilíbrio de gênero.
     */
    public function obterTurmasCenario(int $periodoLetivoId, int $serieId, ?int $turnoId = null): Collection
    {
        $query = Turma::query()
            ->where('periodo_letivo_id', $periodoLetivoId)
            ->where('serie_id', $serieId);

        if ($turnoId) {
            $query->where('turno_id', $turnoId);
        }

        return $query->with(['turno', 'matriculas.pessoa'])->get()->map(function (Turma $turma) {
            $matriculas = $turma->matriculas;
            $totalAlunos = $matriculas->count();
            $vagas = $turma->vagas_maximas ?: 0;

            $meninas = $matriculas->filter(fn ($m) => $m->pessoa?->sexo === Sexo::FEMININO || $m->pessoa?->sexo === 'feminino')->count();
            $meninos = $matriculas->filter(fn ($m) => $m->pessoa?->sexo === Sexo::MASCULINO || $m->pessoa?->sexo === 'masculino')->count();
            $outros = $totalAlunos - ($meninas + $meninos);

            $percentual = $vagas > 0 ? round(($totalAlunos / $vagas) * 100, 1) : 0;

            return [
                'id' => $turma->id,
                'nome' => $turma->nome,
                'codigo' => $turma->codigo,
                'turno' => $turma->turno?->nome ?? 'Padrão',
                'vagas_maximas' => $vagas,
                'total_alunos' => $totalAlunos,
                'ocupados' => $totalAlunos,
                'vagas_restantes' => $vagas > 0 ? max(0, $vagas - $totalAlunos) : 0,
                'percentual_ocupacao' => $percentual,
                'total_meninas' => $meninas,
                'meninas' => $meninas,
                'total_meninos' => $meninos,
                'meninos' => $meninos,
                'total_outros' => $outros,
                'alunos' => $matriculas->map(function ($m) {
                    $nasc = $m->pessoa?->data_nascimento ? Carbon::parse($m->pessoa->data_nascimento) : null;

                    return [
                        'matricula_id' => $m->id,
                        'codigo' => 'MAT-'.str_pad((string) $m->id, 5, '0', STR_PAD_LEFT),
                        'nome' => $m->pessoa?->nome ?? 'Estudante',
                        'data_nascimento' => $nasc?->format('d/m/Y'),
                        'idade' => $nasc?->age,
                        'sexo' => $m->pessoa?->sexo instanceof Sexo ? $m->pessoa->sexo->value : strtolower((string) ($m->pessoa?->sexo ?? 'nao_declarado')),
                    ];
                }),
            ];
        });
    }

    /**
     * Retorna os alunos matriculados que ainda não estão ensalados em nenhuma turma.
     */
    public function obterAlunosNaoEnsalados(int $periodoLetivoId, int $serieId, ?int $turnoId = null): Collection
    {
        // 1. Matrículas diretas sem turma
        $query = Matricula::query()
            ->whereNull('turma_id')
            ->where(function (Builder $q) use ($serieId, $periodoLetivoId) {
                $q->where(function ($sub) use ($serieId, $periodoLetivoId) {
                    $sub->where('serie_id', $serieId)
                        ->where('periodo_letivo_id', $periodoLetivoId);
                })
                    ->orWhereHas('rematriculas', function ($sub) use ($serieId, $periodoLetivoId) {
                        $sub->where('serie_destino_id', $serieId)
                            ->whereHas('periodoRematricula', fn ($p) => $p->where('periodo_letivo_destino_id', $periodoLetivoId));
                    });
            })
            ->with(['pessoa', 'serie']);

        return $query->get()->map(function (Matricula $m) {
            $nasc = $m->pessoa?->data_nascimento ? Carbon::parse($m->pessoa->data_nascimento) : null;

            return [
                'matricula_id' => $m->id,
                'codigo' => 'MAT-'.str_pad((string) $m->id, 5, '0', STR_PAD_LEFT),
                'nome' => $m->pessoa?->nome ?? 'Estudante',
                'cpf' => $m->pessoa?->cpf,
                'data_matricula' => $m->created_at?->format('d/m/Y') ?? '—',
                'data_nascimento' => $nasc?->format('d/m/Y'),
                'idade' => $nasc?->age,
                'sexo' => $m->pessoa?->sexo instanceof Sexo ? $m->pessoa->sexo->value : strtolower((string) ($m->pessoa?->sexo ?? 'nao_declarado')),
            ];
        });
    }

    /**
     * Aloca uma lista de matrículas em uma turma específica.
     */
    public function alocarAlunosEmTurma(array $matriculaIds, int $turmaId): void
    {
        $turma = Turma::findOrFail($turmaId);

        $vagas = $turma->vagas_maximas ?: 0;
        $atuais = $turma->matriculas()->count();
        $novos = count($matriculaIds);

        if ($vagas > 0 && ($atuais + $novos) > $vagas) {
            $excedente = ($atuais + $novos) - $vagas;
            throw new InvalidArgumentException("A Turma '{$turma->nome}' possui capacidade para {$vagas} alunos. A alocação selecionada excederia o limite em {$excedente} vaga(s).");
        }

        Matricula::whereIn('id', $matriculaIds)->update([
            'turma_id' => $turma->id,
            'serie_id' => $turma->serie_id,
        ]);
    }

    /**
     * Remove os alunos selecionados da turma atual (desensalamento).
     */
    public function removerDeTurma(array $matriculaIds): void
    {
        Matricula::whereIn('id', $matriculaIds)->update([
            'turma_id' => null,
        ]);
    }

    /**
     * Algoritmo de Distribuição Automática Inteligente.
     */
    public function distribuirAutomaticamente(
        int $serieId,
        int $periodoLetivoId,
        array $turmaIds,
        string $criterio = 'equilibrio_genero',
        bool $redistribuirTodos = false,
        bool $respeitarLimiteVagas = true
    ): array {
        if (empty($turmaIds)) {
            throw new InvalidArgumentException('Selecione pelo menos uma turma para a distribuição.');
        }

        $turmas = Turma::whereIn('id', $turmaIds)
            ->where('serie_id', $serieId)
            ->where('periodo_letivo_id', $periodoLetivoId)
            ->get();

        if ($turmas->isEmpty()) {
            throw new InvalidArgumentException('Nenhuma turma válida encontrada para a série e período selecionados.');
        }

        // Buscar alunos a distribuir
        $matriculasQuery = Matricula::query()->with('pessoa');

        if ($redistribuirTodos) {
            $matriculasQuery->where(function (Builder $q) use ($serieId, $periodoLetivoId, $turmaIds) {
                $q->whereIn('turma_id', $turmaIds)
                    ->orWhere(function ($sub) use ($serieId, $periodoLetivoId) {
                        $sub->whereNull('turma_id')
                            ->where('serie_id', $serieId)
                            ->where('periodo_letivo_id', $periodoLetivoId);
                    });
            });
        } else {
            $matriculasQuery->whereNull('turma_id')
                ->where('serie_id', $serieId)
                ->where('periodo_letivo_id', $periodoLetivoId);
        }

        $alunos = $matriculasQuery->get();

        if ($alunos->isEmpty()) {
            throw new InvalidArgumentException('Não há alunos disponíveis para distribuição no cenário escolhido.');
        }

        // Capacidades das turmas
        $capacidades = [];
        $ocupacoes = [];
        foreach ($turmas as $t) {
            $capacidades[$t->id] = $t->vagas_maximas ?: 999;
            $ocupacoes[$t->id] = $redistribuirTodos ? 0 : $t->matriculas()->count();
        }

        // Alocação em lote de acordo com o critério
        $alocacoes = []; // turma_id => [matricula_ids]
        foreach ($turmas as $t) {
            $alocacoes[$t->id] = [];
        }

        $turmaIdsList = $turmas->pluck('id')->toArray();
        $turmaCount = count($turmaIdsList);

        if ($criterio === 'equilibrio_genero') {
            // Separação por gênero
            $meninas = $alunos->filter(fn ($m) => $m->pessoa?->sexo === Sexo::FEMININO || $m->pessoa?->sexo === 'feminino')->values();
            $meninos = $alunos->filter(fn ($m) => $m->pessoa?->sexo === Sexo::MASCULINO || $m->pessoa?->sexo === 'masculino')->values();
            $outros = $alunos->reject(fn ($m) => in_array($m->id, $meninas->pluck('id')->merge($meninos->pluck('id'))->toArray()))->values();

            $turmaIndex = 0;

            // Distribui meninas circularmente
            foreach ($meninas as $aluno) {
                $turmaId = $this->proximaTurmaDisponivel($turmaIdsList, $turmaIndex, $ocupacoes, $capacidades, $respeitarLimiteVagas);
                if ($turmaId) {
                    $alocacoes[$turmaId][] = $aluno->id;
                    $ocupacoes[$turmaId]++;
                    $turmaIndex = ($turmaIndex + 1) % $turmaCount;
                }
            }

            // Distribui meninos circularmente
            foreach ($meninos as $aluno) {
                $turmaId = $this->proximaTurmaDisponivel($turmaIdsList, $turmaIndex, $ocupacoes, $capacidades, $respeitarLimiteVagas);
                if ($turmaId) {
                    $alocacoes[$turmaId][] = $aluno->id;
                    $ocupacoes[$turmaId]++;
                    $turmaIndex = ($turmaIndex + 1) % $turmaCount;
                }
            }

            // Distribui outros
            foreach ($outros as $aluno) {
                $turmaId = $this->proximaTurmaDisponivel($turmaIdsList, $turmaIndex, $ocupacoes, $capacidades, $respeitarLimiteVagas);
                if ($turmaId) {
                    $alocacoes[$turmaId][] = $aluno->id;
                    $ocupacoes[$turmaId]++;
                    $turmaIndex = ($turmaIndex + 1) % $turmaCount;
                }
            }
        } elseif ($criterio === 'ordem_alfabetica') {
            // Ordenação alfabética
            $alunosOrdenados = $alunos->sortBy(fn ($m) => $m->pessoa?->nome ?? '')->values();
            $turmaIndex = 0;

            foreach ($alunosOrdenados as $aluno) {
                $turmaId = $this->proximaTurmaDisponivel($turmaIdsList, $turmaIndex, $ocupacoes, $capacidades, $respeitarLimiteVagas);
                if ($turmaId) {
                    $alocacoes[$turmaId][] = $aluno->id;
                    $ocupacoes[$turmaId]++;
                    $turmaIndex = ($turmaIndex + 1) % $turmaCount;
                }
            }
        } else {
            // Equilíbrio por idade (data de nascimento)
            $alunosOrdenados = $alunos->sortBy(function ($m) {
                if (! $m->pessoa?->data_nascimento) {
                    return 0;
                }

                return Carbon::parse($m->pessoa->data_nascimento)->timestamp;
            })->values();
            $turmaIndex = 0;

            foreach ($alunosOrdenados as $aluno) {
                $turmaId = $this->proximaTurmaDisponivel($turmaIdsList, $turmaIndex, $ocupacoes, $capacidades, $respeitarLimiteVagas);
                if ($turmaId) {
                    $alocacoes[$turmaId][] = $aluno->id;
                    $ocupacoes[$turmaId]++;
                    $turmaIndex = ($turmaIndex + 1) % $turmaCount;
                }
            }
        }

        // Executar gravação em banco
        $totalAlocados = 0;
        DB::transaction(function () use ($alocacoes, $serieId, &$totalAlocados) {
            foreach ($alocacoes as $turmaId => $matriculaIds) {
                if (! empty($matriculaIds)) {
                    Matricula::whereIn('id', $matriculaIds)->update([
                        'turma_id' => $turmaId,
                        'serie_id' => $serieId,
                    ]);
                    $totalAlocados += count($matriculaIds);
                }
            }
        });

        // Relatório de resultado
        $resultado = [
            'total_distribuidos' => $totalAlocados,
            'turmas' => [],
        ];

        foreach ($turmas as $t) {
            $resultado['turmas'][] = [
                'turma_id' => $t->id,
                'nome' => $t->nome,
                'novos_alunos' => count($alocacoes[$t->id]),
                'total_atual' => $ocupacoes[$t->id],
                'vagas_maximas' => $capacidades[$t->id],
            ];
        }

        return $resultado;
    }

    /**
     * Encontra a próxima turma com vagas disponíveis respeitando o limite.
     */
    private function proximaTurmaDisponivel(
        array $turmaIdsList,
        int &$startIndex,
        array $ocupacoes,
        array $capacidades,
        bool $respeitarLimite
    ): ?int {
        $count = count($turmaIdsList);

        for ($i = 0; $i < $count; $i++) {
            $idx = ($startIndex + $i) % $count;
            $turmaId = $turmaIdsList[$idx];

            if (! $respeitarLimite || $ocupacoes[$turmaId] < $capacidades[$turmaId]) {
                $startIndex = $idx;

                return $turmaId;
            }
        }

        // Se todas as turmas estiverem cheias
        return $respeitarLimite ? null : $turmaIdsList[$startIndex];
    }
}
