<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            {{ $this->form }}
        </div>

        @php($mapa = $this->getMapa())

        @if(empty($filtros['sala_id']))
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Escolha um espaço para ver a ocupação.
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($mapa as $dia)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-3 text-sm font-bold text-gray-900 dark:text-gray-100">
                            {{ ucfirst($dia['data']->locale('pt_BR')->translatedFormat('l, d/m/Y')) }}
                        </h3>

                        @forelse($dia['blocos'] as $bloco)
                            <div class="mb-2 rounded-lg border px-3 py-2 text-xs {{ $bloco['tipo'] === 'reserva' ? 'border-primary-200 bg-primary-50 text-primary-900 dark:border-primary-900 dark:bg-primary-950/40 dark:text-primary-200' : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono font-semibold">{{ $bloco['inicio'] }}–{{ $bloco['fim'] }}</span>
                                    @if($bloco['status'])
                                        <x-filament::badge size="sm" :color="$bloco['status']->getColor()">
                                            {{ $bloco['status']->getLabel() }}
                                        </x-filament::badge>
                                    @endif
                                </div>
                                <p class="mt-1 font-semibold">{{ $bloco['titulo'] }}</p>
                                @if($bloco['detalhe'])
                                    <p class="opacity-80">{{ $bloco['detalhe'] }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-emerald-700 dark:text-emerald-400">Livre o dia todo.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
