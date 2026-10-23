<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 42px 48px 58px 48px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11.5px; line-height: 1.55; color: #111; }
        h2 { font-size: 15px; text-align: center; margin: 0 0 12px; }
        h3 { font-size: 12px; margin: 14px 0 4px; }
        p { margin: 0 0 6px; text-align: justify; }
        ul { margin: 0 0 6px 16px; padding: 0; }
        .escola { text-align: center; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid #999; font-size: 12px; }
        .rodape { position: fixed; bottom: -38px; left: 0; right: 0; font-size: 8.5px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="escola"><strong>{{ $escola }}</strong></div>

    @if (filled($cabecalho))
        <div>{!! $cabecalho !!}</div>
    @endif

    <div>{!! $conteudo !!}</div>

    @if (filled($rodape))
        <div>{!! $rodape !!}</div>
    @endif

    <div class="rodape">Documento gerado pelo Torre360 em {{ $geradoEm }} para assinatura eletrônica. Código interno {{ $codigo }}.</div>
</body>
</html>
