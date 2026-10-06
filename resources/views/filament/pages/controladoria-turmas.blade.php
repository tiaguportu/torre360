<x-filament-panels::page>
    @php($consolidado = $this->consolidado)

    {{-- Cards de Indicadores Globais no Topo --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {{-- Card 1: Alunos e Ocupação --}}
        <x-filament::section class="border-t-4 border-t-primary-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Alunos & Vagas
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($consolidado['total_alunos'], 0, ',', '.') }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            / {{ number_format($consolidado['total_vagas'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <div class="p-2 text-primary-600 bg-primary-50 dark:bg-primary-950/40 rounded-lg">
                    <x-filament::icon icon="heroicon-o-academic-cap" class="w-6 h-6" />
                </div>
            </div>
            <div class="mt-3 flex items-center text-xs text-gray-600 dark:text-gray-300">
                <span class="font-medium text-primary-600 dark:text-primary-400 mr-1">
                    {{ $consolidado['ocupacao_media'] }}%
                </span>
                taxa média de ocupação das salas
            </div>
        </x-filament::section>

        {{-- Card 2: Receita Líquida Mensal --}}
        <x-filament::section class="border-t-4 border-t-success-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Receita Líquida / Mês
                    </div>
                    <div class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">
                        R$ {{ number_format($consolidado['receita_liquida_total'], 2, ',', '.') }}
                    </div>
                </div>
                <div class="p-2 text-success-600 bg-success-50 dark:bg-success-950/40 rounded-lg">
                    <x-filament::icon icon="heroicon-o-banknotes" class="w-6 h-6" />
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Previsão mensal líquida de bolsas e descontos
            </div>
        </x-filament::section>

        {{-- Card 3: Custos Totais --}}
        <x-filament::section class="border-t-4 border-t-amber-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Custos Totais / Mês
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                        R$ {{ number_format($consolidado['custo_total_geral'], 2, ',', '.') }}
                    </div>
                </div>
                <div class="p-2 text-amber-600 bg-amber-50 dark:bg-amber-950/40 rounded-lg">
                    <x-filament::icon icon="heroicon-o-scale" class="w-6 h-6" />
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Docentes diretos + rateios operacionais
            </div>
        </x-filament::section>

        {{-- Card 4: Resultado & Margem --}}
        <x-filament::section class="border-t-4 {{ $consolidado['resultado_geral'] >= 0 ? 'border-t-emerald-500' : 'border-t-danger-500' }}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Resultado Consolidado
                    </div>
                    <div class="mt-1 text-2xl font-bold {{ $consolidado['resultado_geral'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $consolidado['resultado_geral'] >= 0 ? '+' : '' }}R$ {{ number_format($consolidado['resultado_geral'], 2, ',', '.') }}
                    </div>
                </div>
                <div class="p-2 {{ $consolidado['resultado_geral'] >= 0 ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40' : 'text-danger-600 bg-danger-50 dark:bg-danger-950/40' }} rounded-lg">
                    <x-filament::icon icon="{{ $consolidado['resultado_geral'] >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down' }}" class="w-6 h-6" />
                </div>
            </div>
            <div class="mt-3 text-xs font-semibold {{ $consolidado['resultado_geral'] >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-danger-700 dark:text-danger-300' }}">
                Margem média: {{ $consolidado['margem_geral'] }}%
            </div>
        </x-filament::section>
    </div>

    {{-- Banner de Diagnóstico de Turmas --}}
    <div class="flex flex-wrap items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl gap-3">
        <div class="flex items-center space-x-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            <x-filament::icon icon="heroicon-o-presentation-chart-line" class="w-5 h-5 text-primary-500" />
            <span>Diagnóstico do Período: <strong>{{ $consolidado['total_turmas'] }} turmas abertas</strong></span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-success-100 text-success-800 dark:bg-success-950/60 dark:text-success-300 border border-success-200 dark:border-success-800">
                🟢 {{ $consolidado['turmas_lucrativas'] }} Lucrativas
            </span>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                🟡 {{ $consolidado['turmas_alerta'] }} No Limite
            </span>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-danger-100 text-danger-800 dark:bg-danger-950/60 dark:text-danger-300 border border-danger-200 dark:border-danger-800">
                🔴 {{ $consolidado['turmas_deficitarias'] }} Deficitárias
            </span>
        </div>
    </div>

    {{-- Tabela de Turmas --}}
    <div class="mt-2">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
