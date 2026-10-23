<x-filament-panels::page>
    @php
        $filas = $this->getFilas();
        $comItens = $filas->filter(fn ($fila) => $fila->temItens());
        $emDia = $filas->reject(fn ($fila) => $fila->temItens() || $fila->erro);
        $comErro = $filas->filter(fn ($fila) => $fila->erro);
        $porArea = $comItens->groupBy(fn ($fila) => $fila->fonte->area());
        $totalAtrasados = $comItens->sum('atrasados');
        $total = $comItens->sum('total');
    @endphp

    @if ($comItens->isEmpty())
        <x-filament::section>
            <div class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-success-600" />
                <div>
                    <div class="font-medium">Nada esperando ação sua agora.</div>
                    @if ($emDia->isNotEmpty())
                        <div class="text-gray-500">Em dia: {{ $emDia->map(fn ($fila) => $fila->fonte->titulo())->implode(', ') }}.</div>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-filament::section>
                <div class="text-sm text-gray-500">Esperando ação</div>
                <div class="text-3xl font-semibold text-warning-600">{{ $total }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Já passaram do prazo</div>
                <div class="text-3xl font-semibold {{ $totalAtrasados > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $totalAtrasados }}</div>
            </x-filament::section>
        </div>

        @foreach ($porArea as $area => $filasDaArea)
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $area }}</h2>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @foreach ($filasDaArea as $fila)
                        <x-filament::section wire:key="fila-{{ $fila->fonte->chave() }}">
                            <x-slot name="heading">
                                <span class="flex items-center gap-2">
                                    <x-filament::icon :icon="$fila->fonte->icone()" class="h-5 w-5 text-gray-500" />
                                    {{ $fila->fonte->titulo() }}
                                </span>
                            </x-slot>

                            <x-slot name="afterHeader">
                                <div class="flex items-center gap-2">
                                    <x-filament::badge color="warning" title="Esperando ação">{{ $fila->total }}</x-filament::badge>
                                    @if ($fila->atrasados > 0)
                                        <x-filament::badge color="danger" title="Passaram do prazo">{{ $fila->atrasados }} atrasado(s)</x-filament::badge>
                                    @endif
                                </div>
                            </x-slot>

                            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ $fila->fonte->orientacao() }}</p>

                            <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                                @foreach ($fila->itens as $item)
                                    <li class="py-2">
                                        @if ($item->url)
                                            <a href="{{ $item->url }}" class="font-medium text-primary-600 hover:underline">{{ $item->titulo }}</a>
                                        @else
                                            <span class="font-medium">{{ $item->titulo }}</span>
                                        @endif
                                        @if ($item->atrasado)
                                            <x-filament::badge color="danger" size="sm" class="ml-1 inline-flex">atrasado</x-filament::badge>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->detalhe }}</div>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="text-gray-500">
                                    @if ($fila->restantes() > 0)
                                        e mais {{ $fila->restantes() }}
                                    @endif
                                </span>
                                <a href="{{ $fila->fonte->url() }}" class="font-medium text-primary-600 hover:underline">Abrir a fila →</a>
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($emDia->isNotEmpty())
            <p class="text-sm text-gray-500">Em dia: {{ $emDia->map(fn ($fila) => $fila->fonte->titulo())->implode(', ') }}.</p>
        @endif
    @endif

    @if ($comErro->isNotEmpty())
        <p class="text-sm text-warning-700 dark:text-warning-400">
            Não foi possível carregar: {{ $comErro->map(fn ($fila) => $fila->fonte->titulo())->implode(', ') }}. As outras filas estão corretas; tente atualizar em instantes.
        </p>
    @endif
</x-filament-panels::page>
