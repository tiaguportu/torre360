<x-filament-panels::page>
    @php
        $p = $this->projecao();
        $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
        $c = $p['conciliacao'];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="fp-cenario">Cenário de recebimento</label>
                    <select id="fp-cenario" wire:model.live="cenario" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->cenarios() as $chave => $rotulo)
                            <option value="{{ $chave }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="fp-dias">Horizonte</label>
                    <select id="fp-dias" wire:model.live="dias" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ([30, 60, 90, 180] as $d)
                            <option value="{{ $d }}">{{ $d }} dias</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Estimativa de {{ $p['inicio']->format('d/m/Y') }} a {{ $p['fim']->format('d/m/Y') }}, no cenário “{{ $p['cenario_nome'] }}”.
                @if ($p['taxa'] !== null)
                    Taxa de recebimento usada: {{ number_format($p['taxa'] * 100, 1, ',', '.') }}%
                    ({{ $p['taxa_origem'] === 'historico' ? 'média dos últimos meses' : 'taxa padrão da escola, ainda sem histórico suficiente' }}).
                @endif
            </p>
        </x-filament::section>

        @if ($p['primeira_data_negativa'])
            <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-200">
                <strong>O saldo fica negativo em {{ $p['primeira_data_negativa']->format('d/m/Y') }}</strong>
                ({{ $p['primeira_data_negativa']->diffInDays($p['inicio']) }} dia(s) a partir de hoje). O menor saldo previsto é R$ {{ $fmt($p['menor_saldo']) }}
                em {{ $p['data_menor_saldo']->format('d/m/Y') }}.
            </div>
        @else
            <div class="rounded-lg border border-success-300 bg-success-50 p-4 text-sm text-success-800 dark:border-success-700 dark:bg-success-950 dark:text-success-200">
                No cenário escolhido o saldo não fica negativo nos próximos {{ $p['dias'] }} dias. O menor saldo previsto é R$ {{ $fmt($p['menor_saldo']) }}
                em {{ $p['data_menor_saldo']->format('d/m/Y') }}.
            </div>
        @endif

        @if (! $c['ultimo_lancamento'] || $c['nao_conciliadas'] > 0 || ($c['ultimo_lancamento'] && $c['ultimo_lancamento']->lt(today()->subDays(7))))
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
                <strong>Confira a conciliação bancária antes de confiar neste saldo.</strong>
                @if ($c['ultimo_lancamento'])
                    O último lançamento bancário é de {{ $c['ultimo_lancamento']->format('d/m/Y') }}.
                @else
                    Não há nenhuma transação bancária lançada.
                @endif
                @if ($c['nao_conciliadas'] > 0)
                    {{ $c['nao_conciliadas'] }} transação(ões) ainda sem conciliar.
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <x-filament::section>
                <div class="text-sm text-gray-500">Saldo de hoje</div>
                <div class="text-xl font-semibold tabular-nums {{ $p['saldo_inicial'] < 0 ? 'text-danger-600' : '' }}">R$ {{ $fmt($p['saldo_inicial']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">A receber (estimado)</div>
                <div class="text-xl font-semibold tabular-nums text-success-600">R$ {{ $fmt($p['entradas']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">A pagar</div>
                <div class="text-xl font-semibold tabular-nums text-danger-600">R$ {{ $fmt($p['saidas']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Saldo no fim</div>
                <div class="text-xl font-semibold tabular-nums {{ $p['saldo_final'] < 0 ? 'text-danger-600' : '' }}">R$ {{ $fmt($p['saldo_final']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Menor saldo</div>
                <div class="text-xl font-semibold tabular-nums {{ $p['menor_saldo'] < 0 ? 'text-danger-600' : '' }}">R$ {{ $fmt($p['menor_saldo']) }}</div>
            </x-filament::section>
        </div>

        <x-filament::section heading="Semana a semana">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">Semana</th>
                            <th class="pr-3 text-right">Entradas</th>
                            <th class="pr-3 text-right">Saídas</th>
                            <th class="text-right">Saldo no fim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($p['semanas'] as $semana)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-3">{{ $semana['inicio']->format('d/m') }} a {{ $semana['fim']->format('d/m') }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $fmt($semana['entradas']) }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $fmt($semana['saidas']) }}</td>
                                <td class="text-right font-medium tabular-nums {{ $semana['saldo_final'] < 0 ? 'text-danger-600' : '' }}">
                                    {{ $fmt($semana['saldo_final']) }}@if ($semana['negativa'] && $semana['saldo_final'] >= 0) <span class="text-xs text-danger-600">(negativo no meio da semana)</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="O que entra na conta">
            <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                <li>
                    <strong>{{ $p['origem']['faturas']['quantidade'] }}</strong> fatura(s) aberta(s) somam R$ {{ $fmt($p['origem']['faturas']['bruto']) }};
                    no cenário escolhido entram R$ {{ $fmt($p['origem']['faturas']['ponderado']) }}.
                </li>
                <li><strong>{{ $p['origem']['contas']['quantidade'] }}</strong> conta(s) a pagar aberta(s) somam R$ {{ $fmt($p['origem']['contas']['valor']) }} (as vencidas saem já no primeiro dia).</li>
                <li>
                    <strong>{{ $p['origem']['recorrentes']['quantidade'] }}</strong> vencimento(s) de despesas recorrentes ainda sem conta somam R$ {{ $fmt($p['origem']['recorrentes']['valor']) }}.
                    @can('ViewAny', \App\Models\ContaRecorrente::class)
                        <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\ContasRecorrentes\ContaRecorrenteResource::getUrl('index') }}">Ver contas recorrentes</a>
                    @endcan
                </li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
