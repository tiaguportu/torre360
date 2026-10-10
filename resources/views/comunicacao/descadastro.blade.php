<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $concluido ? 'Cancelamento confirmado' : 'Cancelar e-mails' }} | Torre360</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(145deg, #312e81 0%, #4f46e5 50%, #6366f1 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #1e293b;
        }
        .card { background: #fff; border-radius: 24px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 32px 64px -12px rgba(0,0,0,.25); }
        h1 { font-size: 22px; font-weight: 800; margin-bottom: 12px; }
        p { font-size: 15px; color: #64748b; line-height: 1.7; margin-bottom: 24px; }
        button { width: 100%; padding: 14px 24px; border: 0; border-radius: 12px; font-size: 15px; font-weight: 600; background: #4f46e5; color: #fff; cursor: pointer; }
        button:hover { background: #4338ca; }
    </style>
</head>
<body>
    <main class="card">
        @if($concluido)
            <h1>Pronto{{ $primeiroNome ? ', '.$primeiroNome : '' }}!</h1>
            <p>Você não receberá mais e-mails de acompanhamento da escola. Se um dia quiser retomar o contato, é só preencher o formulário de interesse de novo ou falar com a secretaria.</p>
        @elseif($ja_descadastrada)
            <h1>Você já cancelou</h1>
            <p>Este endereço já está fora da lista de e-mails de acompanhamento da escola. Não é preciso fazer mais nada.</p>
        @else
            <h1>Cancelar e-mails da escola?</h1>
            <p>Deixaremos de enviar lembretes e mensagens de acompanhamento sobre o seu interesse na escola. Avisos individuais obrigatórios, como os de uma matrícula em andamento, continuam chegando.</p>
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                <button type="submit">Sim, cancelar o envio</button>
            </form>
        @endif
    </main>
</body>
</html>
