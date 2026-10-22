<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Distrato de matrícula — {{ $matricula->pessoa?->nome }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; line-height: 1.45; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 2px; text-transform: uppercase; }
        .sub { text-align: center; color: #555; margin-bottom: 14px; }
        h2 { font-size: 11px; margin: 14px 0 4px; }
        p { margin: 0 0 6px; text-align: justify; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        th, td { border: 1px solid #bbb; padding: 3px 5px; text-align: left; }
        th { background: #f3f4f6; }
        td.num, th.num { text-align: right; }
        .total td { font-weight: bold; background: #f9fafb; }
        .assinaturas { margin-top: 38px; }
        .assinaturas td { border: none; padding-top: 30px; width: 50%; text-align: center; vertical-align: top; }
        .linha { border-top: 1px solid #111; padding-top: 3px; margin: 0 14px; }
    </style>
</head>
<body>
    @php
        $reais = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
        $responsaveis = $contrato?->responsaveisFinanceiros->map(fn ($r) => $r->pessoa?->nome)->filter()->values() ?? collect();
        $turma = $matricula->turma;
        $destinos = [
            'manter' => 'Mantida',
            'cancelar' => 'Cancelada',
            'devolver' => 'Cancelada, com devolução do valor pago',
        ];
    @endphp

    <h1>Distrato de contrato de prestação de serviços educacionais</h1>
    <div class="sub">{{ $escola }}</div>

    <h2>Das partes</h2>
    <p>
        <strong>CONTRATADA:</strong> {{ $escola }}.<br>
        <strong>CONTRATANTE{{ $responsaveis->count() > 1 ? 'S' : '' }}:</strong> {{ $responsaveis->isNotEmpty() ? $responsaveis->implode(', ') : 'responsável financeiro do aluno' }}.<br>
        <strong>ALUNO(A):</strong> {{ $matricula->pessoa?->nome }}{{ $turma ? ', turma '.$turma->nome.($turma->serie ? ' ('.$turma->serie->nome.')' : '') : '' }}{{ $turma?->periodoLetivo ? ', período letivo '.$turma->periodoLetivo->nome : '' }}.
    </p>

    <h2>1. Do objeto</h2>
    <p>
        As partes resolvem, de comum acordo, rescindir o contrato de prestação de serviços educacionais
        @if ($contrato?->data_aceite)firmado em {{ \Illuminate\Support\Carbon::parse($contrato->data_aceite)->format('d/m/Y') }}@endif
        referente à matrícula acima, com efeito a partir de <strong>{{ $cancelamento->data_efetiva->format('d/m/Y') }}</strong>.
    </p>

    <h2>2. Das obrigações até a saída</h2>
    <p>
        Permanecem devidas as parcelas com vencimento até o último dia do mês da saída, inclusive. O mês da saída é cobrado integralmente.
        A situação de cada fatura na data de aprovação deste distrato é a seguinte:
    </p>

    @if ($faturas->isEmpty())
        <p>Não há faturas vinculadas a este contrato.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Fatura</th><th>Vencimento</th><th class="num">Valor</th><th class="num">Pago</th><th>Destino</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($faturas as $fatura)
                    <tr>
                        <td>{{ $fatura->itens->pluck('descricao')->filter()->implode(' + ') ?: 'Fatura #'.$fatura->id }}</td>
                        <td>{{ $fatura->vencimento->format('d/m/Y') }}</td>
                        <td class="num">{{ $reais((float) $fatura->valor) }}</td>
                        <td class="num">{{ $reais((float) $fatura->valor_pago) }}</td>
                        <td>{{ $destinos[$decisoes[$fatura->id] ?? 'manter'] ?? 'Mantida' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>3. Dos valores</h2>
    <p>
        Valor devido pela CONTRATANTE nesta data, incluídas a multa de mora e os juros calculados até a aprovação deste distrato:
        <strong>{{ $reais((float) $cancelamento->valor_devido) }}</strong>. Multa e juros continuam correndo sobre o que estiver vencido e não for pago.
    </p>
    @if ((float) $cancelamento->valor_a_devolver > 0)
        <p>
            Valor a ser devolvido pela CONTRATADA, referente a parcelas pagas antecipadamente e canceladas:
            <strong>{{ $reais((float) $cancelamento->valor_a_devolver) }}</strong>.
        </p>
    @endif
    @if ((float) $cancelamento->multa_rescisoria > 0)
        <p>
            Multa rescisória prevista no contrato: <strong>{{ $reais((float) $cancelamento->multa_rescisoria) }}</strong>,
            com vencimento em até {{ $prazoMulta->format('d/m/Y') }}.
        </p>
    @else
        <p>Não há multa rescisória.</p>
    @endif

    <h2>4. Da quitação</h2>
    <p>
        Cumpridas as obrigações acima, as partes dão-se mútua quitação quanto ao contrato rescindido, nada mais tendo a reclamar uma da outra
        a esse título.
    </p>

    <h2>5. Dos documentos escolares</h2>
    <p>
        A CONTRATADA entregará os documentos de transferência e demais documentos escolares do(a) aluno(a) quando solicitados,
        independentemente de a CONTRATANTE estar adimplente, conforme o art. 6º, § 2º, da Lei nº 9.870/1999.
    </p>

    <p style="margin-top: 14px;">Data: {{ $emissao->format('d/m/Y') }}.</p>

    <table class="assinaturas">
        <tr>
            <td>
                <div class="linha">
                    {{ $responsaveis->isNotEmpty() ? $responsaveis->implode(' / ') : 'CONTRATANTE' }}<br>
                    CPF: ______________________
                </div>
            </td>
            <td>
                <div class="linha">{{ $escola }}<br>CONTRATADA</div>
            </td>
        </tr>
    </table>
</body>
</html>
