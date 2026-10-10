<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Interessado;
use App\Models\InteressadoStatusHistorico;
use App\Models\StatusInteressado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Análise de desempenho do funil de captação e vendas:
 *  - Tempo médio de permanência por etapa do funil.
 *  - Conversão etapa a etapa (taxa de avanço e perdas).
 *  - Conversão geral (ganhos vs total).
 *  - Métricas agrupadas por consultor, origem, campanha e período.
 */
class FunilAnaliticoService
{
    /**
     * Calcula o tempo médio (em dias e horas) que os leads passam em cada etapa.
     * Considera as transições concluídas (onde o lead entrou na etapa e posteriormente avançou ou foi perdido).
     *
     * @param  array{data_inicio?: string|Carbon, data_fim?: string|Carbon, usuario_id?: int, origem_interessado_id?: int, campanha_marketing_id?: int, incluir_em_andamento?: bool}  $filtros
     * @return Collection<int, array{status_id: int, status_nome: string, cor: string, ordem: int, total_transicoes: int, tempo_medio_dias: float, tempo_medio_horas: float}>
     */
    public function tempoMedioPorEtapa(array $filtros = []): Collection
    {
        $statusList = StatusInteressado::orderBy('ordem')->get();
        $incluirEmAndamento = (bool) ($filtros['incluir_em_andamento'] ?? false);

        $transicoesQuery = InteressadoStatusHistorico::with('interessado')
            ->whereHas('interessado', function (Builder $query) use ($filtros) {
                $this->aplicarFiltrosLead($query, $filtros);
            })
            ->orderBy('interessado_id')
            ->orderBy('data_transicao')
            ->orderBy('id');

        if (filled($filtros['data_inicio'] ?? null)) {
            $transicoesQuery->where('data_transicao', '>=', Carbon::parse($filtros['data_inicio'])->startOfDay());
        }

        if (filled($filtros['data_fim'] ?? null)) {
            $transicoesQuery->where('data_transicao', '<=', Carbon::parse($filtros['data_fim'])->endOfDay());
        }

        $transicoes = $transicoesQuery->get()->groupBy('interessado_id');

        $duracoesPorStatus = [];
        foreach ($statusList as $status) {
            $duracoesPorStatus[$status->id] = [
                'segundos' => 0,
                'contagem' => 0,
            ];
        }

        foreach ($transicoes as $leadId => $listaTransicoes) {
            $transicoesArray = $listaTransicoes->values();
            $total = $transicoesArray->count();

            for ($i = 0; $i < $total; $i++) {
                $transicaoAtual = $transicoesArray[$i];
                $statusId = $transicaoAtual->status_novo_id;

                if (! isset($duracoesPorStatus[$statusId])) {
                    continue;
                }

                if ($i + 1 < $total) {
                    $proximaTransicao = $transicoesArray[$i + 1];
                    $d1 = Carbon::parse($transicaoAtual->data_transicao);
                    $d2 = Carbon::parse($proximaTransicao->data_transicao);
                    $segundos = abs($d2->getTimestamp() - $d1->getTimestamp());
                    $duracoesPorStatus[$statusId]['segundos'] += $segundos;
                    $duracoesPorStatus[$statusId]['contagem']++;
                } elseif ($incluirEmAndamento) {
                    $statusAtual = $statusList->firstWhere('id', $statusId);
                    if ($statusAtual && ! $statusAtual->is_final && ! $statusAtual->is_ganho) {
                        $segundos = max(0, now()->diffInSeconds($transicaoAtual->data_transicao));
                        $duracoesPorStatus[$statusId]['segundos'] += $segundos;
                        $duracoesPorStatus[$statusId]['contagem']++;
                    }
                }
            }
        }

        return $statusList->map(function (StatusInteressado $status) use ($duracoesPorStatus) {
            $dados = $duracoesPorStatus[$status->id] ?? ['segundos' => 0, 'contagem' => 0];
            $contagem = $dados['contagem'];
            $mediaSegundos = $contagem > 0 ? ($dados['segundos'] / $contagem) : 0;

            return [
                'status_id' => $status->id,
                'status_nome' => $status->nome,
                'cor' => $status->cor,
                'ordem' => $status->ordem,
                'total_transicoes' => $contagem,
                'tempo_medio_dias' => round($mediaSegundos / 86400, 1),
                'tempo_medio_horas' => round($mediaSegundos / 3600, 1),
            ];
        });
    }

    /**
     * Calcula a taxa de conversão etapa a etapa entre as etapas ativas do funil.
     *
     * @param  array{data_inicio?: string|Carbon, data_fim?: string|Carbon, usuario_id?: int, origem_interessado_id?: int, campanha_marketing_id?: int}  $filtros
     * @return array{etapas: array<int, array{status_id: int, status_nome: string, cor: string, ordem: int, total_leads: int, avancaram: int, taxa_conversao: float, perdas: int, taxa_perda: float}>, conversao_geral: array{total_leads: int, ganhos: int, perdidos: int, em_andamento: int, taxa_conversao: float}}
     */
    public function conversaoEtapaAEtapa(array $filtros = []): array
    {
        $statusAtivos = StatusInteressado::where('is_final', false)
            ->where('is_ganho', false)
            ->orderBy('ordem')
            ->get();

        $statusGanho = StatusInteressado::ganho();

        $leadsQuery = Interessado::query();
        $this->aplicarFiltrosLead($leadsQuery, $filtros);

        if (filled($filtros['data_inicio'] ?? null)) {
            $leadsQuery->where('created_at', '>=', Carbon::parse($filtros['data_inicio'])->startOfDay());
        }

        if (filled($filtros['data_fim'] ?? null)) {
            $leadsQuery->where('created_at', '<=', Carbon::parse($filtros['data_fim'])->endOfDay());
        }

        $leadIds = $leadsQuery->pluck('id');
        $totalLeads = $leadIds->count();

        $historicos = InteressadoStatusHistorico::whereIn('interessado_id', $leadIds)
            ->orderBy('data_transicao')
            ->orderBy('id')
            ->get()
            ->groupBy('interessado_id');

        $etapasResultado = [];
        $etapasArray = $statusAtivos->values();

        for ($idx = 0; $idx < $etapasArray->count(); $idx++) {
            $etapaAtual = $etapasArray[$idx];
            $proximaEtapa = $etapasArray[$idx + 1] ?? null;

            $totalPassaram = 0;
            $avancaram = 0;
            $perdas = 0;

            foreach ($leadIds as $id) {
                $transicoesLead = $historicos->get($id, collect());
                $passouPorEtapa = $transicoesLead->contains(fn ($t) => $t->status_novo_id === $etapaAtual->id);

                if (! $passouPorEtapa) {
                    continue;
                }

                $totalPassaram++;

                $avancouParaProxima = false;
                $perdeuNestaEtapa = false;

                $viuEntrada = false;
                foreach ($transicoesLead as $t) {
                    if ($t->status_novo_id === $etapaAtual->id) {
                        $viuEntrada = true;

                        continue;
                    }

                    if ($viuEntrada) {
                        if ($proximaEtapa && $t->status_novo_id === $proximaEtapa->id) {
                            $avancouParaProxima = true;
                            break;
                        }

                        if ($statusGanho && $t->status_novo_id === $statusGanho->id) {
                            $avancouParaProxima = true;
                            break;
                        }

                        if (filled($t->motivo_perda) || StatusInteressado::find($t->status_novo_id)?->isPerda()) {
                            $perdeuNestaEtapa = true;
                            break;
                        }
                    }
                }

                if ($avancouParaProxima) {
                    $avancaram++;
                }

                if ($perdeuNestaEtapa) {
                    $perdas++;
                }
            }

            $taxaConversao = $totalPassaram > 0 ? round(($avancaram / $totalPassaram) * 100, 1) : 0.0;
            $taxaPerda = $totalPassaram > 0 ? round(($perdas / $totalPassaram) * 100, 1) : 0.0;

            $etapasResultado[] = [
                'status_id' => $etapaAtual->id,
                'status_nome' => $etapaAtual->nome,
                'cor' => $etapaAtual->cor,
                'ordem' => $etapaAtual->ordem,
                'total_leads' => $totalPassaram,
                'avancaram' => $avancaram,
                'taxa_conversao' => $taxaConversao,
                'perdas' => $perdas,
                'taxa_perda' => $taxaPerda,
            ];
        }

        $ganhos = Interessado::whereIn('id', $leadIds)->ganhos()->count();
        $perdidos = Interessado::whereIn('id', $leadIds)->perdidos()->count();
        $emAndamento = Interessado::whereIn('id', $leadIds)->ativos()->count();
        $taxaGeral = $totalLeads > 0 ? round(($ganhos / $totalLeads) * 100, 1) : 0.0;

        return [
            'etapas' => $etapasResultado,
            'conversao_geral' => [
                'total_leads' => $totalLeads,
                'ganhos' => $ganhos,
                'perdidos' => $perdidos,
                'em_andamento' => $emAndamento,
                'taxa_conversao' => $taxaGeral,
            ],
        ];
    }

    /**
     * Relatório consolidado por consultor, origem ou campanha.
     *
     * @param  'consultor'|'origem'|'campanha'  $dimensao
     * @param  array{data_inicio?: string|Carbon, data_fim?: string|Carbon, usuario_id?: int, origem_interessado_id?: int, campanha_marketing_id?: int}  $filtros
     * @return Collection<int, array{id: ?int, nome: string, total_leads: int, ganhos: int, perdidos: int, em_andamento: int, taxa_conversao: float}>
     */
    public function conversaoPorDimensao(string $dimensao, array $filtros = []): Collection
    {
        $coluna = match ($dimensao) {
            'consultor' => 'usuario_id',
            'origem' => 'origem_interessado_id',
            'campanha' => 'campanha_marketing_id',
            default => 'usuario_id',
        };

        $query = Interessado::query();
        $this->aplicarFiltrosLead($query, $filtros);

        if (filled($filtros['data_inicio'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($filtros['data_inicio'])->startOfDay());
        }

        if (filled($filtros['data_fim'] ?? null)) {
            $query->where('created_at', '<=', Carbon::parse($filtros['data_fim'])->endOfDay());
        }

        $leads = $query->with(['status', 'usuario', 'origem', 'campanha'])->get();

        $agrupados = $leads->groupBy($coluna);

        return $agrupados->map(function (Collection $grupo, $id) use ($dimensao) {
            $primeiro = $grupo->first();
            $nome = match ($dimensao) {
                'consultor' => $primeiro?->usuario?->name ?? 'Sem consultor',
                'origem' => $primeiro?->origem?->nome ?? 'Não informada',
                'campanha' => $primeiro?->campanha?->nome ?? 'Sem campanha',
                default => 'Outro',
            };

            $total = $grupo->count();
            $ganhos = $grupo->filter(fn ($l) => $l->status?->is_ganho)->count();
            $perdidos = $grupo->filter(fn ($l) => $l->status?->isPerda())->count();
            $emAndamento = $total - $ganhos - $perdidos;
            $taxa = $total > 0 ? round(($ganhos / $total) * 100, 1) : 0.0;

            return [
                'id' => $id ? (int) $id : null,
                'nome' => $nome,
                'total_leads' => $total,
                'ganhos' => $ganhos,
                'perdidos' => $perdidos,
                'em_andamento' => $emAndamento,
                'taxa_conversao' => $taxa,
            ];
        })->values()->sortByDesc('total_leads')->values();
    }

    private function aplicarFiltrosLead(Builder $query, array $filtros): void
    {
        if (filled($filtros['usuario_id'] ?? null)) {
            $query->where('usuario_id', $filtros['usuario_id']);
        }

        if (filled($filtros['origem_interessado_id'] ?? null)) {
            $query->where('origem_interessado_id', $filtros['origem_interessado_id']);
        }

        if (filled($filtros['campanha_marketing_id'] ?? null)) {
            $query->where('campanha_marketing_id', $filtros['campanha_marketing_id']);
        }
    }
}
