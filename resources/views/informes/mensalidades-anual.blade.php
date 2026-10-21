<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Informe Anual de Mensalidades {{ $ano }}</title>
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
        .quebra { page-break-after: always; }
    </style>
</head>
<body>
    @php
        $reais = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
        $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    @endphp

    @if ($informes->isEmpty())
        <h1>Informe Anual de Mensalidades — Ano-calendário {{ $ano }}</h1>
        <p>Não há pagamentos registrados em {{ $ano }}{{ $alunoSemPagamento ? ' para '.$alunoSemPagamento->pessoa?->nome : '' }}.</p>
    @endif

    @foreach ($informes as $informe)
        <div class="{{ $loop->last ? '' : 'quebra' }}">
            <h1>Informe Anual de Mensalidades — Ano-calendário {{ $ano }}</h1>
            <div class="sub">{{ $informe['escola']['nome'] }}@if ($informe['escola']['unidade'] && $informe['escola']['unidade'] !== $informe['escola']['nome']) · {{ $informe['escola']['unidade'] }}@endif · CNPJ {{ $informe['escola']['cnpj'] ?? 'não informado' }}</div>

            <table class="dados">
                <tr><td style="width: 22%">Aluno(a)</td><td><strong>{{ $informe['aluno']['nome'] }}</strong></td></tr>
                <tr><td>CPF do aluno(a)</td><td>{{ $informe['aluno']['cpf'] ?? 'não informado' }}</td></tr>
                <tr><td>Turma / série</td><td>{{ $informe['turma'] }}{{ $informe['serie'] ? ' · '.$informe['serie'] : '' }}</td></tr>
            </table>

            <h2>Responsáveis financeiros</h2>
            @if ($informe['responsaveis'] === [])
                <p>Nenhum responsável financeiro cadastrado no contrato.</p>
            @else
                <table>
                    <thead><tr><th>Nome</th><th>CPF</th><th class="num">Participação</th><th class="num">Valor no ano</th></tr></thead>
                    <tbody>
                        @foreach ($informe['responsaveis'] as $responsavel)
                            <tr>
                                <td>{{ $responsavel['nome'] }}</td>
                                <td>{{ $responsavel['cpf'] ?? 'não informado' }}</td>
                                <td class="num">{{ number_format($responsavel['percentual'], 2, ',', '.') }}%</td>
                                <td class="num">{{ $reais($responsavel['valor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <h2>Pagamentos recebidos por mês</h2>
            <table>
                <thead><tr><th>Mês</th><th class="num">Valor pago</th></tr></thead>
                <tbody>
                    @foreach ($meses as $numero => $nome)
                        <tr><td>{{ $nome }}</td><td class="num">{{ $reais($informe['por_mes'][$numero]) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Total pago em {{ $ano }}</td><td class="num">{{ $reais($informe['total_pago']) }}</td></tr>
                    <tr class="total"><td>Parte estimada como despesa com instrução</td><td class="num">{{ $reais($informe['total_dedutivel']) }}</td></tr>
                    <tr><td>Outros itens (material, uniforme, alimentação, transporte, passeios)</td><td class="num">{{ $reais($informe['total_nao_dedutivel']) }}</td></tr>
                </tbody>
            </table>

            <h2>Detalhe dos pagamentos</h2>
            <table>
                <thead><tr><th>Data</th><th>Fatura</th><th>Descrição</th><th class="num">Valor</th></tr></thead>
                <tbody>
                    @foreach ($informe['pagamentos'] as $pagamento)
                        <tr>
                            <td>{{ $pagamento['data']->format('d/m/Y') }}</td>
                            <td>#{{ $pagamento['fatura_id'] }}</td>
                            <td>{{ $pagamento['descricao'] }}</td>
                            <td class="num">{{ $reais($pagamento['valor']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="nota">
                Documento informativo emitido pelo sistema em {{ now()->format('d/m/Y') }}, com os pagamentos recebidos pela escola de 01/01/{{ $ano }} a 31/12/{{ $ano }}.
                Não substitui o recibo nem a nota fiscal de serviço. A parte "estimada como despesa com instrução" exclui itens cuja descrição indica material, uniforme, alimentação, transporte ou passeios;
                as regras de dedução do imposto de renda (Lei nº 9.250/1995, art. 8º, II, b) e o limite anual devem ser conferidos com a sua contabilidade.
            </div>
        </div>
    @endforeach
</body>
</html>
