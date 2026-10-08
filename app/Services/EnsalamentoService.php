<?php

namespace App\Services;

use App\Enums\Sexo;
use App\Exceptions\TurmaIndisponivelException;
use App\Models\Matricula;
use App\Models\Turma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Remanejamento de alunos entre as turmas de um mesmo período letivo.
 *
 * Toda matrícula já nasce numa turma (rematrícula, wizard e matrícula online escolhem a turma), então
 * aqui não existe mais "aluno sem turma": o serviço só enxerga a ocupação das turmas, move alunos de
 * uma turma para outra e redistribui os alunos de várias turmas para equilibrá-las.
 */
class EnsalamentoService
{
    public function __construct(private TurmaVagasService $vagasService) {}

    /**
     * Retorna as turmas abertas do período/série com dados calculados de lotação e equilíbrio de gênero.
     * Só contam as matrículas que ocupam vaga (Ativa, Pendente e Reserva).
     */
    public function obterTurmasCenario(int $periodoLetivoId, int $serieId, ?int $turnoId = null): Collection
    {
        $query = Turma::query()
            ->abertasParaMatricula()
            ->where('periodo_letivo_id', $periodoLetivoId)
            ->where('serie_id', $serieId);

        if ($turnoId) {
            $query->where('turno_id', $turnoId);
        }

        return $query->with(['turno', 'matriculas.pessoa'])->orderBy('nome')->get()->map(function (Turma $turma) {
            $matriculas = $turma->matriculas->filter(fn (Matricula $m) => $this->vagasService->ocupaVaga($m));
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
                'alunos' => $matriculas->values()->map(function ($m) {
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
     * Move matrículas para outra turma do MESMO período letivo.
     *
     * O período da matrícula é o da turma: mover para uma turma de outro período equivaleria a
     * rematricular/retroceder o aluno, o que tem fluxo próprio (rematrícula). A vaga é conferida com
     * a turma travada na transação, contando só quem ainda não está nela.
     *
     * @param  list<int>  $matriculaIds
     *
     * @throws InvalidArgumentException turma de outro período, fechada ou sem vagas suficientes
     */
    public function alocarAlunosEmTurma(array $matriculaIds, int $turmaId): void
    {
        DB::transaction(function () use ($matriculaIds, $turmaId) {
            $destino = Turma::query()->findOrFail($turmaId);

            $periodosDeOrigem = Matricula::query()
                ->whereIn('id', $matriculaIds)
                ->get()
                ->map(fn (Matricula $m) => $m->turma?->periodo_letivo_id)
                ->unique();

            if ($periodosDeOrigem->contains(fn ($periodoId) => (int) $periodoId !== (int) $destino->periodo_letivo_id)) {
                throw new InvalidArgumentException("O remanejamento só pode ser feito entre turmas do mesmo período letivo. A turma '{$destino->nome}' é de outro período.");
            }

            // Só quem ainda não está na turma consome vaga nova (mover para a mesma turma não conta em dobro).
            $novos = Matricula::query()
                ->whereIn('id', $matriculaIds)
                ->where('turma_id', '!=', $turmaId)
                ->count();

            try {
                if ($novos > 0) {
                    $this->vagasService->garantirVaga($turmaId, $novos);
                } elseif (! $destino->status->abertaParaMatricula()) {
                    throw TurmaIndisponivelException::fechada($destino);
                }
            } catch (TurmaIndisponivelException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }

            Matricula::whereIn('id', $matriculaIds)->update(['turma_id' => $destino->id]);
        });
    }

    /**
     * Redistribui os alunos das turmas escolhidas (mesma série e período) para equilibrá-las.
     * Entram na redistribuição as matrículas que ocupam vaga nessas turmas.
     */
    public function distribuirAutomaticamente(
        int $serieId,
        int $periodoLetivoId,
        array $turmaIds,
        string $criterio = 'equilibrio_genero',
        bool $respeitarLimiteVagas = true
    ): array {
        if (empty($turmaIds)) {
            throw new InvalidArgumentException('Selecione pelo menos uma turma para a distribuição.');
        }

        $turmas = Turma::whereIn('id', $turmaIds)
            ->abertasParaMatricula()
            ->where('serie_id', $serieId)
            ->where('periodo_letivo_id', $periodoLetivoId)
            ->get();

        if ($turmas->isEmpty()) {
            throw new InvalidArgumentException('Nenhuma turma válida encontrada para a série e período selecionados.');
        }

        $turmaIdsValidos = $turmas->pluck('id')->all();

        $alunos = $this->vagasService->queryOcupantesDe($turmaIdsValidos)->with('pessoa')->get();

        if ($alunos->isEmpty()) {
            throw new InvalidArgumentException('Não há alunos nas turmas selecionadas para distribuir.');
        }

        // Capacidades das turmas (a redistribuição recomeça do zero em cada turma)
        $capacidades = [];
        $ocupacoes = [];
        foreach ($turmas as $t) {
            $capacidades[$t->id] = $t->vagas_maximas ?: 999;
            $ocupacoes[$t->id] = 0;
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

            foreach ([$meninas, $meninos, $outros] as $grupo) {
                foreach ($grupo as $aluno) {
                    $turmaId = $this->proximaTurmaDisponivel($turmaIdsList, $turmaIndex, $ocupacoes, $capacidades, $respeitarLimiteVagas);
                    if ($turmaId) {
                        $alocacoes[$turmaId][] = $aluno->id;
                        $ocupacoes[$turmaId]++;
                        $turmaIndex = ($turmaIndex + 1) % $turmaCount;
                    }
                }
            }
        } else {
            if ($criterio === 'ordem_alfabetica') {
                $alunosOrdenados = $alunos->sortBy(fn ($m) => $m->pessoa?->nome ?? '')->values();
            } else {
                // Equilíbrio por idade (data de nascimento)
                $alunosOrdenados = $alunos->sortBy(function ($m) {
                    if (! $m->pessoa?->data_nascimento) {
                        return 0;
                    }

                    return Carbon::parse($m->pessoa->data_nascimento)->timestamp;
                })->values();
            }

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

        // Gravação em banco, com as turmas travadas: nenhuma matrícula nova entra nelas durante a troca.
        $totalAlocados = 0;
        DB::transaction(function () use ($alocacoes, $turmaIdsValidos, &$totalAlocados) {
            Turma::whereIn('id', $turmaIdsValidos)->lockForUpdate()->get();

            foreach ($alocacoes as $turmaId => $matriculaIds) {
                if (! empty($matriculaIds)) {
                    Matricula::whereIn('id', $matriculaIds)->update(['turma_id' => $turmaId]);
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
