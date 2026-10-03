<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Pesquisa de Satisfação do Tour Escolar | {{ config('app.name', 'Torre360') }}</title>
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
            --border: #e2e8f0;
            --bg: #f8fafc;
            --danger: #ef4444;
            --warning: #f59e0b;
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
        .container {
            background: #ffffff;
            border-radius: 24px;
            padding: 40px;
            max-width: 680px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            margin: 20px 0;
        }
        @media (max-width: 560px) {
            .container { padding: 24px 20px; border-radius: 20px; }
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .badge-tour {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e0e7ff;
            color: var(--primary-dk);
            font-size: 13px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 9999px;
            margin-bottom: 14px;
        }
        h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 8px;
        }
        .subtitulo {
            font-size: 15px;
            color: var(--muted);
            line-height: 1.6;
        }
        .card-detalhes {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 28px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-detalhes div span {
            color: var(--muted);
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .card-detalhes div strong {
            color: var(--text);
            font-size: 14px;
        }
        .secao-pergunta {
            margin-bottom: 28px;
        }
        .secao-pergunta h2 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .secao-pergunta p.descricao {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 14px;
        }
        /* Escala NPS 0 a 10 */
        .escala-nps {
            display: grid;
            grid-template-columns: repeat(11, 1fr);
            gap: 6px;
            margin-bottom: 8px;
        }
        @media (max-width: 600px) {
            .escala-nps {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        .opcao-nps {
            position: relative;
        }
        .opcao-nps input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .opcao-nps label {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 48px;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: #fff;
            font-weight: 800;
            font-size: 16px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .opcao-nps input:checked + label {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
            transform: scale(1.06);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
        }
        .nps-legenda {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-top: 6px;
        }
        /* Pilares de Avaliação (1 a 5 estrelas / botões) */
        .pilares-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        .pilar-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px 20px;
        }
        .pilar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .pilar-titulo {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }
        .estrelas-opcoes {
            display: flex;
            gap: 8px;
        }
        .estrela-item {
            position: relative;
            flex: 1;
        }
        .estrela-item input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .estrela-item label {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 38px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: #fff;
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .estrela-item input:checked + label {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #b45309;
            font-weight: 800;
        }
        textarea {
            width: 100%;
            padding: 14px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            background: #fff;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.2s;
        }
        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn-enviar {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
            margin-top: 10px;
        }
        .btn-enviar:hover {
            background: var(--primary-dk);
        }
        .btn-enviar:active {
            transform: scale(0.99);
        }
        .erro-msg {
            color: var(--danger);
            font-size: 13px;
            font-weight: 600;
            margin-top: 6px;
        }
        .footer-aviso {
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            margin-top: 20px;
        }
    </style>
</head>
<body>
    @php
        $responsavel = $pesquisa->interessado?->pessoa;
        $primeiroNome = explode(' ', trim($responsavel?->nome ?? 'Família'))[0];
        $consultorNome = $pesquisa->visita?->usuario?->name ?? 'Nossa Equipe';
        $escolaNome = \App\Models\Unidade::first()?->nome ?? \App\Models\InstituicaoEnsino::first()?->nome ?? 'Nossa Escola';
    @endphp

    <div class="container">
        <div class="header">
            <div class="badge-tour">
                <span>🏫</span> Tour Pedagógico
            </div>
            <h1>Como foi sua experiência no {{ $escolaNome }}?</h1>
            <p class="subtitulo">Olá, <strong>{{ $primeiroNome }}</strong>! Sua opinião é essencial para acolhermos cada vez melhor você e sua família.</p>
        </div>

        <div class="card-detalhes">
            <div>
                <span>Consultor Responsável</span>
                <strong>{{ $consultorNome }}</strong>
            </div>
            <div>
                <span>Data da Visita</span>
                <strong>{{ $pesquisa->visita?->data_hora ? $pesquisa->visita->data_hora->format('d/m/Y') : 'Visita Recente' }}</strong>
            </div>
            <div>
                <span>Série de Interesse</span>
                <strong>{{ $pesquisa->visita?->dependente?->serie?->nome ?? $pesquisa->interessado?->dependentes->first()?->serie?->nome ?? 'Geral' }}</strong>
            </div>
        </div>

        <form method="POST" action="{{ route('pesquisa-visita.store', ['token' => $pesquisa->token]) }}">
            @csrf

            <!-- NPS Global -->
            <div class="secao-pergunta">
                <h2><span>⭐</span> Recomendação da Escola (NPS)</h2>
                <p class="descricao">Em uma escala de 0 a 10, o quanto você recomendaria uma visita à nossa escola para um amigo ou familiar?</p>

                <div class="escala-nps">
                    @for ($i = 0; $i <= 10; $i++)
                        <div class="opcao-nps">
                            <input type="radio" name="nota_nps" id="nps_{{ $i }}" value="{{ $i }}" {{ old('nota_nps') == (string) $i ? 'checked' : '' }} required>
                            <label for="nps_{{ $i }}">{{ $i }}</label>
                        </div>
                    @endfor
                </div>
                <div class="nps-legenda">
                    <span>🙁 Pouco provável (0)</span>
                    <span>Neutro (7-8)</span>
                    <span>Com certeza! (10) 😍</span>
                </div>
                @error('nota_nps')
                    <div class="erro-msg">{{ $message }}</div>
                @enderror
            </div>

            <!-- Pilares -->
            <div class="secao-pergunta">
                <h2><span>🔍</span> Avaliação por Áreas</h2>
                <p class="descricao">Classifique cada aspecto de 1 (insatisfeito) a 5 (excelente):</p>

                <div class="pilares-grid">
                    <!-- Acolhimento -->
                    <div class="pilar-card">
                        <div class="pilar-header">
                            <span class="pilar-titulo">🤝 Acolhimento e Atendimento da Equipe</span>
                        </div>
                        <div class="estrelas-opcoes">
                            @for ($nota = 1; $nota <= 5; $nota++)
                                <div class="estrela-item">
                                    <input type="radio" name="nota_atendimento" id="atendimento_{{ $nota }}" value="{{ $nota }}" {{ old('nota_atendimento') == (string) $nota ? 'checked' : '' }}>
                                    <label for="atendimento_{{ $nota }}">{{ $nota }} ⭐</label>
                                </div>
                            @endfor
                        </div>
                    </div>

                    <!-- Infraestrutura -->
                    <div class="pilar-card">
                        <div class="pilar-header">
                            <span class="pilar-titulo">🏫 Espaço, Limpeza e Infraestrutura</span>
                        </div>
                        <div class="estrelas-opcoes">
                            @for ($nota = 1; $nota <= 5; $nota++)
                                <div class="estrela-item">
                                    <input type="radio" name="nota_infraestrutura" id="infra_{{ $nota }}" value="{{ $nota }}" {{ old('nota_infraestrutura') == (string) $nota ? 'checked' : '' }}>
                                    <label for="infra_{{ $nota }}">{{ $nota }} ⭐</label>
                                </div>
                            @endfor
                        </div>
                    </div>

                    <!-- Proposta Pedagógica -->
                    <div class="pilar-card">
                        <div class="pilar-header">
                            <span class="pilar-titulo">📖 Clareza da Proposta Pedagógica e Metodologia</span>
                        </div>
                        <div class="estrelas-opcoes">
                            @for ($nota = 1; $nota <= 5; $nota++)
                                <div class="estrela-item">
                                    <input type="radio" name="nota_proposta_pedagogica" id="pedag_{{ $nota }}" value="{{ $nota }}" {{ old('nota_proposta_pedagogica') == (string) $nota ? 'checked' : '' }}>
                                    <label for="pedag_{{ $nota }}">{{ $nota }} ⭐</label>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comentários -->
            <div class="secao-pergunta">
                <h2><span>💬</span> Comentários, Elogios ou Dúvidas</h2>
                <p class="descricao">Conte-nos o que você mais gostou ou o que podemos melhorar:</p>
                <textarea name="comentario" placeholder="Escreva aqui suas observações (opcional)...">{{ old('comentario') }}</textarea>
                @error('comentario')
                    <div class="erro-msg">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-enviar">
                Enviar Minha Avaliação 🚀
            </button>
        </form>

        <p class="footer-aviso">Leva menos de 1 minuto. Suas respostas são confidenciais e fundamentais para nossa melhoria contínua.</p>
    </div>
</body>
</html>
