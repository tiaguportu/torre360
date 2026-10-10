<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusVisitaInteressado;
use App\Models\Concorrente;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Serviço de inteligência gerencial e relatórios do CRM (Lote D2):
 *  - Motivos de perda e concorrência (agregável por motivo base e concorrente em coluna própria).
 *  - Desempenho por consultor (leads, 1ª resposta, contatos, visitas, conversão).
 *  - Previsão de receita ponderada baseada no valor estimado dos leads e probabilidade por etapa.
 */
class CrmRelatoriosService
{
    public function __construct(
        protected FunilAnaliticoService $funilAnalitico
    ) {}

    /**
     * Agrega as perdas do período por motivo base e lista os colégios concorrentes citados.
     *
     * @param  array{data_inicio?: string|Carbon, data_fim?: string|Carbon, usuario_id?: int}  $filtros
     * @return array{motivos: Collection<int, array{motivo: string, total: int, percentual: float}>, concorrentes: Collection<int, array{concorrente_id: ?int, concorrente_nome: string, total_perdas: int, fator_decisivo_principal: ?string}>, total_perdas: int}
     */
    public function motivosPerda(array $filtros = []): array
    {
        $query = Interessado::perdidos()->with(['concorrente']);
        $this->aplicarFiltros($query, $filtros);

        $leadsPerdidos = $query->get();
        $totalPerdas = $leadsPerdidos->count();

        // 1. Agrupamento por motivo base
        $motivosAgrupados = $leadsPerdidos->groupBy(function (Interessado $lead) {
            return LeadFunilService::motivoBase($lead->motivo_perda) ?? 'Outro';
        })->map(function (Collection $grupo, string $motivo) use ($totalPerdas) {
            $total = $grupo->count();
            $percentual = $totalPerdas > 0 ? round(($total / $totalPerdas) * 100, 1) : 0.0;

            return [
                'motivo' => $motivo,
                'total' => $total,
                'percentual' => $percentual,
            ];
        })->values()->sortByDesc('total')->values();

        // 2. Agrupamento de concorrência com coluna própria
        $perdasConcorrencia = $leadsPerdidos->filter(function (Interessado $lead) {
            return filled($lead->concorrente_id) || str_starts_with((string) $lead->motivo_perda, LeadFunilService::MOTIVO_CONCORRENCIA);
        });

        $concorrentesAgrupados = $perdasConcorrencia->groupBy(function (Interessado $lead) {
            if ($lead->concorrente) {
                return $lead->concorrente->nome;
            }

            // Extrai nome após os dois pontos se não houver FK
            if (str_contains((string) $lead->motivo_perda, ':')) {
                return trim(explode(':', (string) $lead->motivo_perda, 2)[1]);
            }

            return 'Outra Escola';
        })->map(function (Collection $grupo, string $nomeConcorrente) {
            $primeiro = $grupo->first();
            // Fator decisivo mais comum
            $fatorPrincipal = $grupo->whereNotNull('fator_decisivo_concorrente')
                ->groupBy('fator_decisivo_concorrente')
                ->sortByDesc->count()
                ->keys()
                ->first();

            return [
                'concorrente_id' => $primeiro?->concorrente_id,
                'concorrente_nome' => $nomeConcorrente,
                'total_perdas' => $grupo->count(),
                'fator_decisivo_principal' => $fatorPrincipal ?? 'Não informado',
            ];
        })->values()->sortByDesc('total_perdas')->values();

        return [
            'motivos' => $motivosAgrupados,
            'concorrentes' => $concorrentesAgrupados,
            'total_perdas' => $totalPerdas,
        ];
    }

    /**
     * Apura o desempenho de cada consultor ativo.
     *
     * @param  array{data_inicio?: string|Carbon, data_fim?: string|Carbon}  $filtros
     * @return Collection<int, array{id: int, nome: string, email: string, total_leads: int, tempo_medio_primeira_resposta_horas: float, total_contatos: int, total_visitas: int, ganhos: int, perdidos: int, taxa_conversao: float}>
     */
    public function desempenhoConsultores(array $filtros = []): Collection
    {
        $consultores = User::consultoresCrm()->get();

        $dataInicio = filled($filtros['data_inicio'] ?? null) ? Carbon::parse($filtros['data_inicio'])->startOfDay() : null;
        $dataFim = filled($filtros['data_fim'] ?? null) ? Carbon::parse($filtros['data_fim'])->endOfDay() : null;

        return $consultores->map(function (User $consultor) use ($dataInicio, $dataFim) {
            // Leads atribuídos ao consultor
            $leadsQuery = Interessado::where('usuario_id', $consultor->id);

            if ($dataInicio) {
                $leadsQuery->where('created_at', '>=', $dataInicio);
            }
            if ($dataFim) {
                $leadsQuery->where('created_at', '<=', $dataFim);
            }

            $leads = $leadsQuery->with(['status'])->get();
            $totalLeads = $leads->count();

            // SLA de 1ª resposta: tempo entre cadastro e primeiro atendimento
            $leadsComResposta = $leads->filter(fn ($l) => filled($l->data_primeiro_contato) && filled($l->created_at));
            $horasRespostas = $leadsComResposta->map(function ($l) {
                return abs(Carbon::parse($l->data_primeiro_contato)->diffInHours(Carbon::parse($l->created_at)));
            });
            $mediaHorasResposta = $horasRespostas->isNotEmpty() ? round($horasRespostas->avg(), 1) : 0.0;

            // Total de contatos humanos registrados
            $contatosQuery = HistoricoContato::where('usuario_id', $consultor->id)
                ->where('automatico', false);
            if ($dataInicio) {
                $contatosQuery->where('data_contato', '>=', $dataInicio);
            }
            if ($dataFim) {
                $contatosQuery->where('data_contato', '<=', $dataFim);
            }
            $totalContatos = $contatosQuery->count();

            // Total de visitas realizadas
            $visitasQuery = VisitaInteressado::where('usuario_id', $consultor->id)
                ->where('status', StatusVisitaInteressado::Realizada);
            if ($dataInicio) {
                $visitasQuery->where('data_hora', '>=', $dataInicio);
            }
            if ($dataFim) {
                $visitasQuery->where('data_hora', '<=', $dataFim);
            }
            $totalVisitas = $visitasQuery->count();

            $ganhos = $leads->filter(fn ($l) => $l->status?->is_ganho)->count();
            $perdidos = $leads->filter(fn ($l) => $l->status?->isPerda())->count();
            $taxaConversao = $totalLeads > 0 ? round(($ganhos / $totalLeads) * 100, 1) : 0.0;

            return [
                'id' => $consultor->id,
                'nome' => $consultor->name,
                'email' => $consultor->email,
                'total_leads' => $totalLeads,
                'tempo_medio_primeira_resposta_horas' => (float) $mediaHorasResposta,
                'total_contatos' => $totalContatos,
                'total_visitas' => $totalVisitas,
                'ganhos' => $ganhos,
                'perdidos' => $perdidos,
                'taxa_conversao' => $taxaConversao,
            ];
        })->sortByDesc('total_leads')->values();
    }

    /**
     * Calcula a previsão de receita ponderada para os leads ativos em cada etapa.
     *
     * @param  array{usuario_id?: int}  $filtros
     * @return array{etapas: Collection<int, array{status_id: int, status_nome: string, cor: string, ordem: int, total_leads: int, valor_carteira: float, probabilidade_configurada: float, probabilidade_historica: float, valor_previsto_ponderado: float, valor_previsto_historico: float}>, total_carteira: float, total_previsto_ponderado: float, total_previsto_historico: float, total_leads_ativos: int}
     */
    public function previsaoReceita(array $filtros = []): array
    {
        $ticketMedioPadrao = (float) config('crm.previsao_receita.ticket_medio_padrao', 1500.0);
        $probabilidadePadrao = (float) config('crm.previsao_receita.probabilidade_padrao', 20.0);
        $probabilidadesConfiguradas = (array) config('crm.previsao_receita.probabilidades_etapa', []);

        $statusAtivos = StatusInteressado::where('is_final', false)
            ->where('is_ganho', false)
            ->orderBy('ordem')
            ->get();

        // Obtém a conversão histórica real de cada etapa
        $funilHistorico = $this->funilAnalitico->conversaoEtapaAEtapa();
        $historicoPorStatus = collect($funilHistorico['etapas'])->keyBy('status_id');

        $totalCarteiraGeral = 0.0;
        $totalPonderadoGeral = 0.0;
        $totalHistoricoGeral = 0.0;
        $totalLeadsGeral = 0;

        $etapasResultado = $statusAtivos->map(function (StatusInteressado $status) use (
            $filtros,
            $ticketMedioPadrao,
            $probabilidadePadrao,
            $probabilidadesConfiguradas,
            $historicoPorStatus,
            &$totalCarteiraGeral,
            &$totalPonderadoGeral,
            &$totalHistoricoGeral,
            &$totalLeadsGeral
        ) {
            $leadsQuery = Interessado::where('status_interessado_id', $status->id);
            if (filled($filtros['usuario_id'] ?? null)) {
                $leadsQuery->where('usuario_id', $filtros['usuario_id']);
            }

            $leads = $leadsQuery->get(['id', 'valor_estimado']);
            $totalLeads = $leads->count();

            // Soma dos valores dos leads (valor_estimado informado ou ticket médio configurado)
            $valorCarteira = (float) $leads->sum(function (Interessado $lead) use ($ticketMedioPadrao) {
                return (float) ($lead->valor_estimado ?? $ticketMedioPadrao);
            });

            // Probabilidade configurada
            $probConfigurada = (float) ($probabilidadesConfiguradas[$status->nome] ?? $probabilidadePadrao);

            // Probabilidade histórica real
            $probHistorica = (float) ($historicoPorStatus->get($status->id)['taxa_conversao'] ?? 0.0);

            $valorPonderado = round($valorCarteira * ($probConfigurada / 100), 2);
            $valorHistorico = round($valorCarteira * ($probHistorica / 100), 2);

            $totalCarteiraGeral += $valorCarteira;
            $totalPonderadoGeral += $valorPonderado;
            $totalHistoricoGeral += $valorHistorico;
            $totalLeadsGeral += $totalLeads;

            return [
                'status_id' => $status->id,
                'status_nome' => $status->nome,
                'cor' => $status->cor,
                'ordem' => $status->ordem,
                'total_leads' => $totalLeads,
                'valor_carteira' => $valorCarteira,
                'probabilidade_configurada' => $probConfigurada,
                'probabilidade_historica' => $probHistorica,
                'valor_previsto_ponderado' => $valorPonderado,
                'valor_previsto_historico' => $valorHistorico,
            ];
        });

        return [
            'etapas' => $etapasResultado,
            'total_carteira' => round($totalCarteiraGeral, 2),
            'total_previsto_ponderado' => round($totalPonderadoGeral, 2),
            'total_previsto_historico' => round($totalHistoricoGeral, 2),
            'total_leads_ativos' => $totalLeadsGeral,
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (filled($filtros['usuario_id'] ?? null)) {
            $query->where('usuario_id', $filtros['usuario_id']);
        }

        if (filled($filtros['data_inicio'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($filtros['data_inicio'])->startOfDay());
        }

        if (filled($filtros['data_fim'] ?? null)) {
            $query->where('created_at', '<=', Carbon::parse($filtros['data_fim'])->endOfDay());
        }
    }
}
