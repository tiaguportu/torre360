<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Confirme seus Dados | Torre360</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #4f46e5; --primary-dk: #3730a3; --text: #1e293b; --muted: #64748b;
            --border: #e2e8f0; --bg: #f1f5f9; --danger: #dc2626;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #312e81 0%, #4f46e5 50%, #6366f1 100%);
            min-height: 100vh; padding: 24px; display: flex; justify-content: center;
        }
        .card {
            background: #fff; border-radius: 24px; padding: 40px; max-width: 640px; width: 100%;
            box-shadow: 0 32px 64px -12px rgba(0,0,0,0.25); margin: 24px 0;
        }
        h1 { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
        .subtitulo { font-size: 15px; color: var(--muted); line-height: 1.6; margin-bottom: 28px; }
        fieldset { border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 20px; }
        legend { font-weight: 700; color: var(--primary-dk); padding: 0 8px; font-size: 15px; }
        label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin: 12px 0 6px; }
        input, select {
            width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 14px; font-family: inherit; color: var(--text); background: #fff;
        }
        input:focus, select:focus { outline: none; border-color: var(--primary); }
        .dependente-nome { font-size: 16px; font-weight: 700; color: var(--text); }
        .btn {
            width: 100%; margin-top: 24px; padding: 16px; border: none; border-radius: 12px;
            background: var(--primary); color: #fff; font-size: 15px; font-weight: 700;
            font-family: inherit; cursor: pointer;
        }
        .btn:hover { background: var(--primary-dk); }
        .erro { color: var(--danger); font-size: 13px; margin-top: 4px; }
        .aviso-expira { font-size: 12px; color: var(--muted); text-align: center; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Olá, {{ $interessado->pessoa?->nome ?? 'responsável' }}! 👋</h1>
        <p class="subtitulo">
            Confirme ou atualize os dados abaixo para agilizar a matrícula. Você só vê e
            edita as informações do seu próprio cadastro.
        </p>

        @if($errors->any())
            <div class="erro" style="margin-bottom: 16px;">
                @foreach($errors->all() as $erro)
                    <div>{{ $erro }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('captacao.interessado.convite.confirmar', $interessado->token_convite) }}">
            @csrf

            <fieldset>
                <legend>Seus dados de contato</legend>
                <label for="telefone">Telefone / WhatsApp</label>
                <input type="text" id="telefone" name="telefone" value="{{ old('telefone', $interessado->pessoa?->telefone) }}">

                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', $interessado->pessoa?->email) }}">
            </fieldset>

            @foreach($interessado->dependentes as $i => $dependente)
                <fieldset>
                    <legend>👦 {{ $dependente->nome_crianca }}</legend>
                    <input type="hidden" name="dependentes[{{ $i }}][id]" value="{{ $dependente->id }}">

                    <label for="serie_{{ $dependente->id }}">Série / Ano de Interesse</label>
                    <select id="serie_{{ $dependente->id }}" name="dependentes[{{ $i }}][serie_id]">
                        <option value="">Selecione...</option>
                        @foreach($series->groupBy(fn ($s) => $s->curso?->nome_externo ?? $s->curso?->nome_interno ?? 'Outros') as $cursoNome => $seriesDoCurso)
                            <optgroup label="{{ $cursoNome }}">
                                @foreach($seriesDoCurso as $serie)
                                    <option value="{{ $serie->id }}" @selected(old("dependentes.$i.serie_id", $dependente->serie_id) == $serie->id)>{{ $serie->nome }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>

                    <label for="turno_{{ $dependente->id }}">Turno de Preferência</label>
                    <select id="turno_{{ $dependente->id }}" name="dependentes[{{ $i }}][turno_preferencia]">
                        <option value="">Sem preferência</option>
                        <option value="Manhã" @selected(old("dependentes.$i.turno_preferencia") === 'Manhã')>Manhã</option>
                        <option value="Tarde" @selected(old("dependentes.$i.turno_preferencia") === 'Tarde')>Tarde</option>
                        <option value="Integral" @selected(old("dependentes.$i.turno_preferencia") === 'Integral')>Integral</option>
                    </select>
                </fieldset>
            @endforeach

            <button type="submit" class="btn">Confirmar Dados</button>
        </form>

        <p class="aviso-expira">
            Este link é pessoal e expira em {{ $interessado->token_convite_expira_em->format('d/m/Y') }}.
        </p>
    </div>
</body>
</html>
