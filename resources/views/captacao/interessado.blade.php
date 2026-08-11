<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manifeste seu interesse em matricular seu filho(a) no Torre360. Excelência em educação e gestão.">

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">

    {{-- Open Graph / WhatsApp --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="Torre360">
    <meta property="og:title" content="Torre360 - Quero uma Vaga">
    <meta property="og:description" content="Manifeste seu interesse em matricular seu filho(a) em nossa escola. Preencha o formulário e nossa equipe entrará em contato.">
    <meta property="og:image" content="{{ asset('img/preview.png') }}?v=2">
    <meta property="og:image:secure_url" content="{{ asset('img/preview.png') }}?v=2">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Torre360 - Quero uma Vaga">
    <meta name="twitter:description" content="Manifeste seu interesse em matricular seu filho(a).">
    <meta name="twitter:image" content="{{ asset('img/preview.png') }}?v=2">

    <title>Quero uma Vaga – Inscrição de Interesse | Torre360</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- reCAPTCHA v3 --}}
    @if(config('services.recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
    @endif

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:     #4f46e5;
            --primary-dk:  #3730a3;
            --primary-lt:  #6366f1;
            --accent:      #0ea5e9;
            --success:     #10b981;
            --danger:      #ef4444;
            --warning:     #f59e0b;
            --bg:          #f1f5f9;
            --card:        #ffffff;
            --card-border: #e2e8f0;
            --text:        #1e293b;
            --muted:       #64748b;
            --input-bg:    #ffffff;
            --input-bd:    #cbd5e1;
            --radius:      14px;
            --shadow:      0 20px 40px -8px rgba(0,0,0,0.12), 0 8px 16px -4px rgba(0,0,0,0.06);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── HERO ── */
        .hero {
            width: 100%;
            background: linear-gradient(145deg, #312e81 0%, #4f46e5 50%, #6366f1 100%);
            padding: 72px 24px 90px;
            text-align: center;
            position: relative;
            overflow: hidden;
            color: #fff;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 60%, rgba(99,102,241,0.4) 0%, transparent 60%),
                        radial-gradient(ellipse at 70% 20%, rgba(139,92,246,0.3) 0%, transparent 55%);
        }
        .hero-logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 4px 16px rgba(0,0,0,0.3));
        }
        .hero h1 {
            font-size: clamp(26px, 5vw, 44px);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -.02em;
            position: relative;
            z-index: 2;
            color: #fff;
        }
        .hero p {
            margin-top: 10px;
            font-size: 17px;
            color: rgba(255,255,255,.8);
            position: relative;
            z-index: 2;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero-badges {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        .hero-badge {
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.3);
            backdrop-filter: blur(8px);
            border-radius: 100px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,.95);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── PROGRESS ── */
        .progress-wrap {
            width: 100%;
            max-width: 820px;
            padding: 32px 24px 0;
        }
        .progress-bar-track {
            height: 4px;
            background: var(--card-border);
            border-radius: 99px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-lt));
            border-radius: 99px;
            transition: width .5s cubic-bezier(.4,0,.2,1);
        }
        .steps-track {
            display: flex;
            align-items: flex-start;
            gap: 0;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 18px;
            left: calc(50% + 18px);
            width: calc(100% - 36px);
            height: 2px;
            background: var(--card-border);
            transition: background .4s;
        }
        .step-item.done:not(:last-child)::after { background: var(--success); }
        .step-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--card);
            border: 2px solid var(--card-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            transition: all .35s;
            position: relative; z-index: 1;
            color: var(--muted);
        }
        .step-item.active .step-circle {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 5px rgba(79,70,229,.2);
            color: #fff;
        }
        .step-item.done .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }
        .step-label {
            margin-top: 7px;
            font-size: 10px;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .step-item.active .step-label { color: var(--primary); }
        .step-item.done .step-label   { color: var(--success); }

        /* ── CARD ── */
        .form-card {
            width: 100%;
            max-width: 820px;
            margin: 24px 24px 60px;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ── STEP PANELS ── */
        .step-panel {
            display: none;
            padding: 40px 44px 36px;
            animation: fadeUp .3s ease;
        }
        .step-panel.active { display: block; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

        .step-panel h2 {
            font-size: 22px; font-weight: 800;
            margin-bottom: 4px;
            color: var(--text);
        }
        .step-panel .subtitle {
            font-size: 14px; color: var(--muted);
            margin-bottom: 28px;
            line-height: 1.6;
        }

        /* ── CHOICE CARDS ── */
        .choice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 8px;
        }
        .choice-card {
            border: 2px solid var(--card-border);
            border-radius: var(--radius);
            padding: 28px 20px 24px;
            cursor: pointer;
            transition: all .25s;
            text-align: center;
            background: var(--input-bg);
        }
        .choice-card:hover { border-color: var(--primary-lt); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,.12); }
        .choice-card.selected {
            border-color: var(--primary);
            background: rgba(79,70,229,.08);
            box-shadow: 0 0 0 4px rgba(79,70,229,.12);
        }
        .choice-card .icon {
            font-size: 36px;
            margin-bottom: 14px;
            display: block;
        }
        .choice-card .label { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
        .choice-card .desc  { font-size: 12px; color: var(--muted); }
        .choice-card input  { display: none; }

        /* ── FORM FIELDS ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid.col-1 { grid-template-columns: 1fr; }
        .form-grid.col-3 { grid-template-columns: 1fr 1fr 1fr; }

        @media (max-width: 620px) {
            .step-panel { padding: 28px 20px 24px; }
            .form-grid, .form-grid.col-3 { grid-template-columns: 1fr; }
            .choice-grid { grid-template-columns: 1fr; }
        }

        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.span-2 { grid-column: span 2; }
        .field.span-3 { grid-column: span 3; }

        label {
            font-size: 12px; font-weight: 600;
            color: var(--muted);
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        label .req { color: var(--danger); margin-left: 2px; }

        .input-wrap { position: relative; }
        .input-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            pointer-events: none;
        }
        .input-wrap input,
        .input-wrap select { padding-left: 40px; }
        .input-valid-badge {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            display: none;
            animation: popIn .2s ease;
        }
        @keyframes popIn { from{transform:translateY(-50%) scale(0)} to{transform:translateY(-50%) scale(1)} }
        .input-valid-badge.show { display: block; }

        input[type=text], input[type=email], input[type=date], input[type=tel],
        select, textarea {
            width: 100%;
            background: var(--input-bg);
            border: 1.5px solid var(--input-bd);
            border-radius: 10px;
            padding: 13px 14px;
            color: var(--text);
            font-size: 15px;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none;
            appearance: none;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,.18);
            background: #fafbff;
        }
        input.valid-field { border-color: var(--success); }
        input.invalid-field { border-color: var(--danger); }
        select option { background: #ffffff; }
        textarea { resize: vertical; min-height: 90px; }

        .field-hint { font-size: 11px; color: var(--muted); margin-top: 3px; }

        /* ── ALUNO CARD ── */
        .aluno-item {
            background: #f8fafc;
            border: 1.5px solid var(--card-border);
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 16px;
            position: relative;
            transition: border-color .2s;
        }
        .aluno-item:hover { border-color: #c7d2fe; }
        .aluno-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-remover {
            position: absolute;
            top: 16px; right: 16px;
            background: none;
            border: 1px solid rgba(239,68,68,.3);
            color: var(--danger);
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 6px;
            padding: 4px 10px;
            transition: all .2s;
        }
        .btn-remover:hover { background: rgba(239,68,68,.08); }

        .btn-add-aluno {
            width: 100%;
            padding: 14px;
            border: 2px dashed var(--card-border);
            border-radius: 12px;
            background: transparent;
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
            margin-top: 4px;
        }
        .btn-add-aluno:hover {
            border-color: var(--primary);
            background: rgba(79,70,229,.05);
        }

        /* ── TURNO PILLS ── */
        .turno-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 2px;
        }
        .turno-pill {
            border: 1.5px solid var(--card-border);
            border-radius: 8px;
            padding: 10px 6px;
            cursor: pointer;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            transition: all .2s;
            color: var(--muted);
            background: var(--input-bg);
        }
        .turno-pill:hover { border-color: var(--primary-lt); color: var(--primary); }
        .turno-pill.selected {
            border-color: var(--primary);
            background: rgba(79,70,229,.1);
            color: var(--primary);
        }
        .turno-pill input { display: none; }
        .turno-pill .emoji { font-size: 18px; display: block; margin-bottom: 4px; }

        @media (max-width: 480px) { .turno-grid { grid-template-columns: repeat(2, 1fr); } }

        /* ── FOOTER ── */
        .step-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 44px;
            border-top: 1px solid var(--card-border);
            gap: 12px;
            background: #fafafa;
        }
        @media (max-width: 620px) { .step-footer { padding: 16px 20px; } }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all .2s;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dk));
            color: #fff;
            box-shadow: 0 4px 16px rgba(79,70,229,.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,.5); }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .btn-ghost {
            background: transparent;
            border: 1.5px solid var(--card-border);
            color: var(--muted);
        }
        .btn-ghost:hover { border-color: var(--primary-lt); color: var(--text); }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 4px 16px rgba(16,185,129,.3);
            padding: 13px 36px;
        }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16,185,129,.45); }

        /* ── RESUMO (ETAPA 4) ── */
        .resumo-bloco {
            background: #f8fafc;
            border: 1.5px solid var(--card-border);
            border-radius: 12px;
            padding: 22px 24px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.9;
        }
        .resumo-titulo {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--primary);
            margin-bottom: 10px;
        }

        /* ── ALERTS ── */
        .alert {
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-error { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.3); color: #dc2626; }
        .alert ul { margin: 8px 0 0 16px; }

        /* ── SPINNER ── */
        .spinner {
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── reCAPTCHA ── */
        .grecaptcha-badge { opacity: .6; transition: opacity .3s; }
        .grecaptcha-badge:hover { opacity: 1; }
        .recap-notice {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
            margin-top: 16px;
            line-height: 1.7;
        }
        .recap-notice a { color: var(--primary-lt); text-decoration: none; }

        /* Responsivo */
        @media (max-width: 480px) {
            .progress-wrap { padding: 20px 16px 0; }
            .form-card { margin: 16px 12px 40px; }
            .step-label { display: none; }
        }
    </style>
</head>
<body>

    {{-- ── HERO ── --}}
    <header class="hero" id="form-anchor">
        <img src="{{ asset('logo-adaptative.svg') }}" alt="Torre360 Logo" class="hero-logo">
        <h1>Manifeste seu Interesse</h1>
        <p>Preencha o formulário em menos de 2 minutos e nossa equipe entrará em contato para agendar uma visita.</p>
        <div class="hero-badges">
            <span class="hero-badge">✅ Gratuito e sem compromisso</span>
            <span class="hero-badge">📲 Retorno em até 24h</span>
            <span class="hero-badge">🔒 Dados protegidos (LGPD)</span>
        </div>
    </header>

    {{-- ── PROGRESSO ── --}}
    <div class="progress-wrap">
        <div class="progress-bar-track">
            <div class="progress-bar-fill" id="progressFill" style="width: 25%"></div>
        </div>
        <div class="steps-track">
            <div class="step-item active" id="step-indicator-1">
                <div class="step-circle">1</div>
                <div class="step-label">Quem preenche</div>
            </div>
            <div class="step-item" id="step-indicator-2">
                <div class="step-circle">2</div>
                <div class="step-label">Seus Dados</div>
            </div>
            <div class="step-item" id="step-indicator-3">
                <div class="step-circle">3</div>
                <div class="step-label">Aluno(s)</div>
            </div>
            <div class="step-item" id="step-indicator-4">
                <div class="step-circle">4</div>
                <div class="step-label">Confirmação</div>
            </div>
        </div>
    </div>

    {{-- ── CARD PRINCIPAL ── --}}
    <div class="form-card">

        @if($errors->any())
            <div style="padding: 24px 44px 0">
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <div>
                        <strong>Verifique os campos abaixo:</strong>
                        <ul>
                            @foreach($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('captacao.interessado.store') }}" id="formCaptacao" novalidate>
            @csrf
            <input type="hidden" name="recaptcha_token" id="recaptchaToken">

            {{-- ════════════════════════════════════════
                 ETAPA 1 — Quem está preenchendo?
            ════════════════════════════════════════ --}}
            <div class="step-panel active" id="panel-1">
                <h2>Olá! Bem-vindo(a) 👋</h2>
                <p class="subtitle">Para começar, nos diga: quem está preenchendo este formulário?</p>

                <div class="choice-grid">
                    <label class="choice-card" id="card-proprio" for="tipo_proprio">
                        <span class="icon">🎓</span>
                        <div class="label">Sou o próprio aluno</div>
                        <div class="desc">Estou me inscrevendo por conta própria</div>
                        <input type="radio" id="tipo_proprio" name="tipo_preenchimento" value="proprio" {{ old('tipo_preenchimento') === 'proprio' ? 'checked' : '' }}>
                    </label>

                    <label class="choice-card" id="card-responsavel" for="tipo_responsavel">
                        <span class="icon">👨‍👩‍👧</span>
                        <div class="label">Sou pai, mãe ou responsável</div>
                        <div class="desc">Estou inscrevendo meu filho(a)</div>
                        <input type="radio" id="tipo_responsavel" name="tipo_preenchimento" value="responsavel" {{ old('tipo_preenchimento', 'responsavel') === 'responsavel' ? 'checked' : '' }}>
                    </label>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 ETAPA 2 — Dados de Contato
            ════════════════════════════════════════ --}}
            <div class="step-panel" id="panel-2">
                <h2>Seus Dados de Contato</h2>
                <p class="subtitle" id="subtitle-contato">Informe seus dados para que possamos entrar em contato rapidamente.</p>

                {{-- Responsável --}}
                <div id="bloco-responsavel">
                    <div class="form-grid" style="margin-bottom:18px">
                        <div class="field span-2">
                            <label for="responsavel_nome">Nome completo <span class="req">*</span></label>
                            <input type="text" id="responsavel_nome" name="responsavel_nome"
                                   value="{{ old('responsavel_nome') }}"
                                   placeholder="Ex.: Maria da Silva"
                                   autocomplete="name">
                        </div>

                        <div class="field">
                            <label for="responsavel_cpf">CPF <span style="font-weight:400;text-transform:none;font-size:11px">(opcional)</span></label>
                            <input type="text" id="responsavel_cpf" name="responsavel_cpf"
                                   value="{{ old('responsavel_cpf') }}"
                                   placeholder="000.000.000-00" maxlength="14"
                                   autocomplete="off">
                        </div>

                        <div class="field">
                            {{-- preenchido via JS --}}
                        </div>
                    </div>
                </div>

                {{-- Contato (para todos) --}}
                <div class="form-grid" style="margin-bottom:18px">
                    <div class="field">
                        <label for="responsavel_telefone">WhatsApp / Telefone <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon">📲</span>
                            <input type="tel" id="responsavel_telefone" name="responsavel_telefone"
                                   value="{{ old('responsavel_telefone') }}"
                                   placeholder="(00) 9 0000-0000"
                                   autocomplete="tel">
                            <span class="input-valid-badge" id="telBadge">✅</span>
                        </div>
                        <span class="field-hint">Preferimos contatar pelo WhatsApp.</span>
                    </div>

                    <div class="field">
                        <label for="responsavel_email">E-mail <span class="req">*</span></label>
                        <div class="input-wrap">
                            <span class="input-icon">✉️</span>
                            <input type="email" id="responsavel_email" name="responsavel_email"
                                   value="{{ old('responsavel_email') }}"
                                   placeholder="email@exemplo.com"
                                   autocomplete="email">
                            <span class="input-valid-badge" id="emailBadge">✅</span>
                        </div>
                    </div>
                </div>

                <div class="form-grid col-1">
                    <div class="field">
                        <label for="como_conheceu">Como conheceu nossa escola?</label>
                        <select id="como_conheceu" name="como_conheceu">
                            <option value="">Prefiro não informar</option>
                            @foreach(\App\Models\OrigemInteressado::orderBy('nome')->get() as $origem)
                                <option value="{{ $origem->id }}" {{ old('como_conheceu') == $origem->id ? 'selected' : '' }}>
                                    {{ $origem->nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 ETAPA 3 — Dados do(s) Aluno(s)
            ════════════════════════════════════════ --}}
            <div class="step-panel" id="panel-3">
                <h2>Dados do(s) Aluno(s)</h2>
                <p class="subtitle">Informe os dados de quem irá estudar conosco. Pode adicionar mais de um aluno (ex.: irmãos).</p>

                <div id="alunos-container">
                    {{-- O primeiro aluno já vem fixo --}}
                    <div class="aluno-item" id="aluno-0">
                        <div class="aluno-label">👤 Aluno #1</div>

                        <div class="form-grid" style="margin-bottom:14px">
                            <div class="field span-2">
                                <label>Nome completo do aluno <span class="req">*</span></label>
                                <input type="text" name="alunos[0][nome]" placeholder="Nome completo do(a) aluno(a)" required>
                            </div>

                            <div class="field">
                                <label>Data de nascimento</label>
                                <input type="date" name="alunos[0][data_nascimento]" max="{{ now()->toDateString() }}">
                            </div>

                            <div class="field" id="bloco-vinculo-0">
                                <label>Vínculo com o responsável <span class="req">*</span></label>
                                <select name="alunos[0][vinculo]" required>
                                    <option value="">Selecione...</option>
                                    <option value="Pai">Filho(a) — Sou o Pai</option>
                                    <option value="Mãe">Filho(a) — Sou a Mãe</option>
                                    <option value="Parente">Parente</option>
                                    <option value="Tutor">Tutor(a)</option>
                                    <option value="Próprio">Próprio aluno</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid" style="margin-bottom:18px">
                            <div class="field">
                                <label>Unidade de interesse</label>
                                <select name="alunos[0][unidade_id]" class="unidade-select" onchange="filtrarSeries(this)">
                                    <option value="">Selecione a Unidade...</option>
                                    @foreach($unidades as $u)
                                        <option value="{{ $u->id }}" {{ ($unidades->count() === 1 || old('alunos.0.unidade_id') == $u->id) ? 'selected' : '' }}>{{ $u->nome }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Série / Ano pretendido</label>
                                <select name="alunos[0][serie_id]" class="serie-select">
                                    <option value="">Selecione primeiro a unidade...</option>
                                    @foreach($series as $s)
                                        <option value="{{ $s->id }}"
                                            data-curso-id="{{ $s->curso_id }}"
                                            data-unidade-id="{{ $s->curso?->unidade_id }}"
                                            style="display:none">
                                            {{ $s->nome }}{{ $s->idade_minima ? ' (a partir de '.$s->idade_minima.' anos)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="field-hint">Não sabe? Deixe em branco e nossa equipe orientará.</span>
                            </div>
                        </div>

                        <div class="field">
                            <label>Turno de preferência</label>
                            <div class="turno-grid">
                                @foreach([['🌅','Manhã'],['☀️','Tarde'],['🌇','Integral'],['🤷','Sem preferência']] as [$emoji, $turno])
                                    <label class="turno-pill" onclick="selecionarTurno(this, 0)">
                                        <span class="emoji">{{ $emoji }}</span>
                                        {{ $turno }}
                                        <input type="radio" name="alunos[0][turno_preferencia]" value="{{ $turno }}">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-add-aluno" onclick="adicionarAluno()">
                    ➕ Adicionar outro aluno
                </button>
            </div>

            {{-- ════════════════════════════════════════
                 ETAPA 4 — Confirmação + Observações
            ════════════════════════════════════════ --}}
            <div class="step-panel" id="panel-4">
                <h2>Revise e Envie 🚀</h2>
                <p class="subtitle">Confira as informações e adicione qualquer observação antes de enviar.</p>

                <div id="resumo">
                    {{-- Preenchido via JS --}}
                </div>

                <div class="form-grid col-1" style="margin-bottom:16px">
                    <div class="field">
                        <label for="observacoes">Observações adicionais <span style="font-weight:400;text-transform:none;font-size:11px">(opcional)</span></label>
                        <textarea id="observacoes" name="observacoes"
                                  placeholder="Alguma necessidade especial, dúvida ou informação que gostaria de compartilhar com nossa equipe?">{{ old('observacoes') }}</textarea>
                    </div>
                </div>

                <p class="recap-notice">
                    Este site é protegido pelo reCAPTCHA. Aplicam-se a
                    <a href="https://policies.google.com/privacy" target="_blank">Política de Privacidade</a>
                    e os
                    <a href="https://policies.google.com/terms" target="_blank">Termos de Serviço</a>
                    do Google.
                </p>
            </div>

            {{-- ── RODAPÉ DA NAVEGAÇÃO ── --}}
            <div class="step-footer">
                <button type="button" class="btn btn-ghost" id="btnVoltar" style="display:none" onclick="voltarEtapa()">
                    ← Voltar
                </button>
                <div style="flex:1"></div>
                <button type="button" class="btn btn-primary" id="btnProximo" onclick="avancarEtapa()">
                    Próximo →
                </button>
                <button type="submit" class="btn btn-success" id="btnEnviar" style="display:none">
                    <span id="btnEnviarTexto">✓ Enviar Inscrição</span>
                    <span class="spinner" id="btnSpinner"></span>
                </button>
            </div>
        </form>
    </div>

    <script>
    (() => {
        let etapaAtual = 1;
        const totalEtapas = 4;

        // ── Helpers ──────────────────────────────────
        const $ = (sel) => document.querySelector(sel);
        const $$ = (sel) => document.querySelectorAll(sel);

        function getTipo() {
            const el = document.querySelector('input[name="tipo_preenchimento"]:checked');
            return el ? el.value : 'responsavel';
        }

        // ── Indicadores de progresso ─────────────────
        function atualizarIndicadores() {
            const pct = Math.round((etapaAtual / totalEtapas) * 100);
            $('#progressFill').style.width = pct + '%';

            for (let i = 1; i <= totalEtapas; i++) {
                const ind  = $(`#step-indicator-${i}`);
                const circ = ind.querySelector('.step-circle');
                ind.classList.remove('active', 'done');
                if (i < etapaAtual) {
                    ind.classList.add('done');
                    circ.textContent = '✓';
                } else {
                    circ.textContent = i;
                    if (i === etapaAtual) ind.classList.add('active');
                }
            }
        }

        function mostrarEtapa(n) {
            $$('.step-panel').forEach(p => p.classList.remove('active'));
            $(`#panel-${n}`).classList.add('active');

            $('#btnVoltar').style.display  = n > 1             ? 'inline-flex' : 'none';
            $('#btnProximo').style.display = n < totalEtapas   ? 'inline-flex' : 'none';
            $('#btnEnviar').style.display  = n === totalEtapas ? 'inline-flex' : 'none';

            if (n === totalEtapas) construirResumo();
            atualizarIndicadores();

            // Scroll para o início do form
            const progressWrap = document.querySelector('.progress-wrap');
            if (progressWrap && n > 1) {
                progressWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        // ── Validação ────────────────────────────────
        function showError(el, msg) {
            el.focus();
            el.classList.add('invalid-field');
            alert(msg);
            return false;
        }

        function validarEtapa(n) {
            const tipo = getTipo();

            if (n === 1) {
                if (!tipo) { alert('Selecione uma opção para continuar.'); return false; }
                return true;
            }

            if (n === 2) {
                if (tipo === 'responsavel') {
                    const nome = $('#responsavel_nome');
                    if (!nome.value.trim()) return showError(nome, 'Informe seu nome completo.');
                }
                const tel   = $('#responsavel_telefone');
                const email = $('#responsavel_email');
                if (!tel.value.trim())                 return showError(tel, 'Informe o WhatsApp / telefone de contato.');
                if (!email.value.trim() || !email.value.includes('@')) return showError(email, 'Informe um e-mail válido.');
                return true;
            }

            if (n === 3) {
                const primeiroNome = document.querySelector('input[name="alunos[0][nome]"]');
                if (!primeiroNome || !primeiroNome.value.trim()) {
                    if (primeiroNome) primeiroNome.focus();
                    alert('Informe ao menos o nome do primeiro aluno.');
                    return false;
                }
                return true;
            }

            return true;
        }

        // ── Navegação ────────────────────────────────
        window.avancarEtapa = function () {
            if (!validarEtapa(etapaAtual)) return;
            if (etapaAtual < totalEtapas) {
                etapaAtual++;
                mostrarEtapa(etapaAtual);
            }
        };

        window.voltarEtapa = function () {
            if (etapaAtual > 1) {
                etapaAtual--;
                mostrarEtapa(etapaAtual);
            }
        };

        // ── Repeater Alunos ──────────────────────────
        let alunoIdx = 1;

        // Dados de séries por unidade
        const seriesData = [
            @foreach($series as $s)
            { id: "{{ $s->id }}", nome: "{{ $s->nome }}{{ $s->idade_minima ? ' (a partir de '.$s->idade_minima.' anos)' : '' }}", unidade_id: "{{ $s->curso?->unidade_id }}" },
            @endforeach
        ];

        window.filtrarSeries = function(selectUnidade) {
            const unidadeId   = selectUnidade.value;
            const alunoItem   = selectUnidade.closest('.aluno-item');
            const selectSerie = alunoItem.querySelector('.serie-select');

            selectSerie.innerHTML = '<option value="">Sem preferência (nossa equipe orienta)</option>';

            if (!unidadeId) {
                selectSerie.innerHTML = '<option value="">Selecione primeiro a unidade...</option>';
                selectSerie.disabled = true;
                return;
            }

            const filtradas = seriesData.filter(s => s.unidade_id === unidadeId);

            if (filtradas.length > 0) {
                filtradas.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.nome;
                    selectSerie.appendChild(opt);
                });
                selectSerie.disabled = false;
            } else {
                selectSerie.innerHTML = '<option value="">Nenhuma série nesta unidade</option>';
                selectSerie.disabled = true;
            }
        };

        window.selecionarTurno = function(pill, idx) {
            // Desmarca todos os pills do mesmo aluno
            const alunoItem = pill.closest('.aluno-item');
            alunoItem.querySelectorAll('.turno-pill').forEach(p => p.classList.remove('selected'));
            pill.classList.add('selected');
        };

        window.adicionarAluno = function() {
            const container = $('#alunos-container');
            const tipoAtual = getTipo();
            const opcoesVinculo = tipoAtual === 'proprio'
                ? `<option value="Próprio">Próprio aluno</option>`
                : `<option value="">Selecione...</option>
                   <option value="Pai">Filho(a) — Sou o Pai</option>
                   <option value="Mãe">Filho(a) — Sou a Mãe</option>
                   <option value="Parente">Parente</option>
                   <option value="Tutor">Tutor(a)</option>`;

            const unidadesOpts = `<option value="">Selecione a Unidade...</option>`
                + @json($unidades)->map(u => `<option value="${u.id}">${u.nome}</option>`).join('');

            const html = `
                <div class="aluno-item" id="aluno-${alunoIdx}" style="animation: fadeUp .3s ease;">
                    <div class="aluno-label">👤 Aluno #${alunoIdx + 1}</div>
                    <button type="button" class="btn-remover" onclick="removerAluno(${alunoIdx})">✕ Remover</button>

                    <div class="form-grid" style="margin-bottom:14px">
                        <div class="field span-2">
                            <label>Nome completo do aluno <span class="req">*</span></label>
                            <input type="text" name="alunos[${alunoIdx}][nome]" placeholder="Nome completo" required>
                        </div>
                        <div class="field">
                            <label>Data de nascimento</label>
                            <input type="date" name="alunos[${alunoIdx}][data_nascimento]" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Vínculo <span class="req">*</span></label>
                            <select name="alunos[${alunoIdx}][vinculo]" required>${opcoesVinculo}</select>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-bottom:18px">
                        <div class="field">
                            <label>Unidade de interesse</label>
                            <select name="alunos[${alunoIdx}][unidade_id]" class="unidade-select" onchange="filtrarSeries(this)">
                                ${unidadesOpts}
                            </select>
                        </div>
                        <div class="field">
                            <label>Série / Ano pretendido</label>
                            <select name="alunos[${alunoIdx}][serie_id]" class="serie-select" disabled>
                                <option value="">Selecione primeiro a unidade...</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label>Turno de preferência</label>
                        <div class="turno-grid">
                            <label class="turno-pill" onclick="selecionarTurno(this, ${alunoIdx})">
                                <span class="emoji">🌅</span>Manhã
                                <input type="radio" name="alunos[${alunoIdx}][turno_preferencia]" value="Manhã">
                            </label>
                            <label class="turno-pill" onclick="selecionarTurno(this, ${alunoIdx})">
                                <span class="emoji">☀️</span>Tarde
                                <input type="radio" name="alunos[${alunoIdx}][turno_preferencia]" value="Tarde">
                            </label>
                            <label class="turno-pill" onclick="selecionarTurno(this, ${alunoIdx})">
                                <span class="emoji">🌇</span>Integral
                                <input type="radio" name="alunos[${alunoIdx}][turno_preferencia]" value="Integral">
                            </label>
                            <label class="turno-pill" onclick="selecionarTurno(this, ${alunoIdx})">
                                <span class="emoji">🤷</span>Sem preferência
                                <input type="radio" name="alunos[${alunoIdx}][turno_preferencia]" value="Sem preferência">
                            </label>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);

            // Se unidade única, filtra automaticamente
            const novoSelectUnidade = document.querySelector(`#aluno-${alunoIdx} .unidade-select`);
            if (novoSelectUnidade && novoSelectUnidade.options.length === 2) {
                novoSelectUnidade.selectedIndex = 1;
                filtrarSeries(novoSelectUnidade);
            }

            alunoIdx++;
        };

        window.removerAluno = function(idx) {
            $(`#aluno-${idx}`)?.remove();
        };

        // ── Resumo (Etapa 4) ─────────────────────────
        function construirResumo() {
            const tipo = getTipo();
            const resumoDiv = $('#resumo');
            let html = '';

            // Bloco contato
            html += `<div class="resumo-bloco">
                <div class="resumo-titulo">📋 Dados de Contato</div>`;

            if (tipo === 'responsavel') {
                const nome = ($('#responsavel_nome')?.value || '').trim();
                if (nome) html += `<div><strong>Nome:</strong> ${nome}</div>`;
            }

            const tel   = ($('#responsavel_telefone')?.value || '').trim();
            const email = ($('#responsavel_email')?.value || '').trim();
            if (tel)   html += `<div><strong>📲 WhatsApp:</strong> ${tel}</div>`;
            if (email) html += `<div><strong>✉️ E-mail:</strong> ${email}</div>`;

            const comoConheceu = $('#como_conheceu');
            if (comoConheceu && comoConheceu.selectedIndex > 0) {
                html += `<div><strong>Como conheceu:</strong> ${comoConheceu.options[comoConheceu.selectedIndex].text}</div>`;
            }

            html += `</div>`;

            // Bloco alunos
            const alunos = document.querySelectorAll('.aluno-item');
            html += `<div class="resumo-bloco">
                <div class="resumo-titulo">🎓 Aluno(s) — ${alunos.length} inscrito(s)</div>`;

            alunos.forEach((item, i) => {
                const nome      = (item.querySelector('input[name*="[nome]"]')?.value || '').trim();
                const dataEl    = item.querySelector('input[name*="[data_nascimento]"]');
                const serieEl   = item.querySelector('select[name*="[serie_id]"]');
                const unidadeEl = item.querySelector('select[name*="[unidade_id]"]');
                const turnoPill = item.querySelector('.turno-pill.selected');

                html += `<div style="margin-bottom:10px;padding-bottom:10px;${i < alunos.length-1 ? 'border-bottom:1px solid var(--card-border)' : ''}">`;
                html += `<div style="font-weight:700;margin-bottom:4px">👤 ${nome || 'Aluno '+(i+1)}</div>`;

                if (unidadeEl && unidadeEl.selectedIndex > 0) {
                    html += `<div style="font-size:13px;color:var(--muted)">🏫 ${unidadeEl.options[unidadeEl.selectedIndex].text}</div>`;
                }
                if (serieEl && serieEl.selectedIndex > 0 && serieEl.value) {
                    html += `<div style="font-size:13px;color:var(--muted)">📚 ${serieEl.options[serieEl.selectedIndex].text}</div>`;
                }
                if (turnoPill) {
                    html += `<div style="font-size:13px;color:var(--muted)">⏰ Turno: ${turnoPill.textContent.trim()}</div>`;
                }
                if (dataEl && dataEl.value) {
                    const d = new Date(dataEl.value + 'T00:00:00');
                    html += `<div style="font-size:13px;color:var(--muted)">🎂 ${d.toLocaleDateString('pt-BR')}</div>`;
                }
                html += `</div>`;
            });

            html += `</div>`;

            resumoDiv.innerHTML = html;
        }

        // ── Envio com reCAPTCHA v3 ───────────────────
        $('#formCaptacao').addEventListener('submit', function (e) {
            e.preventDefault();

            const siteKey = '{{ config("services.recaptcha.site_key") }}';
            const btn     = $('#btnEnviar');
            const texto   = $('#btnEnviarTexto');
            const spinner = $('#btnSpinner');

            btn.disabled = true;
            texto.textContent = 'Enviando…';
            spinner.style.display = 'inline-block';

            const doSubmit = () => this.submit();

            if (siteKey && typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(() => {
                    grecaptcha.execute(siteKey, { action: 'captacao_interessado' })
                        .then(token => {
                            $('#recaptchaToken').value = token;
                            doSubmit();
                        });
                });
            } else {
                doSubmit();
            }
        });

        // ── Máscara CPF ──────────────────────────────
        $('#responsavel_cpf')?.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                 .replace(/\.(\d{3})(\d)/, '.$1-$2');
            this.value = v;
        });

        // ── Máscara Telefone + validação visual ──────
        $('#responsavel_telefone')?.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            if (v.length <= 10) {
                v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
            }
            this.value = v;

            const badge = $('#telBadge');
            const rawLen = this.value.replace(/\D/g, '').length;
            if (rawLen >= 10) {
                this.classList.add('valid-field');
                this.classList.remove('invalid-field');
                badge?.classList.add('show');
            } else {
                this.classList.remove('valid-field');
                badge?.classList.remove('show');
            }
        });

        // ── Validação visual e-mail ───────────────────
        $('#responsavel_email')?.addEventListener('blur', function () {
            const valid = this.value.includes('@') && this.value.includes('.');
            const badge = $('#emailBadge');
            if (valid) {
                this.classList.add('valid-field');
                this.classList.remove('invalid-field');
                badge?.classList.add('show');
            } else if (this.value.length > 0) {
                this.classList.add('invalid-field');
                this.classList.remove('valid-field');
                badge?.classList.remove('show');
            }
        });

        // ── Choice cards (Etapa 1) ───────────────────
        function syncChoiceCards() {
            const tipo = getTipo();
            $('#card-proprio')?.classList.toggle('selected', tipo === 'proprio');
            $('#card-responsavel')?.classList.toggle('selected', tipo === 'responsavel');

            const blocoResp = $('#bloco-responsavel');
            const subtitulo = $('#subtitle-contato');

            if (tipo === 'proprio') {
                if (blocoResp) blocoResp.style.display = 'none';
                if (subtitulo) subtitulo.textContent = 'Informe seus dados de contato.';
            } else {
                if (blocoResp) blocoResp.style.display = '';
                if (subtitulo) subtitulo.textContent = 'Informe seus dados para que possamos entrar em contato rapidamente.';
            }

            // Atualiza vinculo do primeiro aluno se for "próprio"
            const vincBtn0 = document.querySelector('select[name="alunos[0][vinculo]"]');
            if (vincBtn0 && tipo === 'proprio') {
                vincBtn0.value = 'Próprio';
                const blocoVinculo = $('#bloco-vinculo-0');
                if (blocoVinculo) blocoVinculo.style.display = 'none';
            } else if (vincBtn0) {
                const blocoVinculo = $('#bloco-vinculo-0');
                if (blocoVinculo) blocoVinculo.style.display = '';
            }
        }

        $$('input[name="tipo_preenchimento"]').forEach(r => {
            r.addEventListener('change', syncChoiceCards);
        });

        // ── Inicialização ─────────────────────────────
        syncChoiceCards();
        mostrarEtapa(etapaAtual);

        // Dispara filtro de série para unidade única
        const firstUnidadeSelect = document.querySelector('.unidade-select');
        if (firstUnidadeSelect && firstUnidadeSelect.value) {
            filtrarSeries(firstUnidadeSelect);
        }

        // Se houve erros de validação do backend, avança para a etapa correta
        @if($errors->any())
            @if($errors->has('tipo_preenchimento'))
                etapaAtual = 1;
            @elseif($errors->hasAny(['responsavel_nome', 'responsavel_email', 'responsavel_telefone']))
                etapaAtual = 2;
            @elseif($errors->has('alunos'))
                etapaAtual = 3;
            @else
                etapaAtual = 4;
            @endif
            mostrarEtapa(etapaAtual);
        @endif
    })();
    </script>
</body>
</html>
