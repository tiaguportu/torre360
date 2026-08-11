<x-filament-panels::page>
    @php
        $pendenciasPorDia = $this->getPendenciasAgrupadas();
    @endphp

    @if($pendenciasPorDia->isNotEmpty())
        <x-filament::section>
            {{-- GRID DE 3 COLUNAS (1/3 DA LARGURA CADA) --}}
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
                                    <x-filament::badge color="info">
                                        Hoje
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="warning">
                                        Atrasado
                                    </x-filament::badge>
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

                        {{-- CONTAINER DO BOTÃO DE AÇÃO DA CHAMADA DO DIA --}}
                        <div class="pt-4 mt-3 px-2 pb-2">
                            {{ ($this->lancarChamadaDiaAction)(['data' => $data]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @else
        <div class="p-12 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 space-y-3">
            <x-heroicon-o-check-circle class="w-16 h-16 text-emerald-500 mx-auto" />
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                Todas as chamadas estão em dia!
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Não há nenhuma aula com frequência pendente ou atrasada no momento.
            </p>
        </div>
    @endif

    {{-- RENDEREIZAÇÃO DOS MODAIS NATIVOS DE ACTION --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
