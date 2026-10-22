<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Demonstrativo de débito — {{ $matricula->pessoa?->nome }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 4px; }
        .sub { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
        th { background: #f3f4f6; }
        td.num, th.num { text-align: right; }
        .total td { font-weight: bold; background: #f9fafb; }
        .dados td { border: none; padding: 2px 0; }
        .nota { margin-top: 12px; font-size: 8.5px; color: #444; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $reais = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
        $pct = fn (float $v): string => number_format($v, 2, ',', '.').'%';
    @endphp

    <h1>Demonstrativo de débito</h1>
    <div class="sub">{{ $escola }} · posição em {{ $emissao->format('d/m/Y') }}</div>

    <table class="dados">
        <tr><td style="width: 22%">Aluno(a)</td><td><strong>{{ $matricula->pessoa?->nome }}</strong></td></tr>
        <tr><td>Turma</td><td>{{ $matricula->turma?->nome }}{{ $matricula->turma?->serie ? ' · '.$matricula->turma->serie->nome : '' }}</td></tr>
        <tr><td>Responsável(is) financeiro(s)</td><td>{{ $matricula->contrato->responsaveisFinanceiros->map(fn ($r) => $r->pessoa?->nome)->filter()->implode(', ') ?: 'não cadastrado' }}</td></tr>
    </table>

    <h2>Faturas em atraso</h2>
    @if ($linhas->isEmpty())
        <p>Não há faturas em atraso nesta data.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Fatura</th><th>Vencimento</th><th class="num">Dias</th><th class="num">Valor em aberto</th><th class="num">Multa</th><th class="num">Juros</th><th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $linha)
                    <tr>
                        <td>{{ $linha['descricao'] }}</td>
                        <td>{{ $linha['vencimento']->format('d/m/Y') }}@if ($linha['vencimento_efetivo']->ne($linha['vencimento'])) <br><span style="color: #666;">vale até {{ $linha['vencimento_efetivo']->format('d/m/Y') }}</span>@endif</td>
                        <td class="num">{{ $linha['dias_atraso'] }}</td>
                        <td class="num">{{ $reais($linha['principal']) }}</td>
                        <td class="num">{{ $reais($linha['multa']) }}</td>
                        <td class="num">{{ $reais($linha['juros']) }}</td>
                        <td class="num">{{ $reais($linha['total']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Total</td>
                    <td class="num">{{ $reais($totais['principal']) }}</td>
                    <td class="num">{{ $reais($totais['multa']) }}</td>
                    <td class="num">{{ $reais($totais['juros']) }}</td>
                    <td class="num">{{ $reais($totais['total']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="nota">
        Cálculo até {{ $emissao->format('d/m/Y') }}: multa de {{ $pct($parametros['multa_percentual']) }} sobre o valor em aberto, cobrada uma vez
        (a multa de mora não pode passar de 2%: CDC, art. 52, § 1º), e juros de mora de {{ $pct($parametros['juros_mensal_percentual']) }} ao mês,
        simples e proporcionais aos dias de atraso. Vencimento em fim de semana ou feriado vale no próximo dia útil.
        Os valores mudam a cada dia de atraso e se houver pagamento parcial. O boleto ou o PIX emitido pelo banco pode
        trazer valor diferente: confirme com a secretaria antes de pagar. Este documento é informativo e não substitui o
        recibo nem a fatura.
    </div>
</body>
</html>
