<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Dados Confirmados! | Torre360</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --primary: #4f46e5; --text: #1e293b; --muted: #64748b; }
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
            width: 96px; height: 96px; border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex; align-items: center; justify-content: center; margin: 0 auto 28px;
            font-size: 46px; box-shadow: 0 12px 32px rgba(16,185,129,.3);
        }
        h1 { font-size: 26px; font-weight: 800; color: var(--text); margin-bottom: 12px; }
        p { font-size: 15px; color: var(--muted); line-height: 1.7; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">✓</div>
        <h1>Dados confirmados com sucesso!</h1>
        <p>
            Obrigado por completar as informações. Nossa secretaria já foi avisada e vai
            entrar em contato para finalizar a matrícula.
        </p>
    </div>
</body>
</html>
