@php
    $reais = fn (float $valor): string => 'R$ '.number_format($valor, 2, ',', '.');
@endphp

<div class="space-y-4 text-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Saída em</div>
            <div class="font-semibold">{{ $cancelamento->data_efetiva->format('d/m/Y') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">A família ainda deve</div>
            <div class="font-semibold">{{ $reais($totais['valor_devido']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">A escola devolve</div>
            <div class="font-semibold">{{ $reais($totais['valor_a_devolver']) }}</div>
        </div>
    </div>

    @if ($cancelamento->motivo_recusa)
        <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            <strong>Motivo da recusa:</strong> {{ $cancelamento->motivo_recusa }}
        </div>
    @endif

    @if ($linhas->isEmpty())
        <p class="text-gray-600 dark:text-gray-300">
            @if ($cancelamento->estaAberto())
                Não há faturas em aberto neste contrato.
            @else
                As decisões sobre as faturas já foram aplicadas ou o pedido foi encerrado. Os totais acima são os do distrato.
            @endif
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pe-3">Fatura</th>
                        <th class="py-2 pe-3">Vencimento</th>
                        <th class="py-2 pe-3 text-right">Pago</th>
                        <th class="py-2 pe-3 text-right">Em aberto</th>
                        <th class="py-2">Destino</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($linhas as $linha)
                        @php
                            $decisao = $decisoes[$linha['fatura_id']] ?? $linha['decisao'];
                        @endphp
                        <tr class="border-b border-gray-100 align-top dark:border-white/5">
                            <td class="py-2 pe-3">
                                {{ $linha['descricao'] }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $linha['status']->getLabel() }}</div>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap">{{ $linha['vencimento']->format('d/m/Y') }}</td>
                            <td class="py-2 pe-3 text-right whitespace-nowrap">{{ $reais($linha['valor_pago']) }}</td>
                            <td class="py-2 pe-3 text-right whitespace-nowrap">
                                {{ $reais($linha['total_em_aberto']) }}
                                @if ($linha['encargos'] > 0)
                                    <div class="text-xs text-danger-600 dark:text-danger-400">com {{ $reais($linha['encargos']) }} de multa e juros</div>
                                @endif
                            </td>
                            <td class="py-2">
                                {{ $rotulos[$decisao] ?? $decisao }}
                                @if (! isset($decisoes[$linha['fatura_id']]))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $linha['sugestao'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ((float) $cancelamento->multa_rescisoria > 0)
        <p>Multa rescisória: <strong>{{ $reais((float) $cancelamento->multa_rescisoria) }}</strong> (vira uma fatura ao concluir).</p>
    @endif

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Valores com multa e juros até hoje. Eles mudam a cada dia de atraso; a posição fixa é a do distrato, na data da aprovação.
    </p>
</div>
