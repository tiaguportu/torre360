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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --text: #1e293b; --muted: #64748b; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #312e81 0%, #4f46e5 50%, #6366f1 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #fff; border-radius: 24px; padding: 56px 52px; max-width: 520px; width: 100%;
            text-align: center; box-shadow: 0 32px 64px -12px rgba(0,0,0,0.25);
        }
        .icon-wrap {
            width: 88px; height: 88px; border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;
            font-size: 42px; box-shadow: 0 12px 32px rgba(217,119,6,.3);
        }
        h1 { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 12px; }
        p { font-size: 15px; color: var(--muted); line-height: 1.7; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">⚠️</div>
        <h1>Este link não é mais válido</h1>
        <p>
            O link do Portal de Admissão expirou ou não existe. Entre em contato com a
            secretaria da escola para receber um novo link de acesso.
        </p>
    </div>
</body>
</html>
