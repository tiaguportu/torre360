<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('titulo') | {{ config('app.name', 'Torre360') }}</title>
    {{-- Sem fontes nem scripts de terceiros de propósito: estas páginas tratam de dado de criança e de denúncia sigilosa. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #eef2f7; color: #1e293b; line-height: 1.5; }
        .pagina { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        .marca { font-weight: 800; color: #243468; margin-bottom: 16px; font-size: 18px; }
        .card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        h2 { font-size: 17px; margin: 20px 0 8px; }
        p { margin: 0 0 12px; }
        label { display: block; font-size: 13px; font-weight: 700; margin: 14px 0 4px; }
        input[type=text], input[type=email], input[type=date], input[type=tel], select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; background: #fff; }
        textarea { min-height: 120px; resize: vertical; }
        .linha { display: grid; grid-template-columns: 1fr; gap: 0 12px; }
        @media (min-width: 560px) { .linha.duas { grid-template-columns: 1fr 1fr; } }
        button, .botao { display: inline-block; margin-top: 18px; padding: 12px 20px; background: #243468; color: #fff; border: 0; border-radius: 10px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        .botao.claro { background: #e2e8f0; color: #1e293b; }
        .erro { color: #b91c1c; font-size: 13px; font-weight: 600; margin-top: 4px; }
        .aviso { background: #fef9c3; border: 1px solid #fde047; border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .ok { background: #dcfce7; border: 1px solid #86efac; border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .codigo { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 26px; font-weight: 800; letter-spacing: 2px; background: #f1f5f9; padding: 12px 16px; border-radius: 10px; display: inline-block; }
        .mini { font-size: 13px; color: #64748b; }
        .tabela { width: 100%; border-collapse: collapse; font-size: 14px; }
        .tabela th, .tabela td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .oculto-de-robo { position: absolute; left: -9999px; height: 0; overflow: hidden; }
    </style>
</head>
<body>
    <div class="pagina">
        <div class="marca">{{ config('app.name', 'Torre360') }}</div>
        @yield('conteudo')
    </div>
</body>
</html>
