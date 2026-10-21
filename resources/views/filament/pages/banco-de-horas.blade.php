<x-filament-panels::page>
    @php
        $resumo = $this->getResumo();
        $funcionario = $this->getFuncionarioSelecionado();
        $hm = fn (?int $m): string => $m === null ? '—' : ($m < 0 ? '-' : '').sprintf('%02d:%02d', intdiv(abs($m), 60), abs($m) % 60);
    @endphp

    <div class="space-y-4">
        {{ $this->form }}

        @if (! $resumo)
            <x-filament::section>
                <p class="text-sm text-gray-500">Nenhum funcionário selecionado.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">{{ $funcionario->pessoa?->nome }}</x-slot>
                <x-slot name="description">{{ $funcionario->cargo }}</x-slot>

                <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                    <div><div class="text-gray-500">Saldo</div><strong class="text-2xl tabular-nums {{ $resumo['saldo'] < 0 ? 'text-danger-600' : '' }}">{{ $hm($resumo['saldo']) }}</strong></div>
                    <div><div class="text-gray-500">Disponível para folga</div><strong class="text-2xl tabular-nums">{{ $hm($resumo['utilizavel']) }}</strong></div>
                    <div><div class="text-gray-500">Saldo devedor</div><strong class="text-2xl tabular-nums">{{ $hm($resumo['devedor']) }}</strong></div>
                    <div><div class="text-gray-500">A pagar (últimos 12 meses)</div><strong class="text-2xl tabular-nums">{{ $hm($resumo['a_pagar']) }}</strong></div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Créditos a vencer">
                @forelse ($resumo['creditos'] as $credito)
                    @php
                        $vence = $credito['lancamento']->vence_em;
                        $urgente = $vence && $vence->lte(today()->addDays(30));
                    @endphp
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10" wire:key="credito-{{ $credito['lancamento']->id }}">
                        <span class="tabular-nums font-medium">{{ $hm($credito['restante']) }}</span>
                        <span class="text-gray-500">de {{ $credito['lancamento']->data->format('d/m/Y') }}</span>
                        <span class="{{ $urgente ? 'text-danger-600 font-medium' : 'text-gray-500' }}">{{ $vence ? 'vence em '.$vence->format('d/m/Y') : 'sem vencimento' }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nenhum crédito em aberto.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section heading="Extrato" description="Últimos 60 lançamentos. Lançamentos nunca são alterados nem apagados.">
                @forelse ($resumo['extrato'] as $lancamento)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10" wire:key="lanc-{{ $lancamento->id }}">
                        <span class="text-gray-500">{{ $lancamento->data->format('d/m/Y') }}</span>
                        <span>{{ $lancamento->origem->getLabel() }}</span>
                        <span class="tabular-nums font-medium {{ $lancamento->minutos < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $lancamento->minutos > 0 ? '+' : '' }}{{ $hm($lancamento->minutos) }}</span>
                        <span class="basis-full text-xs text-gray-500">{{ $lancamento->observacao }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Sem lançamentos.</p>
                @endforelse
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
