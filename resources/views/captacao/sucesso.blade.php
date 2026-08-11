<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
    <title>Inscrição Enviada com Sucesso! | Torre360</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:    #4f46e5;
            --primary-dk: #3730a3;
            --success:    #10b981;
            --text:       #1e293b;
            --muted:      #64748b;
            --border:     #e2e8f0;
            --bg:         #f1f5f9;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #312e81 0%, #4f46e5 50%, #6366f1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 56px 52px;
            max-width: 580px;
            width: 100%;
            text-align: center;
            box-shadow: 0 32px 64px -12px rgba(0,0,0,0.25);
            animation: slideUp .5s cubic-bezier(.16,1,.3,1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(32px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .icon-wrap {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 28px;
            font-size: 46px;
            box-shadow: 0 12px 32px rgba(16,185,129,0.3);
            animation: pop .6s cubic-bezier(.36,.07,.19,.97) .2s both;
        }
        @keyframes pop {
            0%  { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.12); }
            100%{ transform: scale(1); opacity: 1; }
        }

        h1 {
            font-size: 30px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
        }
        .nome-destaque {
            color: var(--primary);
        }
        .subtitulo {
            font-size: 16px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 32px;
        }

        /* ── Timeline de próximos passos ── */
        .timeline {
            text-align: left;
            margin: 0 0 32px;
            padding: 24px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .timeline-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 16px;
        }
        .timeline-steps {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .timeline-step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            position: relative;
            padding-bottom: 18px;
        }
        .timeline-step:last-child { padding-bottom: 0; }
        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 18px;
            top: 38px;
            width: 2px;
            height: calc(100% - 38px);
            background: var(--border);
        }
        .step-dot {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            position: relative; z-index: 1;
        }
        .step-dot.done    { background: #dcfce7; }
        .step-dot.active  { background: #ede9fe; box-shadow: 0 0 0 4px rgba(79,70,229,.12); }
        .step-dot.pending { background: #f1f5f9; }
        .step-info { padding-top: 6px; }
        .step-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }
        .step-desc {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Botões de ação ── */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 28px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all .2s;
            border: none;
            font-family: inherit;
            width: 100%;
        }
        .btn-whatsapp {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: #fff;
            box-shadow: 0 4px 16px rgba(37,211,102,.3);
        }
        .btn-whatsapp:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,211,102,.45); }
        .btn-ghost {
            background: transparent;
            border: 1.5px solid var(--border);
            color: var(--muted);
        }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: 12px;
            margin: 4px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        @media (max-width: 520px) {
            .card { padding: 40px 24px 36px; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">✓</div>

        <h1>
            @if($nomeResponsavel)
                <span class="nome-destaque">{{ explode(' ', $nomeResponsavel)[0] }}</span>, inscrição enviada!
            @else
                Inscrição enviada!
            @endif
        </h1>

        <p class="subtitulo">
            Recebemos seu interesse com sucesso. Nossa equipe vai analisar as informações
            e entrar em contato em breve para os próximos passos.
        </p>

        {{-- Timeline de próximos passos --}}
        <div class="timeline">
            <div class="timeline-title">📋 O que acontece agora?</div>
            <div class="timeline-steps">
                <div class="timeline-step">
                    <div class="step-dot done">✅</div>
                    <div class="step-info">
                        <div class="step-name">Inscrição recebida</div>
                        <div class="step-desc">Acabamos de receber seus dados e você receberá um e-mail de confirmação.</div>
                    </div>
                </div>
                <div class="timeline-step">
                    <div class="step-dot active">📞</div>
                    <div class="step-info">
                        <div class="step-name">Análise e primeiro contato</div>
                        <div class="step-desc">Nossa equipe entrará em contato pelo WhatsApp/telefone em até 24 horas úteis.</div>
                    </div>
                </div>
                <div class="timeline-step">
                    <div class="step-dot pending">🏫</div>
                    <div class="step-info">
                        <div class="step-name">Visita à escola</div>
                        <div class="step-desc">Agendaremos uma visita para que você conheça nossa estrutura e equipe.</div>
                    </div>
                </div>
                <div class="timeline-step">
                    <div class="step-dot pending">🎓</div>
                    <div class="step-info">
                        <div class="step-name">Efetivação da matrícula</div>
                        <div class="step-desc">Com a documentação em mãos, garantimos a vaga do(a) aluno(a).</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ações --}}
        <div class="actions">
            @if($whatsappUnidade)
                @php
                    $numero = preg_replace('/\D/', '', $whatsappUnidade);
                    $mensagem = urlencode('Olá! Acabei de preencher o formulário de interesse' . ($nomeResponsavel ? ' ('. $nomeResponsavel .')' : '') . ' e gostaria de saber mais sobre a escola.');
                @endphp
                <a href="https://wa.me/55{{ $numero }}?text={{ $mensagem }}" target="_blank" class="btn btn-whatsapp">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Falar pelo WhatsApp
                    @if($nomeUnidade)
                        — {{ $nomeUnidade }}
                    @endif
                </a>
                <div class="divider">ou</div>
            @endif

            <a href="{{ route('captacao.interessado.show') }}" class="btn btn-ghost">
                ← Fazer outra inscrição
            </a>
        </div>
    </div>
</body>
</html>
