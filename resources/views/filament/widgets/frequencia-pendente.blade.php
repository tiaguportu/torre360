<x-filament-widgets::widget>
    @php
        $pendenciasPorDia = $this->getPendenciasAgrupadas();
    @endphp

    @if($pendenciasPorDia->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center space-x-2">
                        <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-500 animate-pulse" />
                        <span class="text-lg font-bold text-gray-900 dark:text-white">
                            Pendências de Chamada
                        </span>
                        <x-filament::badge color="warning">
                            Últimos {{ $pendenciasPorDia->count() }} {{ $pendenciasPorDia->count() === 1 ? 'dia' : 'dias' }}
                        </x-filament::badge>
                    </div>

                    <a
                        href="{{ \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                    >
                        <span>Ver mais</span>
                        <x-heroicon-o-arrow-right-circle class="w-4 h-4" />
                    </a>
                </div>
            </x-slot>

            <x-slot name="headerActions">
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('index') }}"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-arrow-right-circle"
                >
                    Ver mais
                </x-filament::button>
            </x-slot>

            {{-- GRID DE 3 COLUNAS (1/3 DA LARGURA CADA) NO PADRÃO FILAMENT --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($pendenciasPorDia as $data => $cronogramas)
                    @php
                        $carbonData = \Illuminate\Support\Carbon::parse($data);
                        $isHoje = $carbonData->isToday();
                        $dataDisplay = $carbonData->format('d/m/Y');
                        $diaSemana = ucfirst($carbonData->translatedFormat('l'));
                    @endphp

                    <div class="p-6 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm transition hover:shadow-md flex flex-col justify-between">
                        <div class="space-y-2 p-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    {{ $diaSemana }}
                                </span>
                                @if($isHoje)
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">
                                        Hoje
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                        Atrasado
                                    </span>
                                @endif
                            </div>

                            <div>
                                <h4 class="text-xl font-extrabold text-gray-900 dark:text-white">
                                    {{ $dataDisplay }}
                                </h4>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $cronogramas->count() }} {{ $cronogramas->count() === 1 ? 'aula pendente' : 'aulas pendentes' }}
                                </p>
                            </div>
                        </div>

                        {{-- CONTAINER DO BOTÃO COM MARGEM DE SEGURANÇA INTERNA E SUPERIOR PARA AFASTAR DAS BORDAS --}}
                        <div class="pt-4 mt-3 px-2 pb-2">
                            {{ ($this->lancarChamadaDiaAction)(['data' => $data]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- RENDEREIZAÇÃO DOS MODAIS NATIVOS DE ACTION DO FILAMENT COM Z-INDEX OFICIAL --}}
    <x-filament-actions::modals />
</x-filament-widgets::widget>
