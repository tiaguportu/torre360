<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Link Inválido ou Expirado | Torre360</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #1e293b; -webkit-font-smoothing: antialiased; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-top: 3px solid #243468; border-radius: 12px; padding: 40px 36px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); }
        .icon-wrap { width: 48px; height: 48px; border-radius: 50%; margin: 0 auto 20px; background: #fffbeb; color: #b45309; box-shadow: inset 0 0 0 1px #fde68a; display: flex; align-items: center; justify-content: center; }
        .icon-wrap svg { width: 24px; height: 24px; }
        h1 { font-size: 20px; font-weight: 600; letter-spacing: -0.01em; margin-bottom: 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.65; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap"><x-heroicon-o-exclamation-triangle /></div>
        <h1>Este link não é mais válido</h1>
        <p>
            O link do Portal de Admissão expirou ou não existe. Entre em contato com a
            secretaria da escola para receber um novo link de acesso.
        </p>
    </div>
</body>
</html>
