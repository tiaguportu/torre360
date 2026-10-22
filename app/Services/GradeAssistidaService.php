<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Disciplina;
use App\Models\GradeHorario;
use App\Models\MatrizCurricular;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TempoAula;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grade horária assistida (Onda 64): confere a grade de uma turma contra a matriz curricular da série e sugere os horários
 * que faltam, respeitando os tempos do turno, a disponibilidade dos professores e o que eles já têm em outras turmas.
 *
 * A sugestão é gulosa (preenche primeiro as disciplinas com menos opções de horário) e determinística: dado o mesmo estado
 * da grade, devolve a mesma proposta. Ela não troca horários que já existem; quando sobra aula sem lugar, diz por quê.
 *
 * Quem leciona cada disciplina na turma vem de `turma_disciplina.professor_id`. A carga da matriz é em aulas por semana.
 */
class GradeAssistidaService
{
    public const SITUACAO_COMPLETA = 'completa';

    public const SITUACAO_INCOMPLETA = 'incompleta';

    public const SITUACAO_EXCEDENTE = 'excedente';

    public const SITUACAO_FORA_DA_MATRIZ = 'fora_da_matriz';

    public const SITUACAO_SEM_CARGA = 'sem_carga';

    public function __construct(
        private readonly TempoAulaService $tempos,
        private readonly DisponibilidadeProfessorService $disponibilidade,
        private readonly GradeHorarioConflitoService $conflitos,
    ) {}

    /**
     * @return Collection<int, TempoAula>
     */
    public function temposDaTurma(Turma $turma): Collection
    {
        return $this->tempos->doTurno($turma->turno_id);
    }

    // --- Conferência com a matriz ------------------------------------------------------------------------------------

    /**
     * Uma linha por disciplina da matriz da série (e por disciplina que está na grade sem estar na matriz), com as aulas
     * esperadas e as já colocadas.
     *
     * @return Collection<int, array{disciplina_id: int, disciplina: string, professor_id: ?int, professor: ?string, esperadas: ?int, alocadas: int, faltam: int, excedem: int, na_matriz: bool, situacao: string}>
     */
    public function cobertura(Turma $turma): Collection
    {
        $tempos = $this->temposDaTurma($turma);

        $matriz = $turma->serie_id
            ? MatrizCurricular::query()->where('serie_id', $turma->serie_id)->orderBy('ordem')->orderBy('id')->get()->keyBy('disciplina_id')
            : collect();

        $alocadas = [];

        foreach (GradeHorario::query()->where('turma_id', $turma->id)->get(['disciplina_id', 'hora_inicio', 'hora_fim']) as $horario) {
            $alocadas[$horario->disciplina_id] = ($alocadas[$horario->disciplina_id] ?? 0)
                + $this->tempos->aulasDoHorario((string) $horario->hora_inicio, (string) $horario->hora_fim, $tempos);
        }

        $professores = DB::table('turma_disciplina')->where('turma_id', $turma->id)->pluck('professor_id', 'disciplina_id');

        $ids = $matriz->keys()
            ->merge(array_keys($alocadas))
            ->unique()
            ->values();

        $nomes = Disciplina::query()->whereIn('id', $ids)->pluck('nome', 'id');
        $nomesProfessores = Pessoa::query()->whereIn('id', $professores->filter()->values())->pluck('nome', 'id');

        $linhas = $ids->map(function (int $disciplinaId) use ($matriz, $alocadas, $professores, $nomes, $nomesProfessores): array {
            $naMatriz = $matriz->has($disciplinaId);
            $esperadas = $naMatriz ? $matriz->get($disciplinaId)->carga_horaria_semanal : null;
            $colocadas = $alocadas[$disciplinaId] ?? 0;
            $professorId = $professores->get($disciplinaId);

            $situacao = match (true) {
                ! $naMatriz => self::SITUACAO_FORA_DA_MATRIZ,
                $esperadas === null => self::SITUACAO_SEM_CARGA,
                $colocadas === $esperadas => self::SITUACAO_COMPLETA,
                $colocadas < $esperadas => self::SITUACAO_INCOMPLETA,
                default => self::SITUACAO_EXCEDENTE,
            };

            return [
                'disciplina_id' => $disciplinaId,
                'disciplina' => (string) ($nomes[$disciplinaId] ?? "Disciplina #{$disciplinaId}"),
                'professor_id' => $professorId ? (int) $professorId : null,
                'professor' => $professorId ? ($nomesProfessores[$professorId] ?? null) : null,
                'esperadas' => $esperadas === null ? null : (int) $esperadas,
                'alocadas' => $colocadas,
                'faltam' => $esperadas === null ? 0 : max(0, (int) $esperadas - $colocadas),
                'excedem' => $esperadas === null ? 0 : max(0, $colocadas - (int) $esperadas),
                'na_matriz' => $naMatriz,
                'situacao' => $situacao,
            ];
        });

        // A matriz manda na ordem; o que está fora dela vem depois, por nome.
        return $linhas->sortBy(fn (array $l): array => [$l['na_matriz'] ? 0 : 1, $l['na_matriz'] ? $matriz->keys()->search($l['disciplina_id']) : 0, $l['disciplina']])->values();
    }

    /**
     * Resumo das turmas do período (menos as concluídas e canceladas): quantas aulas a matriz pede, quantas já estão na
     * grade e se o turno tem tempos cadastrados.
     *
     * @return Collection<int, array{turma: Turma, sem_tempos: bool, esperadas: int, alocadas: int, faltam: int, excedem: int, fora_da_matriz: int}>
     */
    public function visaoGeral(PeriodoLetivo $periodo): Collection
    {
        return Turma::query()
            ->where('periodo_letivo_id', $periodo->id)
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->orderBy('nome')
            ->get()
            ->map(function (Turma $turma): array {
                $linhas = $this->cobertura($turma);
                $daMatriz = $linhas->where('na_matriz', true);

                return [
                    'turma' => $turma,
                    'sem_tempos' => $this->temposDaTurma($turma)->isEmpty(),
                    'esperadas' => (int) $daMatriz->sum('esperadas'),
                    'alocadas' => (int) $daMatriz->sum('alocadas'),
                    'faltam' => (int) $daMatriz->sum('faltam'),
                    'excedem' => (int) $daMatriz->sum('excedem'),
                    'fora_da_matriz' => $linhas->where('na_matriz', false)->count(),
                ];
            });
    }

    /**
     * A grade semanal da turma pronta para desenhar: uma linha por tempo do turno e uma coluna por dia. Horário que não
     * bate com nenhum tempo vai para `fora_dos_tempos`.
     *
     * @return array{dias: array<int, string>, tempos: Collection<int, TempoAula>, celulas: array<int, array<int, list<GradeHorario>>>, fora_dos_tempos: Collection<int, GradeHorario>}
     */
    public function grade(Turma $turma): array
    {
        $tempos = $this->temposDaTurma($turma);
        $dias = collect((array) config('grade_horaria.dias_da_semana', [1, 2, 3, 4, 5]))
            ->mapWithKeys(fn ($dia): array => [(int) $dia => DisponibilidadeProfessorService::DIAS[(int) $dia] ?? (string) $dia])
            ->all();

        $horarios = GradeHorario::query()
            ->where('turma_id', $turma->id)
            ->with(['disciplina', 'professor', 'sala'])
            ->orderBy('hora_inicio')
            ->get();

        $celulas = [];
        $fora = collect();

        foreach ($horarios as $horario) {
            $ocupados = $this->tempos->temposOcupados((string) $horario->hora_inicio, (string) $horario->hora_fim, $tempos);

            if ($ocupados->isEmpty() || ! array_key_exists($horario->dia_semana, $dias)) {
                $fora->push($horario);

                continue;
            }

            foreach ($ocupados as $tempo) {
                $celulas[$tempo->ordem][$horario->dia_semana][] = $horario;
            }
        }

        return ['dias' => $dias, 'tempos' => $tempos, 'celulas' => $celulas, 'fora_dos_tempos' => $fora];
    }

    // --- Sugestão ----------------------------------------------------------------------------------------------------

    /**
     * Propõe os horários que faltam para a turma, sem gravar nada.
     *
     * @return array{propostas: list<array{dia_semana: int, tempo_ordem: int, hora_inicio: string, hora_fim: string, disciplina_id: int, disciplina: string, professor_id: ?int, professor: ?string}>, nao_alocadas: list<array{disciplina_id: int, disciplina: string, quantidade: int, motivo: string}>, avisos: list<string>}
     */
    public function sugerir(Turma $turma): array
    {
        $resultado = ['propostas' => [], 'nao_alocadas' => [], 'avisos' => []];
        $tempos = $this->temposDaTurma($turma)->values();

        if ($tempos->isEmpty()) {
            $resultado['avisos'][] = 'O turno desta turma não tem tempos de aula cadastrados. Cadastre em Configurações > Turnos.';

            return $resultado;
        }

        $dias = array_values(array_unique(array_map('intval', (array) config('grade_horaria.dias_da_semana', [1, 2, 3, 4, 5]))));
        $limiteDoDia = max(1, (int) config('grade_horaria.maximo_aulas_por_dia_da_disciplina', 2));
        $sugerirSemProfessor = (bool) config('grade_horaria.sugerir_sem_professor', false);

        $demandas = $this->cobertura($turma)
            ->filter(fn (array $linha): bool => $linha['na_matriz'] && $linha['esperadas'] !== null && $linha['faltam'] > 0)
            ->values();

        if ($demandas->isEmpty()) {
            return $resultado;
        }

        $professoresIds = $demandas->pluck('professor_id')->filter()->unique()->values()->all();
        $janelas = $this->disponibilidade->janelasDe($professoresIds);
        $ocupacaoProfessores = $this->horariosDosProfessores($turma, $professoresIds);

        // Estado da turma: tempos já tomados, aulas de cada disciplina por dia e carga de cada dia.
        $ocupada = [];
        $aulasPorDia = [];
        $ordensPorDia = [];
        $carga = [];

        foreach (GradeHorario::query()->where('turma_id', $turma->id)->get() as $horario) {
            $dia = (int) $horario->dia_semana;

            foreach ($this->tempos->temposOcupados((string) $horario->hora_inicio, (string) $horario->hora_fim, $tempos) as $tempo) {
                $ocupada[$dia][$tempo->ordem] = true;
                $ordensPorDia[$horario->disciplina_id][$dia][] = $tempo->ordem;
            }

            $aulas = $this->tempos->aulasDoHorario((string) $horario->hora_inicio, (string) $horario->hora_fim, $tempos);
            $aulasPorDia[$horario->disciplina_id][$dia] = ($aulasPorDia[$horario->disciplina_id][$dia] ?? 0) + $aulas;
            $carga[$dia] = ($carga[$dia] ?? 0) + $aulas;
        }

        $cabe = function (int $dia, TempoAula $tempo, ?int $professorId) use (&$ocupada, &$ocupacaoProfessores, $janelas): bool {
            if (isset($ocupada[$dia][$tempo->ordem])) {
                return false;
            }

            if ($professorId === null) {
                return true;
            }

            foreach ($ocupacaoProfessores[$professorId][$dia] ?? [] as [$inicio, $fim]) {
                if ($inicio < $tempo->fim() && $fim > $tempo->inicio()) {
                    return false;
                }
            }

            return $this->disponibilidade->motivoDeIndisponibilidade($janelas->get($professorId, collect()), $dia, $tempo->inicio(), $tempo->fim()) === null;
        };

        // Quem tem menos horários possíveis escolhe primeiro: é o que mais corre risco de ficar sem lugar.
        $ordenadas = $demandas
            ->map(function (array $linha) use ($dias, $tempos, $cabe): array {
                $possiveis = 0;

                foreach ($dias as $dia) {
                    foreach ($tempos as $tempo) {
                        if ($cabe($dia, $tempo, $linha['professor_id'])) {
                            $possiveis++;
                        }
                    }
                }

                return $linha + ['possiveis' => $possiveis];
            })
            ->sortBy(fn (array $linha): array => [$linha['possiveis'], -$linha['faltam'], $linha['disciplina']])
            ->values();

        foreach ($ordenadas as $linha) {
            $professorId = $linha['professor_id'];
            $disciplinaId = $linha['disciplina_id'];

            if ($professorId === null && ! $sugerirSemProfessor) {
                $resultado['nao_alocadas'][] = [
                    'disciplina_id' => $disciplinaId,
                    'disciplina' => $linha['disciplina'],
                    'quantidade' => $linha['faltam'],
                    'motivo' => 'Defina o professor desta disciplina na turma: sem ele a sugestão não tem como evitar conflitos.',
                ];

                continue;
            }

            for ($colocadas = 0; $colocadas < $linha['faltam']; $colocadas++) {
                $melhor = null;

                foreach ($dias as $posicaoDoDia => $dia) {
                    if (($aulasPorDia[$disciplinaId][$dia] ?? 0) >= $limiteDoDia) {
                        continue;
                    }

                    foreach ($tempos as $tempo) {
                        if (! $cabe($dia, $tempo, $professorId)) {
                            continue;
                        }

                        $vizinho = collect($ordensPorDia[$disciplinaId][$dia] ?? [])->contains(fn (int $o): bool => abs($o - $tempo->ordem) === 1);
                        $pontos = [
                            ($aulasPorDia[$disciplinaId][$dia] ?? 0) * 100 - ($vizinho ? 50 : 0) + ($carga[$dia] ?? 0) * 10 + $posicaoDoDia,
                            $tempo->ordem,
                        ];

                        if ($melhor === null || $pontos < $melhor['pontos']) {
                            $melhor = ['dia' => $dia, 'tempo' => $tempo, 'pontos' => $pontos];
                        }
                    }
                }

                if ($melhor === null) {
                    $resultado['nao_alocadas'][] = [
                        'disciplina_id' => $disciplinaId,
                        'disciplina' => $linha['disciplina'],
                        'quantidade' => $linha['faltam'] - $colocadas,
                        'motivo' => $this->motivoDeNaoCaber($linha, $dias, $tempos, $cabe, $aulasPorDia, $limiteDoDia),
                    ];

                    break;
                }

                $dia = $melhor['dia'];
                /** @var TempoAula $tempo */
                $tempo = $melhor['tempo'];

                $ocupada[$dia][$tempo->ordem] = true;
                $ordensPorDia[$disciplinaId][$dia][] = $tempo->ordem;
                $aulasPorDia[$disciplinaId][$dia] = ($aulasPorDia[$disciplinaId][$dia] ?? 0) + 1;
                $carga[$dia] = ($carga[$dia] ?? 0) + 1;

                if ($professorId !== null) {
                    $ocupacaoProfessores[$professorId][$dia][] = [$tempo->inicio(), $tempo->fim()];
                }

                $resultado['propostas'][] = [
                    'dia_semana' => $dia,
                    'tempo_ordem' => $tempo->ordem,
                    'hora_inicio' => $tempo->inicio(),
                    'hora_fim' => $tempo->fim(),
                    'disciplina_id' => $disciplinaId,
                    'disciplina' => $linha['disciplina'],
                    'professor_id' => $professorId,
                    'professor' => $linha['professor'],
                ];
            }
        }

        usort($resultado['propostas'], fn (array $a, array $b): int => [$a['dia_semana'], $a['tempo_ordem']] <=> [$b['dia_semana'], $b['tempo_ordem']]);

        return $resultado;
    }

    /**
     * Diz por que uma aula não coube: sem tempo livre na turma, professor sem disponibilidade, professor ocupado ou o
     * limite diário da disciplina.
     *
     * @param  array<string, mixed>  $linha
     * @param  list<int>  $dias
     * @param  Collection<int, TempoAula>  $tempos
     * @param  array<int, array<int, int>>  $aulasPorDia
     */
    private function motivoDeNaoCaber(array $linha, array $dias, Collection $tempos, callable $cabe, array $aulasPorDia, int $limiteDoDia): string
    {
        $professor = $linha['professor'] ?? 'O professor';

        $livresDaTurma = 0;
        $comProfessorLivre = 0;
        $noLimite = 0;

        foreach ($dias as $dia) {
            foreach ($tempos as $tempo) {
                if (! $cabe($dia, $tempo, null)) {
                    continue;
                }

                $livresDaTurma++;

                if ($cabe($dia, $tempo, $linha['professor_id'])) {
                    $comProfessorLivre++;

                    if (($aulasPorDia[$linha['disciplina_id']][$dia] ?? 0) >= $limiteDoDia) {
                        $noLimite++;
                    }
                }
            }
        }

        return match (true) {
            $livresDaTurma === 0 => 'A turma não tem mais tempo livre na semana.',
            $comProfessorLivre === 0 => "{$professor} não tem disponibilidade ou já está ocupado em todos os horários livres da turma.",
            $noLimite === $comProfessorLivre => "A disciplina já está no limite de {$limiteDoDia} aulas por dia nos horários que sobraram.",
            default => 'Não há horário livre que sirva para a turma e para o professor.',
        };
    }

    /**
     * O que cada professor já tem em outras turmas que dividem o calendário com a desta: [professor][dia] => [[início, fim]].
     *
     * @param  list<int>  $professoresIds
     * @return array<int, array<int, list<array{0: string, 1: string}>>>
     */
    private function horariosDosProfessores(Turma $turma, array $professoresIds): array
    {
        if ($professoresIds === []) {
            return [];
        }

        $consulta = GradeHorario::query()
            ->whereIn('professor_id', $professoresIds)
            ->where('turma_id', '!=', $turma->id);

        $periodo = $turma->periodo_letivo_id ? PeriodoLetivo::query()->find($turma->periodo_letivo_id) : null;
        $ocupacao = [];

        foreach ($this->conflitos->escopoDoPeriodo($consulta, $periodo)->get(['professor_id', 'dia_semana', 'hora_inicio', 'hora_fim']) as $horario) {
            $ocupacao[$horario->professor_id][(int) $horario->dia_semana][] = [substr((string) $horario->hora_inicio, 0, 5), substr((string) $horario->hora_fim, 0, 5)];
        }

        return $ocupacao;
    }

    // --- Aplicação ---------------------------------------------------------------------------------------------------

    /**
     * Grava a sugestão. Calcula de novo na hora de aplicar (nunca confia no que a tela mostrou) e confere cada horário
     * contra os conflitos antes de gravar: o que conflitar nesse meio-tempo fica de fora e é avisado.
     *
     * @return array{criadas: int, ignoradas: list<string>, nao_alocadas: list<array{disciplina_id: int, disciplina: string, quantidade: int, motivo: string}>, avisos: list<string>}
     */
    public function aplicar(Turma $turma, ?User $por = null): array
    {
        $criadas = 0;
        $ignoradas = [];

        $sugestao = DB::transaction(function () use ($turma, &$criadas, &$ignoradas): array {
            $sugestao = $this->sugerir($turma);

            foreach ($sugestao['propostas'] as $proposta) {
                $horario = new GradeHorario([
                    'turma_id' => $turma->id,
                    'disciplina_id' => $proposta['disciplina_id'],
                    'professor_id' => $proposta['professor_id'],
                    'dia_semana' => $proposta['dia_semana'],
                    'hora_inicio' => $proposta['hora_inicio'].':00',
                    'hora_fim' => $proposta['hora_fim'].':00',
                ]);

                $conflitos = $this->conflitos->conflitos($horario);

                if ($conflitos !== []) {
                    $ignoradas[] = "{$proposta['disciplina']} ({$proposta['hora_inicio']}): ".implode(' ', $conflitos);

                    continue;
                }

                $horario->save();
                $criadas++;
            }

            return $sugestao;
        });

        $log = activity('calendario')
            ->performedOn($turma)
            ->withProperties(['criadas' => $criadas, 'ignoradas' => count($ignoradas), 'nao_alocadas' => count($sugestao['nao_alocadas'])]);

        if ($por) {
            $log->causedBy($por);
        }

        $log->log('Sugestão da grade horária aplicada.');

        return ['criadas' => $criadas, 'ignoradas' => $ignoradas, 'nao_alocadas' => $sugestao['nao_alocadas'], 'avisos' => $sugestao['avisos']];
    }
}
