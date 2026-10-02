<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Pré-matrícula Online | Torre360</title>
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
            background: #fff; border-radius: 24px; padding: 40px; max-width: 720px; width: 100%;
            box-shadow: 0 32px 64px -12px rgba(0,0,0,0.25); margin: 24px 0;
        }
        h1 { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
        .subtitulo { font-size: 15px; color: var(--muted); line-height: 1.6; margin-bottom: 28px; }
        fieldset { border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 20px; }
        legend { font-weight: 700; color: var(--primary-dk); padding: 0 8px; font-size: 15px; }
        label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin: 12px 0 6px; }
        label small { font-weight: 400; color: var(--muted); }
        input, select {
            width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 14px; font-family: inherit; color: var(--text); background: #fff;
        }
        input:focus, select:focus { outline: none; border-color: var(--primary); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        .grid .full { grid-column: 1 / -1; }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } .card { padding: 24px; } }
        .check { display: flex; align-items: flex-start; gap: 10px; margin-top: 14px; font-weight: 500; }
        .check input { width: auto; margin-top: 3px; }
        details { border: 1px dashed var(--border); border-radius: 16px; padding: 14px 20px; margin-bottom: 20px; }
        summary { cursor: pointer; font-weight: 700; color: var(--primary-dk); font-size: 15px; }
        .btn {
            width: 100%; margin-top: 24px; padding: 16px; border: none; border-radius: 12px;
            background: var(--primary); color: #fff; font-size: 15px; font-weight: 700;
            font-family: inherit; cursor: pointer;
        }
        .btn:hover { background: var(--primary-dk); }
        .erro { color: var(--danger); font-size: 13px; margin-top: 4px; }
        .aviso { font-size: 12px; color: var(--muted); text-align: center; margin-top: 16px; line-height: 1.5; }
        .lgpd { background: var(--bg); border-radius: 12px; padding: 14px 16px; font-size: 13px; color: var(--muted); line-height: 1.6; }
    </style>
</head>
<body>
    @php
        $pessoa = $interessado->pessoa;
        $enderecoCidadeIbge = old('responsavel.cidade_ibge');
    @endphp
    <div class="card">
        <h1>Olá, {{ $pessoa?->nome ?? 'responsável' }}! 👋</h1>
        <p class="subtitulo">
            Preencha a pré-matrícula abaixo para agilizar o atendimento da secretaria. Você só vê e
            edita as informações do seu próprio cadastro. Nenhum dado é enviado até você clicar em
            <strong>Enviar pré-matrícula</strong>.
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

            {{-- RESPONSÁVEL --}}
            <fieldset>
                <legend>👤 Você (responsável)</legend>
                <div class="grid">
                    <div class="full">
                        <label for="resp_nome">Nome completo</label>
                        <input type="text" id="resp_nome" name="responsavel[nome]" value="{{ old('responsavel.nome', $pessoa?->nome) }}" required>
                    </div>
                    <div>
                        <label for="resp_cpf">CPF</label>
                        <input type="text" id="resp_cpf" name="responsavel[cpf]" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" data-mask="cpf" value="{{ old('responsavel.cpf', $pessoa?->cpf) }}" required>
                    </div>
                    <div>
                        <label for="resp_nasc">Data de nascimento</label>
                        <input type="date" id="resp_nasc" name="responsavel[data_nascimento]" max="{{ now()->toDateString() }}" value="{{ old('responsavel.data_nascimento', filled($pessoa?->data_nascimento) ? \Illuminate\Support\Carbon::parse($pessoa->data_nascimento)->toDateString() : null) }}" required>
                    </div>
                    <div>
                        <label for="resp_tel">Telefone / WhatsApp</label>
                        <input type="text" id="resp_tel" name="responsavel[telefone]" value="{{ old('responsavel.telefone', $pessoa?->telefone) }}" required>
                    </div>
                    <div>
                        <label for="resp_email">E-mail</label>
                        <input type="email" id="resp_email" name="responsavel[email]" value="{{ old('responsavel.email', $pessoa?->email) }}">
                    </div>
                    <div>
                        <label for="resp_vinculo">Vínculo com o aluno</label>
                        <select id="resp_vinculo" name="responsavel[tipo_vinculo_id]" required>
                            <option value="">Selecione...</option>
                            @foreach($tiposVinculo as $id => $nome)
                                <option value="{{ $id }}" @selected(old('responsavel.tipo_vinculo_id') == $id)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label class="check">
                            <input type="checkbox" name="responsavel[is_financeiro]" value="1" @checked(old('responsavel.is_financeiro', true))>
                            <span>Sou o responsável financeiro (contrato e mensalidades)</span>
                        </label>
                    </div>
                </div>
            </fieldset>

            {{-- ENDEREÇO --}}
            <fieldset>
                <legend>🏠 Endereço da família</legend>
                <div class="grid">
                    <div>
                        <label for="cep">CEP</label>
                        <input type="text" id="cep" name="responsavel[cep]" inputmode="numeric" maxlength="9" placeholder="00000-000" value="{{ old('responsavel.cep') }}" required>
                    </div>
                    <div>
                        <label for="numero">Número</label>
                        <input type="text" id="numero" name="responsavel[numero]" value="{{ old('responsavel.numero') }}" required>
                    </div>
                    <div class="full">
                        <label for="logradouro">Logradouro</label>
                        <input type="text" id="logradouro" name="responsavel[logradouro]" value="{{ old('responsavel.logradouro') }}" required>
                    </div>
                    <div>
                        <label for="complemento">Complemento <small>(opcional)</small></label>
                        <input type="text" id="complemento" name="responsavel[complemento]" value="{{ old('responsavel.complemento') }}">
                    </div>
                    <div>
                        <label for="bairro">Bairro</label>
                        <input type="text" id="bairro" name="responsavel[bairro]" value="{{ old('responsavel.bairro') }}" required>
                    </div>
                </div>
                <input type="hidden" id="cidade_ibge" name="responsavel[cidade_ibge]" value="{{ $enderecoCidadeIbge }}">
                <p id="cidade_info" class="aviso" style="text-align:left; margin-top:10px;"></p>
            </fieldset>

            {{-- SEGUNDO RESPONSÁVEL --}}
            <details @open(old('segundo_responsavel.nome'))>
                <summary>➕ Adicionar um segundo responsável <small style="font-weight:400;color:var(--muted);">(opcional)</small></summary>
                <div class="grid">
                    <div class="full">
                        <label for="resp2_nome">Nome completo</label>
                        <input type="text" id="resp2_nome" name="segundo_responsavel[nome]" value="{{ old('segundo_responsavel.nome') }}">
                    </div>
                    <div>
                        <label for="resp2_cpf">CPF</label>
                        <input type="text" id="resp2_cpf" name="segundo_responsavel[cpf]" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" data-mask="cpf" value="{{ old('segundo_responsavel.cpf') }}">
                    </div>
                    <div>
                        <label for="resp2_vinculo">Vínculo com o aluno</label>
                        <select id="resp2_vinculo" name="segundo_responsavel[tipo_vinculo_id]">
                            <option value="">Selecione...</option>
                            @foreach($tiposVinculo as $id => $nome)
                                <option value="{{ $id }}" @selected(old('segundo_responsavel.tipo_vinculo_id') == $id)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="resp2_tel">Telefone</label>
                        <input type="text" id="resp2_tel" name="segundo_responsavel[telefone]" value="{{ old('segundo_responsavel.telefone') }}">
                    </div>
                    <div>
                        <label for="resp2_email">E-mail</label>
                        <input type="email" id="resp2_email" name="segundo_responsavel[email]" value="{{ old('segundo_responsavel.email') }}">
                    </div>
                    <div class="full">
                        <label class="check">
                            <input type="checkbox" name="segundo_responsavel[is_financeiro]" value="1" @checked(old('segundo_responsavel.is_financeiro'))>
                            <span>Também é responsável financeiro</span>
                        </label>
                    </div>
                    <div>
                        <label for="resp2_pct">% do contrato sob sua responsabilidade <small>(se ambos forem financeiros)</small></label>
                        <input type="number" id="resp2_pct" name="segundo_responsavel[percentual]" min="1" max="99" placeholder="50" value="{{ old('segundo_responsavel.percentual') }}">
                    </div>
                </div>
            </details>

            {{-- ALUNOS --}}
            @foreach($interessado->dependentes as $i => $dependente)
                <fieldset>
                    <legend>👦 {{ $dependente->nome_crianca }}</legend>
                    <input type="hidden" name="dependentes[{{ $i }}][id]" value="{{ $dependente->id }}">
                    <div class="grid">
                        <div>
                            <label for="nasc_{{ $dependente->id }}">Data de nascimento</label>
                            <input type="date" id="nasc_{{ $dependente->id }}" name="dependentes[{{ $i }}][data_nascimento]" max="{{ now()->toDateString() }}" value="{{ old("dependentes.$i.data_nascimento", filled($dependente->data_nascimento) ? \Illuminate\Support\Carbon::parse($dependente->data_nascimento)->toDateString() : null) }}" required>
                        </div>
                        <div>
                            <label for="cpf_{{ $dependente->id }}">CPF do aluno <small>(se tiver)</small></label>
                            <input type="text" id="cpf_{{ $dependente->id }}" name="dependentes[{{ $i }}][cpf]" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" data-mask="cpf" value="{{ old("dependentes.$i.cpf") }}">
                        </div>
                        <div>
                            <label for="sexo_{{ $dependente->id }}">Sexo</label>
                            <select id="sexo_{{ $dependente->id }}" name="dependentes[{{ $i }}][sexo]">
                                <option value="">Prefiro não informar</option>
                                @foreach($sexos as $sexo)
                                    <option value="{{ $sexo->value }}" @selected(old("dependentes.$i.sexo") === $sexo->value)>{{ $sexo->getLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="serie_{{ $dependente->id }}">Série / Ano de Interesse</label>
                            <select id="serie_{{ $dependente->id }}" name="dependentes[{{ $i }}][serie_id]" required>
                                <option value="">Selecione...</option>
                                @foreach($series->groupBy(fn ($s) => $s->curso?->nome_externo ?? $s->curso?->nome_interno ?? 'Outros') as $cursoNome => $seriesDoCurso)
                                    <optgroup label="{{ $cursoNome }}">
                                        @foreach($seriesDoCurso as $serie)
                                            <option value="{{ $serie->id }}" @selected(old("dependentes.$i.serie_id", $dependente->serie_id) == $serie->id)>{{ $serie->nome }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="full">
                            <label for="turno_{{ $dependente->id }}">Turno de Preferência</label>
                            <select id="turno_{{ $dependente->id }}" name="dependentes[{{ $i }}][turno_preferencia]">
                                <option value="">Sem preferência</option>
                                <option value="Manhã" @selected(old("dependentes.$i.turno_preferencia") === 'Manhã')>Manhã</option>
                                <option value="Tarde" @selected(old("dependentes.$i.turno_preferencia") === 'Tarde')>Tarde</option>
                                <option value="Integral" @selected(old("dependentes.$i.turno_preferencia") === 'Integral')>Integral</option>
                            </select>
                        </div>
                    </div>
                </fieldset>
            @endforeach

            {{-- LGPD --}}
            <div class="lgpd">
                🔒 Os dados informados serão usados <strong>exclusivamente</strong> para a análise e a efetivação da matrícula
                e para o contato da escola com você, conforme a Lei Geral de Proteção de Dados (LGPD).
                <label class="check">
                    <input type="checkbox" name="lgpd_aceite" value="1" @checked(old('lgpd_aceite')) required>
                    <span>Li e concordo com o tratamento dos meus dados e dos dados do(s) aluno(s) para esta finalidade.</span>
                </label>
            </div>

            <button type="submit" class="btn">Enviar pré-matrícula</button>
        </form>

        <p class="aviso">
            Este link é pessoal, de uso único, e expira em {{ $interessado->token_convite_expira_em->format('d/m/Y') }}.<br>
            A pré-matrícula não garante a vaga: a secretaria entrará em contato para concluir o processo.
        </p>
    </div>

    <script>
        // Máscara de CPF
        document.querySelectorAll('[data-mask="cpf"]').forEach(function (el) {
            el.addEventListener('input', function () {
                var v = el.value.replace(/\D/g, '').slice(0, 11);
                v = v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                el.value = v;
            });
        });

        // CEP: máscara e preenchimento automático via ViaCEP (falha silenciosa: o usuário pode digitar)
        var cep = document.getElementById('cep');
        cep.addEventListener('input', function () {
            var v = cep.value.replace(/\D/g, '').slice(0, 8);
            cep.value = v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
        });
        cep.addEventListener('blur', function () {
            var digits = cep.value.replace(/\D/g, '');
            if (digits.length !== 8) { return; }
            fetch('https://viacep.com.br/ws/' + digits + '/json/')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.erro) { return; }
                    if (d.logradouro) { document.getElementById('logradouro').value = d.logradouro; }
                    if (d.bairro) { document.getElementById('bairro').value = d.bairro; }
                    if (d.complemento && !document.getElementById('complemento').value) { document.getElementById('complemento').value = d.complemento; }
                    document.getElementById('cidade_ibge').value = d.ibge || '';
                    document.getElementById('cidade_info').textContent = d.localidade ? ('Cidade: ' + d.localidade + ' - ' + d.uf) : '';
                })
                .catch(function () {});
        });
    </script>
</body>
</html>
