@php
    $reais = fn (float $valor): string => 'R$ '.number_format($valor, 2, ',', '.');
    $fecha = abs($valorContrato - $totalPlano) <= 0.01;
@endphp

<div class="space-y-4 text-sm">
    @if ($faturasProtegidas > 0)
        <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            Este contrato tem {{ $faturasProtegidas }} fatura(s) com pagamento registrado ou cobrança emitida. Elas impedem a regeneração: ajuste-as manualmente antes de confirmar.
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
        <div class="text-xs text-gray-500 dark:text-gray-400">Sua instrução</div>
        <div class="whitespace-pre-line">{{ $instrucao }}</div>
    </div>

    @if ($resumo !== '')
        <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 text-primary-800 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-200">
            <strong>A IA entendeu:</strong> {{ $resumo }}
        </div>
    @endif

    @if ($avisos !== [])
        <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
            <strong>Suposições da IA — confira:</strong>
            <ul class="mt-1 list-disc ps-5">
                @foreach ($avisos as $aviso)
                    <li>{{ $aviso }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-2 pe-3">#</th>
                    <th class="py-2 pe-3">Descrição</th>
                    <th class="py-2 pe-3">Vencimento</th>
                    <th class="py-2 text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($parcelas as $indice => $parcela)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2 pe-3">{{ $indice + 1 }}</td>
                        <td class="py-2 pe-3">{{ $parcela['descricao'] }}</td>
                        <td class="py-2 pe-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($parcela['vencimento'])->format('d/m/Y') }}</td>
                        <td class="py-2 text-right whitespace-nowrap">{{ $reais((float) $parcela['valor']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="pt-2 pe-3 font-semibold">Total das faturas</td>
                    <td class="pt-2 text-right font-semibold whitespace-nowrap">{{ $reais($totalPlano) }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="pe-3 text-xs text-gray-500 dark:text-gray-400">Valor do contrato</td>
                    <td class="text-right text-xs whitespace-nowrap {{ $fecha ? 'text-gray-500 dark:text-gray-400' : 'text-danger-600 dark:text-danger-400' }}">{{ $reais($valorContrato) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <ul class="list-disc space-y-1 ps-5 text-xs text-gray-600 dark:text-gray-300">
        @if ($percentualBolsa > 0)
            <li>A bolsa vigente da matrícula ({{ $percentualBolsa }}%) será aplicada como desconto em cada fatura; os valores acima são os brutos.</li>
        @endif
        @if ($faturasAtuais > 0)
            <li>As {{ $faturasAtuais }} fatura(s) atual(is) do contrato serão substituídas por estas.</li>
        @endif
        @if ($jaEnviadoAssinafy)
            <li>O contrato já foi enviado para assinatura: ao gerar, os registros do Assinafy são limpos e ele precisa ser enviado de novo.</li>
        @endif
        <li>Os valores foram calculados pelo sistema, não pela IA. Se algo estiver errado, cancele e ajuste a instrução.</li>
    </ul>
</div>
