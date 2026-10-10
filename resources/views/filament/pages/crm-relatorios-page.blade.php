<x-filament-panels::page>
    {{-- Barra de Filtros no Topo --}}
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Período Rápido:</span>
                <button type="button" wire:click="definirPeriodo('mes_atual')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $periodoPredefinido === 'mes_atual' ? 'bg-primary-600 text-white font-bold' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                    Mês Atual
                </button>
                <button type="button" wire:click="definirPeriodo('ultimos_30')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $periodoPredefinido === 'ultimos_30' ? 'bg-primary-600 text-white font-bold' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                    Últimos 30 Dias
                </button>
                <button type="button" wire:click="definirPeriodo('ultimos_90')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $periodoPredefinido === 'ultimos_90' ? 'bg-primary-600 text-white font-bold' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                    Últimos 90 Dias
                </button>
                <button type="button" wire:click="definirPeriodo('ano_atual')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $periodoPredefinido === 'ano_atual' ? 'bg-primary-600 text-white font-bold' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                    Ano Atual
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">De:</label>
                    <input type="date" wire:model.live="dataInicio"
                        class="text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 py-1.5 px-2.5">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Até:</label>
                    <input type="date" wire:model.live="dataFim"
                        class="text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 py-1.5 px-2.5">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Consultor:</label>
                    <select wire:model.live="consultorId"
                        class="text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 py-1.5 px-2.5">
                        <option value="">Todos os Consultores</option>
                        @foreach ($consultores as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Cards Principais de KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total de Leads no Período</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">
                {{ number_format($funilEtapas['conversao_geral']['total_leads']) }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                <span class="text-success-600 font-bold">{{ $funilEtapas['conversao_geral']['ganhos'] }} matriculados</span> • 
                <span class="text-danger-600 font-medium">{{ $funilEtapas['conversao_geral']['perdidos'] }} perdidos</span>
            </div>
        </div>

        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Taxa de Conversão Geral</div>
            <div class="mt-2 text-3xl font-extrabold text-primary-600">
                {{ number_format($funilEtapas['conversao_geral']['taxa_conversao'], 1) }}%
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Ganhos sobre o total de leads criados
            </div>
        </div>

        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Receita Prevista (Ponderada)</div>
            <div class="mt-2 text-3xl font-extrabold text-emerald-600">
                R$ {{ number_format($previsaoReceita['total_previsto_ponderado'], 2, ',', '.') }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Carteira ativa: R$ {{ number_format($previsaoReceita['total_carteira'], 2, ',', '.') }}
            </div>
        </div>

        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Leads Ativos no Funil</div>
            <div class="mt-2 text-3xl font-extrabold text-indigo-600">
                {{ number_format($previsaoReceita['total_leads_ativos']) }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Oportunidades em aberto aguardando fechamento
            </div>
        </div>
    </div>

    {{-- Seção: Previsão de Receita por Etapa --}}
    <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span>💰</span> Previsão de Receita por Etapa do Funil
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Valores calculados com base no valor estimado informado em cada lead (ou ticket médio de tabela) e a probabilidade ponderada por etapa comparada à conversão histórica real.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="py-2.5 px-3 font-semibold">Etapa Ativa</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Leads Ativos</th>
                        <th class="py-2.5 px-3 font-semibold text-right">Valor em Carteira</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Probab. Configurada</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Histórico Real</th>
                        <th class="py-2.5 px-3 font-semibold text-right text-emerald-600 font-bold">Previsto Ponderado</th>
                        <th class="py-2.5 px-3 font-semibold text-right text-gray-500">Previsto Histórico</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($previsaoReceita['etapas'] as $etapa)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                            <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-200 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-{{ $etapa['cor'] }}-500 inline-block"></span>
                                {{ $etapa['status_nome'] }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-bold text-gray-800 dark:text-gray-200">
                                {{ $etapa['total_leads'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-gray-700 dark:text-gray-300">
                                R$ {{ number_format($etapa['valor_carteira'], 2, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-semibold text-primary-600">
                                {{ number_format($etapa['probabilidade_configurada'], 1) }}%
                            </td>
                            <td class="py-2.5 px-3 text-center text-gray-500">
                                {{ number_format($etapa['probabilidade_historica'], 1) }}%
                            </td>
                            <td class="py-2.5 px-3 text-right font-bold text-emerald-600">
                                R$ {{ number_format($etapa['valor_previsto_ponderado'], 2, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-gray-500">
                                R$ {{ number_format($etapa['valor_previsto_historico'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-gray-400">Nenhuma etapa ativa encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-gray-200 dark:border-gray-700 font-bold bg-gray-50/80 dark:bg-gray-800/40">
                    <tr>
                        <td class="py-3 px-3">Total Consolidado</td>
                        <td class="py-3 px-3 text-center">{{ $previsaoReceita['total_leads_ativos'] }}</td>
                        <td class="py-3 px-3 text-right">R$ {{ number_format($previsaoReceita['total_carteira'], 2, ',', '.') }}</td>
                        <td colspan="2"></td>
                        <td class="py-3 px-3 text-right text-emerald-600 font-extrabold">R$ {{ number_format($previsaoReceita['total_previsto_ponderado'], 2, ',', '.') }}</td>
                        <td class="py-3 px-3 text-right text-gray-500">R$ {{ number_format($previsaoReceita['total_previsto_historico'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Grid: Desempenho por Consultor & Eficiência do Funil --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Desempenho por Consultor --}}
        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>👥</span> Desempenho por Consultor
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Métricas de atendimento, agilidade de 1ª resposta e conversão por atendente.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="py-2 px-3 font-semibold">Consultor</th>
                            <th class="py-2 px-2 text-center font-semibold">Leads</th>
                            <th class="py-2 px-2 text-center font-semibold">1ª Resposta</th>
                            <th class="py-2 px-2 text-center font-semibold">Contatos</th>
                            <th class="py-2 px-2 text-center font-semibold">Visitas</th>
                            <th class="py-2 px-2 text-center font-semibold text-success-600">Matrículas</th>
                            <th class="py-2 px-2 text-center font-semibold text-primary-600">Conversão</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($desempenhoConsultores as $item)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-200">{{ $item['nome'] }}</td>
                                <td class="py-2.5 px-2 text-center font-bold">{{ $item['total_leads'] }}</td>
                                <td class="py-2.5 px-2 text-center text-gray-600 dark:text-gray-400">{{ $item['tempo_medio_primeira_resposta_horas'] }}h</td>
                                <td class="py-2.5 px-2 text-center text-gray-600 dark:text-gray-400">{{ $item['total_contatos'] }}</td>
                                <td class="py-2.5 px-2 text-center text-gray-600 dark:text-gray-400">{{ $item['total_visitas'] }}</td>
                                <td class="py-2.5 px-2 text-center font-bold text-success-600">{{ $item['ganhos'] }}</td>
                                <td class="py-2.5 px-2 text-center font-bold text-primary-600">{{ number_format($item['taxa_conversao'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-gray-400">Nenhum consultor com dados no período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Funil e Tempo Médio por Etapa --}}
        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>⏳</span> Eficiência e Conversão Etapa a Etapa
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Taxas de avanço entre etapas consecutivas e tempo médio de permanência.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="py-2 px-3 font-semibold">Etapa</th>
                            <th class="py-2 px-2 text-center font-semibold">Passaram</th>
                            <th class="py-2 px-2 text-center font-semibold">Avançaram</th>
                            <th class="py-2 px-2 text-center font-semibold text-primary-600">Taxa Avanço</th>
                            <th class="py-2 px-2 text-center font-semibold text-danger-600">Perdas</th>
                            <th class="py-2 px-3 text-right font-semibold">Tempo Médio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($funilEtapas['etapas'] as $etp)
                            @php
                                $tempo = $temposMedios->firstWhere('status_id', $etp['status_id']);
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-200">{{ $etp['status_nome'] }}</td>
                                <td class="py-2.5 px-2 text-center font-bold">{{ $etp['total_leads'] }}</td>
                                <td class="py-2.5 px-2 text-center">{{ $etp['avancaram'] }}</td>
                                <td class="py-2.5 px-2 text-center font-bold text-primary-600">{{ number_format($etp['taxa_conversao'], 1) }}%</td>
                                <td class="py-2.5 px-2 text-center text-danger-600 font-medium">{{ $etp['perdas'] }}</td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700 dark:text-gray-300">
                                    {{ $tempo ? $tempo['tempo_medio_dias'] . ' dias' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-400">Nenhum dado analítico no período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Seção: Motivos de Descarte & Concorrência --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Motivos de Perda Consolidados --}}
        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>📉</span> Motivos de Perda no Funil
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Agrupamento consolidado das justificativas de descarte informadas.</p>
            </div>

            <div class="space-y-3">
                @forelse ($motivosPerda['motivos'] as $item)
                    <div>
                        <div class="flex justify-between text-xs mb-1 font-medium">
                            <span class="text-gray-800 dark:text-gray-200">{{ $item['motivo'] }}</span>
                            <span class="text-gray-500 font-semibold">{{ $item['total'] }} leads ({{ $item['percentual'] }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
                            <div class="bg-danger-500 h-2 rounded-full" style="width: {{ min(100, $item['percentual']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-gray-400">Nenhuma perda registrada no período selecionado.</div>
                @endforelse
            </div>
        </div>

        {{-- Concorrência com Coluna Própria --}}
        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>🛡️</span> Inteligência Competitiva (Concorrência)
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Colégios para onde os candidatos migraram e os principais fatores determinantes.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="py-2.5 px-3 font-semibold">Colégio Concorrente</th>
                            <th class="py-2.5 px-3 text-center font-semibold">Perdas</th>
                            <th class="py-2.5 px-3 font-semibold">Fator Decisivo Principal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($motivosPerda['concorrentes'] as $conc)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-200 flex items-center gap-2">
                                    <span class="text-gray-400">🏫</span> {{ $conc['concorrente_nome'] }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold text-danger-600">{{ $conc['total_perdas'] }}</td>
                                <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ $conc['fator_decisivo_principal'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-400">Nenhuma perda atribuída à concorrência no período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>