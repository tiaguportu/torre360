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

    {{-- Icons --}}
    <script src="https://unpkg.com/lucide@latest"></script>

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
            --bg:          #f8fafc;
            --card:        #ffffff;
            --card-border: #e2e8f0;
            --text:        #1e293b;
            --muted:       #64748b;
            --input-bg:    #ffffff;
            --input-bd:    #cbd5e1;
            --radius:      12px;
            --shadow:      0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
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
            background: linear-gradient(rgba(79, 70, 229, 0.85), rgba(55, 48, 163, 0.9)), url('{{ asset("img/preview.png") }}');
            background-size: cover;
            background-position: center;
            padding: 80px 24px 100px;
            text-align: center;
            position: relative;
            overflow: hidden;
            color: #fff;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        .hero-logo {
            max-width: 220px;
            height: auto;
            margin-bottom: 24px;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.25));
        }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }

        .hero h1 {
            font-size: clamp(28px, 5vw, 46px);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -.02em;
            position: relative;
            color: #fff;
        }
        .hero p {
            margin-top: 12px;
            font-size: 18px;
            color: rgba(255,255,255,.8);
            position: relative;
        }

        /* ── PROGRESS BAR ── */
        .progress-wrap {
            width: 100%;
            max-width: 800px;
            padding: 32px 24px 0;
        }
        .steps-track {
            display: flex;
            align-items: center;
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
            top: 20px;
            left: calc(50% + 20px);
            width: calc(100% - 40px);
            height: 2px;
            background: var(--card-border);
            transition: background .4s;
        }
        .step-item.done:not(:last-child)::after   { background: var(--primary); }
        .step-circle {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--input-bg);
            border: 2px solid var(--card-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700;
            transition: all .35s;
            position: relative; z-index: 1;
        }
        .step-item.active .step-circle {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 6px rgba(79,70,229,.25);
            color: #fff;
        }
        .step-item.done .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }
        .step-label {
            margin-top: 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .step-item.active .step-label { color: var(--primary-lt); }
        .step-item.done .step-label   { color: var(--success); }

        /* ── CARD ── */
        .form-card {
            width: 100%;
            max-width: 800px;
            margin: 28px 24px 60px;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ── STEP PANELS ── */
        .step-panel {
            display: none;
            padding: 40px 40px 36px;
            animation: fadeIn .3s ease;
        }
        .step-panel.active { display: block; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

        .step-panel h2 {
            font-size: 22px; font-weight: 700;
            margin-bottom: 6px;
        }
        .step-panel .subtitle {
            font-size: 14px; color: var(--muted);
            margin-bottom: 32px;
        }

        /* ── CHOICE CARDS (tipo_preenchimento) ── */
        .choice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }
        .choice-card {
            border: 2px solid var(--card-border);
            border-radius: var(--radius);
            padding: 24px 20px;
            cursor: pointer;
            transition: all .25s;
            text-align: center;
            background: var(--input-bg);
        }
        .choice-card:hover { border-color: var(--primary-lt); transform: translateY(-2px); }
        .choice-card.selected {
            border-color: var(--primary);
            background: rgba(79,70,229,.12);
            box-shadow: 0 0 0 4px rgba(79,70,229,.15);
        }
        .choice-card .icon { 
            color: var(--primary);
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .choice-card .label { font-size: 15px; font-weight: 600; }
        .choice-card .desc  { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .choice-card input  { display: none; }

        /* ── FORM FIELDS ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
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
            font-size: 13px; font-weight: 500;
            color: var(--muted);
            letter-spacing: .02em;
        }
        label .req { color: var(--danger); margin-left: 2px; }

        input[type=text], input[type=email], input[type=date], input[type=tel],
        select, textarea {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-bd);
            border-radius: 10px;
            padding: 12px 14px;
            color: var(--text);
            font-size: 15px;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
            appearance: none;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,.25);
        }
        select option { background: #ffffff; }
        textarea { resize: vertical; min-height: 100px; }

        /* ── TURMA CARDS ── */
        .turma-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .turma-card {
            border: 2px solid var(--card-border);
            border-radius: 12px;
            padding: 16px 14px;
            cursor: pointer;
            transition: all .22s;
            background: var(--input-bg);
            position: relative;
        }
        .turma-card:hover { border-color: var(--primary-lt); transform: translateY(-2px); }
        .turma-card.selected {
            border-color: var(--primary);
            background: rgba(79,70,229,.1);
            box-shadow: 0 0 0 3px rgba(79,70,229,.15);
        }
        .turma-card input { display: none; }
        .turma-color-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
            vertical-align: middle;
        }
        .turma-nome { font-size: 15px; font-weight: 700; }
        .turma-serie { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .turma-turno { font-size: 11px; color: var(--primary-lt); margin-top: 2px; font-weight: 600; }
        .turma-card .check-badge {
            position: absolute; top: 8px; right: 8px;
            width: 20px; height: 20px;
            border-radius: 50%;
            background: var(--primary);
            display: none;
            align-items: center; justify-content: center;
            font-size: 11px;
        }
        .turma-card.selected .check-badge { display: flex; }

        /* Hidden field hint */
        .no-turma {
            font-size: 13px; color: var(--muted);
            padding: 12px 0;
            font-style: italic;
        }

        /* ── FOOTER DO FORM ── */
        .step-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 40px;
            border-top: 1px solid var(--card-border);
            gap: 12px;
        }
        @media (max-width: 620px) { .step-footer { padding: 20px; } }

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
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dk));
            color: #fff;
            box-shadow: 0 4px 16px rgba(79,70,229,.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,.5); }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .btn-ghost {
            background: transparent;
            border: 1px solid var(--card-border);
            color: var(--muted);
        }
        .btn-ghost:hover { border-color: var(--primary-lt); color: var(--text); }
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: #fff;
            box-shadow: 0 4px 16px rgba(16,185,129,.35);
        }
        .btn-success:hover { transform: translateY(-2px); }

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
        .alert-error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }
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

        /* ── RECAPTCHA BADGE customização ── */
        .grecaptcha-badge { opacity: .7; transition: opacity .3s; }
        .grecaptcha-badge:hover { opacity: 1; }

        .recap-notice {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
            margin-top: 12px;
            line-height: 1.6;
        }
        .recap-notice a { color: var(--primary-lt); text-decoration: none; }

        /* Responsivo geral */
        @media (max-width: 480px) {
            .progress-wrap { padding: 24px 16px 0; }
            .form-card { margin: 20px 12px 40px; }
            .step-label { display: none; }
        }
    </style>
</head>
<body>

    {{-- ── HERO ── --}}
    <header class="hero" id="form-anchor">
        <img src="{{ asset('logo-adaptative.svg') }}" alt="Torre360 Logo" class="hero-logo">
        <h1>Manifeste seu Interesse</h1>
        <p>Preencha o formulário e nossa equipe entrará em contato para agendar uma visita.</p>
    </header>

    {{-- ── PROGRESSO ── --}}
    <div class="progress-wrap">
        <div class="steps-track">
            <div class="step-item active" id="step-indicator-1">
                <div class="step-circle">1</div>
                <div class="step-label">Quem preenche</div>
            </div>
            <div class="step-item" id="step-indicator-2">
                <div class="step-circle">2</div>
                <div class="step-label">Dados de Contato</div>
            </div>
            <div class="step-item" id="step-indicator-3">
                <div class="step-circle">3</div>
                <div class="step-label">Dados do(s) Aluno(s)</div>
            </div>
            <div class="step-item" id="step-indicator-4">
                <div class="step-circle">4</div>
                <div class="step-label">Observações</div>
            </div>
            <div class="step-item" id="step-indicator-5">
                <div class="step-circle">5</div>
                <div class="step-label">Confirmação</div>
            </div>
        </div>
    </div>

    {{-- ── CARD PRINCIPAL ── --}}
    <div class="form-card">

        @if($errors->any())
            <div style="padding: 24px 40px 0">
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
                <h2>Olá! Bem-vindo(a)</h2>
                <p class="subtitle">Para começar, nos diga: quem está preenchendo este formulário?</p>
 
                <div class="choice-grid">
                    <label class="choice-card" id="card-proprio" for="tipo_proprio">
                        <div class="icon"><i data-lucide="graduation-cap" style="width: 40px; height: 40px;"></i></div>
                        <div class="label">Sou o próprio aluno</div>
                        <div class="desc">Estou me inscrevendo por conta própria</div>
                        <input type="radio" id="tipo_proprio" name="tipo_preenchimento" value="proprio" {{ old('tipo_preenchimento') === 'proprio' ? 'checked' : '' }}>
                    </label>

                    <label class="choice-card" id="card-responsavel" for="tipo_responsavel">
                        <div class="icon"><i data-lucide="users" style="width: 40px; height: 40px;"></i></div>
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
                <h2>Dados de Contato</h2>
                <p class="subtitle" id="subtitle-contato">Informe seus dados para que possamos entrar em contato.</p>

                {{-- Responsável --}}
                <div id="bloco-responsavel">
                    <div class="form-grid" style="margin-bottom:20px">
                        <div class="field span-2">
                            <label for="responsavel_nome">Seu nome completo <span class="req">*</span></label>
                            <input type="text" id="responsavel_nome" name="responsavel_nome"
                                   value="{{ old('responsavel_nome') }}"
                                   placeholder="Ex.: Maria da Silva">
                        </div>

                        <div class="field">
                            <label for="responsavel_cpf">CPF</label>
                            <input type="text" id="responsavel_cpf" name="responsavel_cpf"
                                   value="{{ old('responsavel_cpf') }}"
                                   placeholder="000.000.000-00" maxlength="14">
                        </div>

                    </div>
                </div>

                {{-- Contato (para todos) --}}
                <div class="form-grid">
                    <div class="field">
                        <label for="responsavel_telefone">Telefone / WhatsApp <span class="req">*</span></label>
                        <input type="tel" id="responsavel_telefone" name="responsavel_telefone"
                               value="{{ old('responsavel_telefone') }}"
                               placeholder="(00) 9 0000-0000">
                    </div>

                    <div class="field">
                        <label for="responsavel_email">E-mail <span class="req">*</span></label>
                        <input type="email" id="responsavel_email" name="responsavel_email"
                               value="{{ old('responsavel_email') }}"
                               placeholder="email@exemplo.com">
                    </div>
                </div>

                <div class="form-grid col-1" style="margin-top:20px">
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
                 ETAPA 3 — Dados do Aluno
            ════════════════════════════════════════ --}}
            <div class="step-panel" id="panel-3">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <div>
                        <h2>Dados do Aluno</h2>
                        <p class="subtitle" style="margin-bottom: 0;">Informe os dados de quem irá estudar conosco.</p>
                    </div>
                    <button type="button" class="btn btn-ghost" onclick="adicionarAluno()" style="padding: 10px 16px; font-size: 13px;">
                        + Adicionar outro aluno
                    </button>
                </div>

                <div id="alunos-container">
                    {{-- O primeiro aluno já vem fixo --}}
                    <div class="aluno-item" id="aluno-0" style="background: rgba(79,70,229,0.03); padding: 20px; border-radius: 12px; border: 1px solid var(--card-border); margin-bottom: 20px; position: relative;">
                        <div style="font-size: 12px; font-weight: 800; color: var(--primary); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em;">Aluno #1</div>
                        
                        <div class="form-grid">
                            <div class="field span-2">
                                <label>Nome completo do aluno <span class="req">*</span></label>
                                <input type="text" name="alunos[0][nome]" placeholder="Nome completo" required>
                            </div>

                            <div class="field">
                                <label>Data de nascimento</label>
                                <input type="date" name="alunos[0][data_nascimento]" max="{{ now()->toDateString() }}">
                            </div>

                            <div class="field">
                                <label>Vínculo com o responsável <span class="req">*</span></label>
                                <select name="alunos[0][vinculo]" required>
                                    <option value="">Selecione...</option>
                                    <option value="Pai">Filho(a) - Sou o Pai</option>
                                    <option value="Mãe">Filho(a) - Sou a Mãe</option>
                                    <option value="Parente">Parente</option>
                                    <option value="Tutor">Tutor(a)</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Unidade de interesse</label>
                                <select name="alunos[0][unidade_id]" class="unidade-select" onchange="filtrarTurmas(this)">
                                    <option value="">Selecione a Unidade...</option>
                                    @foreach($unidades as $u)
                                        <option value="{{ $u->id }}" {{ $unidades->count() === 1 ? 'selected' : '' }}>{{ $u->nome }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Turma/Série de interesse</label>
                                <select name="alunos[0][turma_id]" class="turma-select" disabled>
                                    <option value="">Selecione primeiro a unidade...</option>
                                    @foreach($turmas as $turma)
                                        <option value="{{ $turma->id }}" data-unidade="{{ $turma->serie?->curso?->unidade_id }}" style="display:none">
                                            {{ $turma->nome }} ({{ $turma->serie?->nome }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 ETAPA 4 — Observações Finais
            ════════════════════════════════════════ --}}
            <div class="step-panel" id="panel-4">
                <h2>Observações Finais</h2>
                <p class="subtitle">Alguma informação adicional que gostaria de nos contar?</p>

                <div class="form-grid col-1">
                    <div class="field">
                        <label for="observacoes">Suas observações</label>
                        <textarea id="observacoes" name="observacoes"
                                  placeholder="Alguma necessidade especial, dúvida ou informação adicional?">{{ old('observacoes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 ETAPA 5 — Confirmação
            ════════════════════════════════════════ --}}
            <div class="step-panel" id="panel-5">
                <h2>Tudo certo? Revise e envie 🚀</h2>
                <p class="subtitle">Confirme os dados antes de enviar. Nossa equipe entrará em contato em breve.</p>

                <div id="resumo" style="
                    background: #f1f5f9;
                    border: 1px solid var(--card-border);
                    border-radius: 12px;
                    padding: 24px;
                    margin-bottom: 24px;
                    line-height: 1.8;
                    font-size: 14px;
                ">
                    {{-- preenchido via JS --}}
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
        const totalEtapas = 5;

        // ── Helpers ──────────────────────────────────
        const $ = (sel) => document.querySelector(sel);
        const $$ = (sel) => document.querySelectorAll(sel);

        function getTipo() {
            const el = document.querySelector('input[name="tipo_preenchimento"]:checked');
            return el ? el.value : 'responsavel';
        }

        // ── Sinalizadores de etapa ───────────────────
        function atualizarIndicadores() {
            for (let i = 1; i <= totalEtapas; i++) {
                const ind = $(`#step-indicator-${i}`);
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

            $('#btnVoltar').style.display  = n > 1     ? 'inline-flex' : 'none';
            $('#btnProximo').style.display = n < totalEtapas ? 'inline-flex' : 'none';
            $('#btnEnviar').style.display  = n === totalEtapas ? 'inline-flex' : 'none';

            if (n === totalEtapas) construirResumo();
            atualizarIndicadores();

            // Rola para a barra de progresso (início do form) suavemente
            if (n > 1) {
                const progressWrap = document.querySelector('.progress-wrap');
                if (progressWrap) {
                    progressWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        // ── Validação antes de avançar ───────────────
        function validarEtapa(n) {
            const tipo = getTipo();

            if (n === 1) {
                if (!tipo) { alert('Selecione uma opção para continuar.'); return false; }
                return true;
            }

            if (n === 2) {
                if (tipo === 'responsavel') {
                    const nome = $('#responsavel_nome').value.trim();
                    if (!nome) { $('#responsavel_nome').focus(); alert('Informe seu nome completo.'); return false; }
                }
                const tel   = $('#responsavel_telefone').value.trim();
                const email = $('#responsavel_email').value.trim();
                if (!tel)   { $('#responsavel_telefone').focus(); alert('Informe o telefone.'); return false; }
                if (!email || !email.includes('@')) { $('#responsavel_email').focus(); alert('Informe um e-mail válido.'); return false; }
                return true;
            }

            if (n === 3) {
                const primeiroNome = document.querySelector('input[name="alunos[0][nome]"]');
                if (!primeiroNome || !primeiroNome.value.trim()) { 
                    if(primeiroNome) primeiroNome.focus();
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
        window.adicionarAluno = function() {
            const container = $('#alunos-container');
            const html = `
                <div class="aluno-item" id="aluno-${alunoIdx}" style="background: rgba(79,70,229,0.03); padding: 20px; border-radius: 12px; border: 1px solid var(--card-border); margin-bottom: 20px; position: relative; animation: fadeIn .3s ease;">
                    <button type="button" onclick="removerAluno(${alunoIdx})" style="position: absolute; top: 16px; right: 16px; background: none; border: none; color: var(--danger); cursor: pointer; font-size: 11px; font-weight: 700; text-transform: uppercase;">Remover</button>
                    <div style="font-size: 12px; font-weight: 800; color: var(--primary); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em;">Aluno #${alunoIdx + 1}</div>
                    <div class="form-grid">
                        <div class="field span-2">
                            <label>Nome completo do aluno <span class="req">*</span></label>
                            <input type="text" name="alunos[${alunoIdx}][nome]" placeholder="Nome completo" required>
                        </div>
                        <div class="field">
                            <label>Data de nascimento</label>
                            <input type="date" name="alunos[${alunoIdx}][data_nascimento]" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Vínculo com o responsável <span class="req">*</span></label>
                            <select name="alunos[${alunoIdx}][vinculo]" required>
                                <option value="">Selecione...</option>
                                <option value="Pai">Filho(a) - Sou o Pai</option>
                                <option value="Mãe">Filho(a) - Sou a Mãe</option>
                                <option value="Parente">Parente</option>
                                <option value="Tutor">Tutor(a)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Unidade de interesse</label>
                            <select name="alunos[${alunoIdx}][unidade_id]" class="unidade-select" onchange="filtrarTurmas(this)">
                                <option value="">Selecione a Unidade...</option>
                                @foreach($unidades as $u)
                                    <option value="{{ $u->id }}" {{ $unidades->count() === 1 ? 'selected' : '' }}>{{ $u->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Turma/Série de interesse</label>
                            <select name="alunos[${alunoIdx}][turma_id]" class="turma-select" disabled>
                                <option value="">Selecione primeiro a unidade...</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" data-unidade="{{ $turma->serie?->curso?->unidade_id }}" style="display:none">
                                        {{ $turma->nome }} ({{ $turma->serie?->nome }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);

            // Se for unidade única, já dispara o filtro para o novo aluno
            const novoSelectUnidade = document.querySelector(`#aluno-${alunoIdx} .unidade-select`);
            if (novoSelectUnidade && novoSelectUnidade.value) {
                filtrarTurmas(novoSelectUnidade);
            }

            alunoIdx++;
        };

        window.filtrarTurmas = function(selectUnidade) {
            const unidadeId = selectUnidade.value;
            const alunoItem = selectUnidade.closest('.aluno-item');
            const selectTurma = alunoItem.querySelector('.turma-select');

            // Limpa o select de turmas
            selectTurma.innerHTML = '<option value="">Sem preferência</option>';
            
            if (!unidadeId) {
                selectTurma.disabled = true;
                selectTurma.innerHTML = '<option value="">Selecione primeiro a unidade...</option>';
                return;
            }

            const totalTurmas = [
                @foreach($turmas as $turma)
                { id: "{{ $turma->id }}", nome: "{{ $turma->nome }} ({{ $turma->serie?->nome }})", unidade_id: "{{ $turma->serie?->curso?->unidade_id }}" },
                @endforeach
            ];

            const turmasFiltradas = totalTurmas.filter(t => t.unidade_id === unidadeId);

            if (turmasFiltradas.length > 0) {
                turmasFiltradas.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.nome;
                    selectTurma.appendChild(opt);
                });
                selectTurma.disabled = false;
            } else {
                const opt = document.createElement('option');
                opt.value = "";
                opt.textContent = "Nenhuma turma disponível nesta unidade";
                selectTurma.appendChild(opt);
                selectTurma.disabled = true;
            }
        };

        window.removerAluno = function(idx) {
            $(`#aluno-${idx}`).remove();
        };

        // ── Resumo (Etapa 5) ─────────────────────────
        function val(id) {
            const el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }
        function selText(id) {
            const el = document.getElementById(id);
            return el && el.selectedIndex >= 0 ? el.options[el.selectedIndex]?.text?.trim() : '';
        }

        function construirResumo() {
            const tipo   = getTipo();
            const linhas = [];

            if (tipo === 'responsavel') {
                linhas.push(`<strong>Responsável:</strong> ${val('responsavel_nome') || '–'}`);
            }

            linhas.push(`<strong>Telefone:</strong> ${val('responsavel_telefone') || '–'}`);
            linhas.push(`<strong>E-mail:</strong> ${val('responsavel_email') || '–'}`);
            linhas.push(`<hr style="border-color:var(--card-border);margin:10px 0">`);

            const alunos = document.querySelectorAll('.aluno-item');
            linhas.push(`<strong>Alunos inscritos (${alunos.length}):</strong><br>`);
            
            alunos.forEach((item, index) => {
                const nome = item.querySelector('input[name*="[nome]"]').value;
                const vinculo = item.querySelector('select[name*="[vinculo]"] option:checked').text;
                linhas.push(`<div style="margin-bottom:8px">• ${nome} (${vinculo})</div>`);
            });

            const obs = val('observacoes');
            if (obs) {
                linhas.push(`<hr style="border-color:var(--card-border);margin:10px 0">`);
                linhas.push(`<strong>Observações:</strong> ${obs}`);
            }

            $('#resumo').innerHTML = linhas.join('<br>');
        }

        // ── Envio com reCAPTCHA v3 ───────────────────
        $('#formCaptacao').addEventListener('submit', function (e) {
            e.preventDefault();

            const siteKey = '{{ config("services.recaptcha.site_key") }}';
            const btn     = $('#btnEnviar');
            const texto   = $('#btnEnviarTexto');
            const spinner = $('#btnSpinner');

            btn.disabled    = true;
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

        // ── Máscara Telefone ─────────────────────────
        $('#responsavel_telefone')?.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            if (v.length <= 10) {
                v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
            }
            this.value = v;
        });

        // ── Choice cards (Etapa 1) ───────────────────
        function syncChoiceCards() {
            const tipo = getTipo();
            const cardProprio = $('#card-proprio');
            const cardResponsavel = $('#card-responsavel');
            
            if(cardProprio) cardProprio.classList.toggle('selected', tipo === 'proprio');
            if(cardResponsavel) cardResponsavel.classList.toggle('selected', tipo === 'responsavel');

            const blocoResp = $('#bloco-responsavel');
            const subtitulo = $('#subtitle-contato');

            if (tipo === 'proprio') {
                if(blocoResp) blocoResp.style.display = 'none';
                if(subtitulo) subtitulo.textContent   = 'Informe seus dados de contato.';
            } else {
                if(blocoResp) blocoResp.style.display = '';
                if(subtitulo) subtitulo.textContent   = 'Informe seus dados para que possamos entrar em contato.';
            }
        }

        $$('input[name="tipo_preenchimento"]').forEach(r => {
            r.addEventListener('change', syncChoiceCards);
        });

        // ── Inicialização ────────────────────────────
        syncChoiceCards();
        mostrarEtapa(etapaAtual);

        // Dispara filtro inicial se houver unidade única
        const firstUnidadeSelect = document.querySelector('.unidade-select');
        if (firstUnidadeSelect && firstUnidadeSelect.value) {
            filtrarTurmas(firstUnidadeSelect);
        }
        // Inicializa ícones Lucide
        lucide.createIcons();

        // Se houve erros de validação, tenta ir para a etapa correta
        @if($errors->any())
            // Vai para a etapa com erro (2 ou 3)
            @if($errors->has('tipo_preenchimento'))
                etapaAtual = 1;
            @elseif($errors->hasAny(['responsavel_nome', 'responsavel_email', 'responsavel_telefone']))
                etapaAtual = 2;
            @elseif($errors->has('aluno_nome'))
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
