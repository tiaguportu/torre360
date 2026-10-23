<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #6b7280; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e5e7eb; text-align: left; padding: 4px 5px; font-size: 8.5px; border-bottom: 1px solid #9ca3af; }
        td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .direita { text-align: right; }
        .aviso { margin-top: 10px; padding: 6px 8px; background: #fef3c7; border: 1px solid #f59e0b; }
        .rodape { margin-top: 12px; color: #6b7280; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="sub">{{ $escola }} · {{ $fonte }} · gerado em {{ $geradoEm }} por {{ $geradoPor }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($colunas as $coluna)
                    <th class="{{ $coluna->tipo->alinhaADireita() ? 'direita' : '' }}">{{ $coluna->rotulo }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr>
                    @foreach ($colunas as $posicao => $coluna)
                        <td class="{{ $coluna->tipo->alinhaADireita() ? 'direita' : '' }}">{{ $linha[$posicao] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($colunas) }}">Nenhum registro para os filtros escolhidos.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($truncado)
        <div class="aviso">O relatório passou de {{ number_format($limite, 0, ',', '.') }} linhas e foi cortado. Use filtros para reduzir ou peça a planilha (CSV), que aceita mais linhas.</div>
    @endif

    <div class="rodape">
        {{ count($linhas) }} linha(s).
        @if ($temDadoPessoal)
            Este documento contém dados pessoais (LGPD): guarde com cuidado e não compartilhe fora da finalidade para a qual foi gerado.
        @endif
    </div>
</body>
</html>
