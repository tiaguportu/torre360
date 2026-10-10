<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusTurma;
use App\Models\Interessado;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turma;
use Illuminate\Support\Collection;

class TermometroVagasService
{
    /**
     * Vagas padrão por turma caso o campo vagas_maximas não esteja definido ou seja 0.
     */
    public const VAGAS_PADRAO_TURMA = 25;

    /**
     * Níveis de escassez.
     */
    public const STATUS_ESGOTADO = 'esgotado';

    public const STATUS_CRITICO = 'critico';

    public const STATUS_ALERTA = 'alerta';

    public const STATUS_DISPONIVEL = 'disponivel';

    /**
     * Limites de classificação de escassez.
     */
    public const LIMITE_CRITICO_VAGAS = 3;

    public const LIMITE_CRITICO_PERCENTUAL = 90.0;

    public const LIMITE_ALERTA_VAGAS = 6;

    public const LIMITE_ALERTA_PERCENTUAL = 75.0;

    /**
     * Cache estático em memória por request para alta performance na renderização de listagens.
     */
    protected static ?Collection $cacheTodasSeries = null;

    public static function limparCache(): void
    {
        static::$cacheTodasSeries = null;
    }

    /**
     * Calcula as vagas e taxa de ocupação para uma série específica (retorna array) ou todas as séries (retorna Collection).
     *
     * @return Collection<int, array>|array
     */
    public static function calcularVagasPorSerie(?int $serieId = null): Collection|array
    {
        if ($serieId === null && static::$cacheTodasSeries !== null) {
            return static::$cacheTodasSeries;
        }

        $query = Serie::with(['curso', 'turmas' => function ($q) {
            // Só turmas que ainda aceitam matrícula (Planejada e Ativa); concluídas e canceladas não têm vaga.
            $q->abertasParaMatricula()->with(['turno', 'periodoLetivo'])->withCount(['matriculas as matriculas_ativas_count' => function ($mq) {
                $mq->where(function ($sub) {
                    $sub->whereIn('situacao', [
                        SituacaoMatricula::ATIVA->value,
                        SituacaoMatricula::PENDENTE->value,
                        SituacaoMatricula::RESERVA->value,
                    ])->orWhereNull('situacao');
                })->where(function ($sub) {
                    $sub->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>', now());
                });
            }]);
        }]);

        if ($serieId !== null) {
            $query->where('id', $serieId);
        }

        $series = $query->orderBy('nome')->get();

        $resultado = $series->map(function (Serie $serie) {
            [$turmas, $periodo] = self::turmasDoPeriodoDeCaptacao($serie->turmas);

            // Com turmas já planejadas para o mesmo período, são elas que recebem os novos leads:
            // somar as turmas em curso (cheias) inflaria a capacidade e esconderia a escassez.
            $planejadas = $turmas->filter(fn ($turma) => $turma->status === StatusTurma::Planejada);
            if ($planejadas->isNotEmpty()) {
                $turmas = $planejadas->values();
            }

            // Vagas sem turma cadastrada, ou turma sem `vagas_maximas`, usam o padrão (25): o número é uma
            // suposição, não um dado da escola, e não pode virar argumento de urgência para a família.
            $capacidadeEstimada = $turmas->isEmpty() || $turmas->contains(fn ($turma) => ! ($turma->vagas_maximas > 0));

            $capacidadeTotal = 0;
            $matriculasOcupadas = 0;
            $turmasDetalhes = [];

            foreach ($turmas as $turma) {
                $vagasTurma = $turma->vagas_maximas > 0 ? (int) $turma->vagas_maximas : self::VAGAS_PADRAO_TURMA;
                $ocupadasTurma = (int) ($turma->matriculas_ativas_count ?? 0);
                $restantesTurma = max(0, $vagasTurma - $ocupadasTurma);

                $capacidadeTotal += $vagasTurma;
                $matriculasOcupadas += $ocupadasTurma;

                $turmasDetalhes[] = [
                    'turma_id' => $turma->id,
                    'turma_nome' => $turma->nome ?? $turma->codigo ?? 'Turma '.$turma->id,
                    'turno' => $turma->turno?->nome ?? 'Padrão',
                    'capacidade' => $vagasTurma,
                    'ocupadas' => $ocupadasTurma,
                    'restantes' => $restantesTurma,
                ];
            }

            // Se não houver turmas cadastradas para a série, assume 1 turma padrão estimada
            if ($turmas->isEmpty()) {
                $capacidadeTotal = self::VAGAS_PADRAO_TURMA;
            }

            $vagasRestantes = max(0, $capacidadeTotal - $matriculasOcupadas);
            $taxaOcupacao = $capacidadeTotal > 0
                ? round(($matriculasOcupadas / $capacidadeTotal) * 100, 1)
                : 0.0;

            $escassez = self::classificarEscassez($vagasRestantes, $taxaOcupacao);

            return [
                'serie_id' => $serie->id,
                'serie_nome' => $serie->nome,
                'curso_nome' => $serie->curso?->nome_externo ?? $serie->curso?->nome_interno ?? $serie->curso?->nome ?? 'Geral',
                'total_turmas' => $turmas->count(),
                'periodo_letivo_id' => $periodo?->id,
                'periodo_letivo_nome' => $periodo?->nome,
                'capacidade_estimada' => $capacidadeEstimada,
                'capacidade_total' => $capacidadeTotal,
                'matriculas_ocupadas' => $matriculasOcupadas,
                'vagas_restantes' => $vagasRestantes,
                'taxa_ocupacao' => min(100.0, $taxaOcupacao),
                'nivel_escassez' => $escassez['status'],
                'label_escassez' => $escassez['label'],
                'badge_cor' => $escassez['cor'],
                'escassez' => $escassez,
                'turmas_detalhes' => $turmasDetalhes,
            ];
        });

        if ($serieId === null) {
            static::$cacheTodasSeries = $resultado;

            return $resultado;
        }

        return $resultado->first() ?? [];
    }

    /**
     * Turmas que recebem os novos leads de uma série: as do período letivo de captação. Misturar períodos
     * somava a turma do ano em curso (cheia) com a do próximo (aberta) e produzia uma escassez que não
     * existe para quem vai se matricular.
     *
     * Período de captação = o próximo a começar entre os que têm turma aberta; sem nenhum futuro, o que está
     * em curso (ou, se todos já terminaram, o mais recente). Turmas sem período ficam de fora quando a série
     * tem alguma com período.
     *
     * @param  Collection<int, Turma>  $turmas  já limitadas às abertas para matrícula
     * @return array{0: Collection<int, Turma>, 1: ?PeriodoLetivo}
     */
    private static function turmasDoPeriodoDeCaptacao(Collection $turmas): array
    {
        $comPeriodo = $turmas->filter(fn ($turma) => $turma->periodo_letivo_id !== null && $turma->periodoLetivo !== null);

        if ($comPeriodo->isEmpty()) {
            return [$turmas->values(), null];
        }

        $periodos = $comPeriodo->map(fn ($turma) => $turma->periodoLetivo)->unique('id')->values();
        $hoje = today();

        $escolhido = $periodos
            ->filter(fn ($periodo) => $periodo->data_inicio !== null && $periodo->data_inicio->gt($hoje))
            ->sortBy('data_inicio')
            ->first()
            ?? $periodos
                ->filter(fn ($periodo) => $periodo->data_inicio !== null && $periodo->data_inicio->lte($hoje) && ($periodo->data_fim === null || $periodo->data_fim->gte($hoje)))
                ->sortByDesc('data_inicio')
                ->first()
            ?? $periodos->sortByDesc('data_inicio')->first();

        return [$comPeriodo->where('periodo_letivo_id', $escolhido->id)->values(), $escolhido];
    }

    /**
     * Retorna a classificação de escassez com base nas vagas restantes e percentual.
     *
     * @return array{status: string, nivel: string, label: string, cor: string, 0: string, 1: string, 2: string}
     */
    public static function classificarEscassez(int $vagasRestantes, float $taxaOcupacao): array
    {
        if ($vagasRestantes === 0) {
            return [
                'status' => self::STATUS_ESGOTADO,
                'nivel' => self::STATUS_ESGOTADO,
                'label' => 'Esgotado (0 vagas)',
                'cor' => 'danger',
                0 => self::STATUS_ESGOTADO,
                1 => 'Esgotado (0 vagas)',
                2 => 'danger',
            ];
        }

        if ($vagasRestantes <= self::LIMITE_CRITICO_VAGAS || $taxaOcupacao >= self::LIMITE_CRITICO_PERCENTUAL) {
            return [
                'status' => self::STATUS_CRITICO,
                'nivel' => self::STATUS_CRITICO,
                'label' => "Últimas {$vagasRestantes} vagas!",
                'cor' => 'danger',
                0 => self::STATUS_CRITICO,
                1 => "Últimas {$vagasRestantes} vagas!",
                2 => 'danger',
            ];
        }

        if ($vagasRestantes <= self::LIMITE_ALERTA_VAGAS || $taxaOcupacao >= self::LIMITE_ALERTA_PERCENTUAL) {
            return [
                'status' => self::STATUS_ALERTA,
                'nivel' => self::STATUS_ALERTA,
                'label' => "Vagas Limitadas ({$vagasRestantes} restantes)",
                'cor' => 'warning',
                0 => self::STATUS_ALERTA,
                1 => "Vagas Limitadas ({$vagasRestantes} restantes)",
                2 => 'warning',
            ];
        }

        return [
            'status' => self::STATUS_DISPONIVEL,
            'nivel' => self::STATUS_DISPONIVEL,
            'label' => "Disponível ({$vagasRestantes} vagas)",
            'cor' => 'success',
            0 => self::STATUS_DISPONIVEL,
            1 => "Disponível ({$vagasRestantes} vagas)",
            2 => 'success',
        ];
    }

    /**
     * Obtém o diagnóstico de vagas para as séries de interesse dos dependentes de um lead.
     *
     * @return array{
     *     tem_escassez: bool,
     *     nivel_mais_critico: string,
     *     badge_cor: string,
     *     texto_destaque: string,
     *     series: array
     * }
     */
    public static function obterStatusParaLead(Interessado $interessado): array
    {
        $seriesIds = $interessado->dependentes->pluck('serie_id')->filter()->unique()->values();

        if ($seriesIds->isEmpty()) {
            return [
                'tem_escassez' => false,
                'nivel_mais_critico' => 'disponivel',
                'badge_cor' => 'gray',
                'texto_destaque' => 'Série não definida',
                'series' => [],
            ];
        }

        /** @var Collection $seriesDados */
        $seriesDados = self::calcularVagasPorSerie();
        $filtradas = $seriesDados->whereIn('serie_id', $seriesIds)->values();

        if ($filtradas->isEmpty()) {
            return [
                'tem_escassez' => false,
                'nivel_mais_critico' => 'disponivel',
                'badge_cor' => 'gray',
                'texto_destaque' => 'Série sem turmas',
                'series' => [],
            ];
        }

        // Séries com capacidade estimada (sem turma ou sem `vagas_maximas`) não sinalizam escassez: o número é suposição.
        $confiaveis = $filtradas->reject(fn (array $serie): bool => $serie['capacidade_estimada']);

        if ($confiaveis->isEmpty()) {
            return [
                'tem_escassez' => false,
                'nivel_mais_critico' => 'disponivel',
                'badge_cor' => 'gray',
                'texto_destaque' => 'Capacidade não definida',
                'series' => $filtradas->all(),
            ];
        }

        $temEsgotado = $confiaveis->contains('nivel_escassez', 'esgotado');
        $temCritico = $confiaveis->contains('nivel_escassez', 'critico');
        $temAlerta = $confiaveis->contains('nivel_escassez', 'alerta');

        $nivelMaisCritico = match (true) {
            $temEsgotado => 'esgotado',
            $temCritico => 'critico',
            $temAlerta => 'alerta',
            default => 'disponivel',
        };

        $badgeCor = match ($nivelMaisCritico) {
            'esgotado', 'critico' => 'danger',
            'alerta' => 'warning',
            default => 'success',
        };

        // Monta texto de destaque para exibição em cards e colunas
        $primeiraSerie = $confiaveis->sortByDesc(fn ($s) => $s['taxa_ocupacao'])->first();
        $icone = match ($nivelMaisCritico) {
            'esgotado' => '⛔',
            'critico' => '🔥',
            'alerta' => '🟡',
            default => '🟢',
        };

        $textoDestaque = "{$icone} {$primeiraSerie['vagas_restantes']} vagas ({$primeiraSerie['serie_nome']})";

        return [
            'tem_escassez' => in_array($nivelMaisCritico, ['esgotado', 'critico', 'alerta'], true),
            'nivel_mais_critico' => $nivelMaisCritico,
            'badge_cor' => $badgeCor,
            'texto_destaque' => $textoDestaque,
            'series' => $filtradas->all(),
        ];
    }

    /**
     * Gera um bloco de texto com dados reais de vagas para enriquecer o contexto de IA do Copiloto e Dossiê.
     *
     * Só entram séries com capacidade conhecida (todas as turmas com `vagas_maximas` definido) e do período
     * letivo de captação. Sem isso o modelo recebia "Restam apenas 25 vagas" de uma suposição, ou a ocupação
     * de uma turma do ano em curso, e escrevia mensagens com urgência falsa para uma família.
     */
    public static function gerarPromptEscassez(Interessado $interessado): ?string
    {
        $status = self::obterStatusParaLead($interessado);

        $series = collect($status['series'])->reject(fn (array $serie): bool => $serie['capacidade_estimada']);

        if ($series->isEmpty()) {
            return null;
        }

        $linhas = $series->map(function (array $serie): string {
            $periodo = $serie['periodo_letivo_nome'] ? " (turmas de {$serie['periodo_letivo_nome']})" : '';
            $restantes = in_array($serie['nivel_escassez'], [self::STATUS_ESGOTADO, self::STATUS_CRITICO, self::STATUS_ALERTA], true)
                ? ($serie['vagas_restantes'] === 0 ? 'Não há vagas restantes.' : "Restam apenas {$serie['vagas_restantes']} vagas.")
                : "{$serie['vagas_restantes']} vagas disponíveis.";

            return "- {$serie['serie_nome']}{$periodo}: {$serie['matriculas_ocupadas']}/{$serie['capacidade_total']} vagas ocupadas ({$serie['taxa_ocupacao']}% de ocupação) — {$restantes}";
        })->implode("\n");

        $posicao = 'Posição em '.now()->format('d/m/Y').'.';

        if ($status['tem_escassez']) {
            return "⚠️ URGÊNCIA REAL DE VAGAS NA ESCOLA ({$posicao} Cite só se fizer sentido na conversa, usando apenas estes números):\n{$linhas}\nAtenção: As turmas pretendidas estão com alta procura e poucas vagas restantes. Se mencionar, faça com elegância e empatia para ajudar os pais a decidirem a visita ou a vaga; não pressione, não invente prazo, desconto nem outro número de vagas.";
        }

        return "📊 DISPONIBILIDADE DE VAGAS ({$posicao}):\n{$linhas}\nNão há escassez: não use argumento de urgência.";
    }
}
