<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Obrigado pelo seu feedback! | {{ config('app.name', 'Torre360') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #4f46e5;
            --primary-dk: #3730a3;
            --text: #1e293b;
            --muted: #64748b;
            --success: #10b981;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%);
            min-height: 100vh;
            padding: 24px 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text);
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 48px 36px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        }
        .icone {
            font-size: 54px;
            margin-bottom: 20px;
            display: inline-block;
            animation: bounce 1.5s infinite alternate;
        }
        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-8px); }
        }
        h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .resumo-nps {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 28px;
            font-size: 14px;
            color: var(--text);
        }
        .resumo-nps strong {
            color: var(--primary);
            font-size: 18px;
        }
        .btn-contato {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            transition: background 0.2s;
        }
        .btn-contato:hover {
            background: var(--primary-dk);
        }
    </style>
</head>
<body>
    @php
        $responsavel = $pesquisa->interessado?->pessoa;
        $primeiroNome = explode(' ', trim($responsavel?->nome ?? 'Família'))[0];
        $escolaNome = \App\Models\Unidade::first()?->nome ?? \App\Models\InstituicaoEnsino::first()?->nome ?? 'Nossa Escola';
    @endphp

    <div class="card">
        <div class="icone">✨</div>
        <h1>Muito obrigado, {{ $primeiroNome }}!</h1>
        <p>Sua avaliação sobre o tour escolar no <strong>{{ $escolaNome }}</strong> foi registrada com sucesso. Ela é fundamental para acolhermos sua família com toda a dedicação e carinho.</p>

        @if($pesquisa->nota_nps !== null)
            <div class="resumo-nps">
                Sua nota de recomendação registrada: <strong>{{ $pesquisa->nota_nps }} / 10 ⭐</strong>
            </div>
        @endif

        <p style="font-size: 13px; margin-bottom: 0;">Ficou com alguma dúvida sobre matrículas ou séries? Nossa equipe de admissões está à sua inteira disposição.</p>
    </div>
</body>
</html>
