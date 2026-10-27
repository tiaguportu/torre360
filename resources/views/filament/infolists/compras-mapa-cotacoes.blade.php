@php
    $requisicao = $getRecord();
    $mapa = app(\App\Services\ComprasService::class)->mapaComparativo($requisicao);
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

<div class="space-y-3">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                    <th class="py-2 pr-3">Item</th>
                    <th class="pr-3 text-right">Qtd.</th>
                    <th class="pr-3 text-right">Estimativa</th>
                    @foreach ($mapa['cotacoes'] as $cotacao)
                        <th class="pr-3 text-right">
                            {{ $cotacao['fornecedor'] }}
                            @if ($cotacao['escolhida'])<span class="ml-1 text-xs text-success-600">(escolhida)</span>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($mapa['itens'] as $item)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2 pr-3">{{ $item['descricao'] }}</td>
                        <td class="pr-3 text-right tabular-nums">{{ rtrim(rtrim(number_format($item['quantidade'], 3, ',', '.'), '0'), ',') }} {{ $item['unidade'] }}</td>
                        <td class="pr-3 text-right tabular-nums">{{ $item['estimativa'] !== null ? $fmt($item['estimativa']) : '—' }}</td>
                        @foreach ($mapa['cotacoes'] as $cotacao)
                            @php($preco = $item['precos'][$cotacao['id']] ?? null)
                            <td class="pr-3 text-right tabular-nums {{ $preco !== null && $preco === $item['menor'] ? 'font-semibold text-success-600' : '' }}">{{ $preco !== null ? $fmt($preco) : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="text-gray-700 dark:text-gray-200">
                    <td class="py-2 pr-3 font-medium" colspan="3">Total (com frete)</td>
                    @foreach ($mapa['cotacoes'] as $cotacao)
                        <td class="pr-3 text-right font-semibold tabular-nums {{ $cotacao['mais_barata'] ? 'text-success-600' : '' }}">R$ {{ $fmt($cotacao['total']) }}@if ($cotacao['mais_barata'])<span class="block text-xs font-normal">menor valor</span>@endif</td>
                    @endforeach
                </tr>
                <tr class="text-xs text-gray-500">
                    <td class="py-1 pr-3" colspan="3">Entrega · pagamento · validade</td>
                    @foreach ($mapa['cotacoes'] as $cotacao)
                        <td class="pr-3 text-right">
                            {{ $cotacao['prazo_entrega'] !== null ? $cotacao['prazo_entrega'].' d' : '—' }} · {{ $cotacao['prazo_pagamento'] }} d ·
                            <span class="{{ $cotacao['vencida'] ? 'text-danger-600' : '' }}">{{ $cotacao['validade'] ? $cotacao['validade']->format('d/m/Y') : 'sem validade' }}</span>
                            @if ($cotacao['divergencias'] > 0)
                                <span class="block font-medium text-danger-600">⚠ {{ $cotacao['divergencias'] }} recebimento(s) com divergência</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="text-xs text-gray-500">Esta faixa de valor pede {{ $mapa['minimo'] }} cotação(ões); há {{ count($mapa['cotacoes']) }}.</p>
</div>
