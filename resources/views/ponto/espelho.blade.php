<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Espelho de Ponto</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .sub { color: #555; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; text-align: left; }
        th { background: #f3f4f6; }
        td.num, th.num { text-align: right; }
        .alerta { color: #b45309; }
        .neg { color: #b91c1c; }
        .totais td { font-weight: bold; background: #f9fafb; }
        .rodape { margin-top: 10px; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    @php
        $nomes = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
        $hm = fn (?int $m): string => $m === null ? '—' : ($m < 0 ? '-' : '').sprintf('%02d:%02d', intdiv(abs($m), 60), abs($m) % 60);
        $situacoes = ['ok' => 'Apurado', 'folga' => 'Folga', 'falta' => 'Falta', 'abonado' => 'Abonado', 'incompleto' => 'Incompleto', 'em_andamento' => 'Em andamento', 'futuro' => ''];
    @endphp

    <h1>Espelho de Ponto — {{ sprintf('%02d/%d', $espelho['mes'], $espelho['ano']) }}</h1>
    <div class="sub">{{ $funcionario->pessoa?->nome }} · {{ $funcionario->cargo }}@if ($funcionario->unidade) · {{ $funcionario->unidade->nome }}@endif</div>

    <table>
        <thead>
            <tr>
                <th>Dia</th><th>Marcações</th><th class="num">Previsto</th><th class="num">Trabalhado</th><th class="num">Abono</th><th class="num">Saldo</th><th>Situação</th><th>Observações</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($espelho['dias'] as $dia)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dia['data'])->format('d/m') }} {{ $nomes[$dia['dia_semana']] }}</td>
                    <td>
                        @foreach ($dia['marcacoes'] as $m){{ $m['hora'] }}{{ $m['ajustada'] ? '*' : '' }}@if (! $loop->last) · @endif @endforeach
                    </td>
                    <td class="num">{{ $hm($dia['previsto']) }}</td>
                    <td class="num">{{ $hm($dia['trabalhado']) }}</td>
                    <td class="num">{{ $dia['abono'] ? $hm($dia['abono']) : '' }}</td>
                    <td class="num {{ ($dia['saldo'] ?? 0) < 0 ? 'neg' : '' }}">{{ $hm($dia['saldo']) }}</td>
                    <td>{{ $situacoes[$dia['situacao']] ?? $dia['situacao'] }}@if ($dia['feriado']) · {{ $dia['feriado'] }}@endif</td>
                    <td class="alerta">{{ collect($dia['alertas'])->pluck('mensagem')->implode(' ') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totais">
                <td colspan="2">Totais do mês</td>
                <td class="num">{{ $hm($espelho['totais']['previsto']) }}</td>
                <td class="num">{{ $hm($espelho['totais']['trabalhado']) }}</td>
                <td class="num">{{ $hm($espelho['totais']['abono']) }}</td>
                <td colspan="3">
                    Crédito no banco: {{ $hm($espelho['totais']['extras_banco']) }} ·
                    Extras a pagar: {{ $hm($espelho['totais']['extras_a_pagar']) }} ·
                    Débitos: {{ $hm($espelho['totais']['debitos']) }} ·
                    Faltas: {{ $espelho['totais']['faltas'] }} · Dias incompletos: {{ $espelho['totais']['incompletos'] }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="rodape">* Marcação incluída por ajuste aprovado. A tolerância de até 10 minutos por dia (CLT, art. 58, § 1º) já está aplicada ao saldo. Documento gerado em {{ now()->format('d/m/Y H:i') }}.</div>
</body>
</html>
