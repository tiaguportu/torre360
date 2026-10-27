<x-filament-panels::page>
    @php
        $grupos = $this->cartoes();
        $atencao = $this->atencaoHoje();
        $pontos = function (array $serie): ?string {
            if (count($serie) < 2) {
                return null;
            }
            $min = min($serie);
            $max = max($serie);
            $amplitude = ($max - $min) ?: 1;
            $n = count($serie);
            $coords = [];
            foreach ($serie as $i => $v) {
                $coords[] = round($i / ($n - 1) * 100, 1).','.round(22 - (($v - $min) / $amplitude) * 20, 1);
            }

            return implode(' ', $coords);
        };
        $corDoSemaforo = ['verde' => 'bg-success-500', 'amarelo' => 'bg-warning-500', 'vermelho' => 'bg-danger-500'];
        $rotuloDoSemaforo = ['verde' => 'Meta atingida', 'amarelo' => 'Dentro da tolerância', 'vermelho' => 'Fora da meta'];
        $corDaVariacao = ['melhor' => 'text-success-600', 'pior' => 'text-danger-600', 'igual' => 'text-gray-500'];
        $seta = ['melhor' => '▲', 'pior' => '▼', 'igual' => '■'];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="max-w-sm">
                <label class="mb-1 block text-sm font-medium" for="pd-unidade">Unidade</label>
                <select id="pd-unidade" wire:model.live="unidadeId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                    <option value="">Escola toda</option>
                    @foreach ($this->unidades() as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
        </x-filament::section>

        @if (count($atencao) > 0)
            <x-filament::section heading="Atenção hoje" description="Filas da sua Minha Mesa com algo atrasado.">
                <ul class="space-y-2 text-sm">
                    @foreach ($atencao as $fila)
                        <li class="flex flex-wrap items-baseline justify-between gap-2">
                            <a class="font-medium text-primary-600 underline" href="{{ $fila->fonte->url() }}">{{ $fila->fonte->titulo() }}</a>
                            <span class="text-danger-600">{{ $fila->atrasados }} atrasado(s) de {{ $fila->total }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @forelse ($grupos as $grupo => $cartoes)
            <section class="space-y-3">
                <h2 class="text-base font-semibold text-gray-700 dark:text-gray-200">{{ $grupo }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($cartoes as $c)
                        @php($linha = $pontos($c['serie']))
                        <x-filament::section>
                            <div class="flex items-start justify-between gap-2">
                                <div class="text-sm text-gray-500" title="{{ $c['descricao'] }}">
                                    {{ $c['titulo'] }}
                                    <span class="cursor-help text-xs text-gray-400" title="{{ $c['descricao'] }}">ⓘ</span>
                                </div>
                                @if ($c['semaforo'])
                                    <span class="mt-1 inline-block size-3 shrink-0 rounded-full {{ $corDoSemaforo[$c['semaforo']] }}" title="{{ $rotuloDoSemaforo[$c['semaforo']] }}" aria-label="{{ $rotuloDoSemaforo[$c['semaforo']] }}"></span>
                                @endif
                            </div>

                            <div class="mt-1 text-2xl font-semibold tabular-nums">
                                @if ($c['url'])
                                    <a href="{{ $c['url'] }}" class="hover:underline">{{ $c['valor_formatado'] }}</a>
                                @else
                                    {{ $c['valor_formatado'] }}
                                @endif
                            </div>

                            <div class="mt-1 min-h-[1.25rem] text-xs">
                                @if ($c['variacao'])
                                    <span class="{{ $corDaVariacao[$c['variacao']['sentido']] }}">
                                        {{ $seta[$c['variacao']['sentido']] }}
                                        {{ $c['variacao']['percentual'] !== null ? number_format(abs($c['variacao']['percentual']), 1, ',', '.').'%' : number_format(abs($c['variacao']['absoluta']), 2, ',', '.') }}
                                    </span>
                                    <span class="text-gray-500">contra {{ $c['anterior']['data']->format('d/m') }} ({{ $c['anterior']['formatado'] }})</span>
                                @else
                                    <span class="text-gray-400">sem fotografia para comparar ainda</span>
                                @endif
                            </div>

                            @if ($c['variacao_ano'])
                                <div class="text-xs text-gray-500">
                                    Um ano atrás:
                                    <span class="{{ $corDaVariacao[$c['variacao_ano']['sentido']] }}">
                                        {{ $seta[$c['variacao_ano']['sentido']] }}
                                        {{ $c['variacao_ano']['percentual'] !== null ? number_format(abs($c['variacao_ano']['percentual']), 1, ',', '.').'%' : number_format(abs($c['variacao_ano']['absoluta']), 2, ',', '.') }}
                                    </span>
                                </div>
                            @endif

                            @if ($c['meta_formatada'])
                                <div class="mt-1 text-xs text-gray-500">Meta do ano: {{ $c['meta_formatada'] }}</div>
                            @endif

                            @if ($linha)
                                <svg viewBox="0 0 100 24" class="mt-2 h-8 w-full text-primary-500" preserveAspectRatio="none" role="img" aria-label="Série dos últimos dias de {{ $c['titulo'] }}">
                                    <polyline fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" points="{{ $linha }}" />
                                </svg>
                            @endif

                            @if ($c['escola_toda'])
                                <div class="mt-1 text-xs text-gray-400">Indicador da escola toda (não existe por unidade).</div>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>
            </section>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">Você não tem acesso a nenhum indicador. Peça à administração para liberar as telas de origem.</p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
