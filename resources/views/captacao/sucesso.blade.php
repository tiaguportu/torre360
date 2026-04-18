<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição Enviada com Sucesso!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f1a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #16162a;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px;
            padding: 56px 48px;
            max-width: 540px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,.5);
        }
        .icon-wrap {
            width: 88px; height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 28px;
            font-size: 42px;
            box-shadow: 0 8px 32px rgba(16,185,129,.4);
            animation: pop .5s cubic-bezier(.36,.07,.19,.97);
        }
        @keyframes pop {
            0%   { transform: scale(0); opacity:0; }
            70%  { transform: scale(1.12); }
            100% { transform: scale(1); opacity:1; }
        }
        h1 {
            font-size: 28px; font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #fff, #a5b4fc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        p {
            font-size: 16px; color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 16px;
        }
        .highlight {
            background: rgba(79,70,229,.15);
            border: 1px solid rgba(99,102,241,.3);
            border-radius: 10px;
            padding: 16px 20px;
            font-size: 14px;
            color: #a5b4fc;
            margin: 20px 0 32px;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 28px;
            border-radius: 10px;
            font-size: 15px; font-weight: 600;
            text-decoration: none;
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            color: #fff;
            box-shadow: 0 4px 16px rgba(79,70,229,.4);
            transition: all .2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,.5); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">✓</div>
        <h1>Inscrição enviada!</h1>
        <p>Recebemos seu interesse com sucesso. Nossa equipe vai analisar as informações e entrar em contato em breve.</p>

        <div class="highlight">
            📞 Fique de olho no seu telefone e e-mail. Entraremos em contato para apresentar nossa escola e agendar uma visita.
        </div>

        <a href="{{ route('captacao.interessado.show') }}" class="btn">
            ← Fazer outra inscrição
        </a>
    </div>
</body>
</html>
