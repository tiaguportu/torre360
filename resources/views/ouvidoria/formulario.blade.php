@extends('publico.layout')

@section('titulo', 'Ouvidoria')

@section('conteudo')
    <div class="card">
        <h1>Ouvidoria e convivência escolar</h1>
        <p>Use este canal para relatar bullying, assédio, discriminação, violência ou desvio de conduta. Seu relato é lido apenas pelo comitê da escola.</p>
        <p class="mini">Você pode enviar de forma <strong>anônima</strong>: a manifestação não guarda seu nome, seu usuário, seu endereço de rede nem o seu navegador. (Como qualquer site, o servidor mantém por algumas horas um registro técnico de sessão, que não é ligado à manifestação.) Ao enviar, você recebe um <strong>protocolo e um código</strong> para acompanhar a resposta sem precisar de login.</p>
        <p><a href="{{ route('ouvidoria.acompanhar') }}">Já enviei e quero acompanhar</a></p>
    </div>

    @if ($errors->any())
        <div class="aviso">Confira os campos destacados e envie de novo.</div>
    @endif

    <form method="POST" action="{{ route('ouvidoria.enviar') }}" class="card" autocomplete="off">
        @csrf

        {{-- Armadilha para robô: pessoa nunca vê nem preenche. --}}
        <div class="oculto-de-robo" aria-hidden="true">
            <label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <label for="categoria">Assunto *</label>
        <select id="categoria" name="categoria" required>
            <option value="">Selecione...</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->value }}" @selected(old('categoria') === $categoria->value)>{{ $categoria->getLabel() }}</option>
            @endforeach
        </select>
        @error('categoria') <div class="erro">{{ $message }}</div> @enderror

        <label for="descricao">O que aconteceu? *</label>
        <textarea id="descricao" name="descricao" required minlength="20" maxlength="5000">{{ old('descricao') }}</textarea>
        <div class="mini">Conte com o máximo de detalhes que se sentir à vontade para dar. Mínimo de 20 caracteres.</div>
        @error('descricao') <div class="erro">{{ $message }}</div> @enderror

        <label for="local_data">Onde e quando aconteceu</label>
        <input type="text" id="local_data" name="local_data" maxlength="255" value="{{ old('local_data') }}" placeholder="Ex.: pátio, na semana passada">
        @error('local_data') <div class="erro">{{ $message }}</div> @enderror

        <h2>Pessoas envolvidas (opcional)</h2>
        @foreach (range(0, 2) as $i)
            <div class="linha duas">
                <div>
                    <label for="env_nome_{{ $i }}">Nome</label>
                    <input type="text" id="env_nome_{{ $i }}" name="envolvidos[{{ $i }}][nome]" maxlength="120" value="{{ old("envolvidos.$i.nome") }}">
                </div>
                <div>
                    <label for="env_papel_{{ $i }}">Papel</label>
                    <select id="env_papel_{{ $i }}" name="envolvidos[{{ $i }}][papel]">
                        @foreach (['vitima' => 'Vítima', 'autor' => 'Autor(a)', 'testemunha' => 'Testemunha', 'outro' => 'Outro'] as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old("envolvidos.$i.papel") === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endforeach

        <h2>Como você quer enviar?</h2>
        <label><input type="radio" name="anonima" value="1" @checked(old('anonima', '1') === '1')> Anônima (não me identifico)</label>
        <label><input type="radio" name="anonima" value="0" @checked(old('anonima') === '0')> Identificada (a escola pode me procurar)</label>

        <div class="linha duas">
            <div>
                <label for="nome">Seu nome (só se identificada)</label>
                <input type="text" id="nome" name="nome" maxlength="120" value="{{ old('nome') }}">
                @error('nome') <div class="erro">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="email">E-mail ou telefone para contato</label>
                <input type="text" id="email" name="contato" maxlength="120" value="{{ old('contato') }}">
                @error('contato') <div class="erro">{{ $message }}</div> @enderror
            </div>
        </div>

        <p class="mini" style="margin-top:14px">Se houver risco imediato para alguém, procure a direção da escola, o Conselho Tutelar ou ligue 190 (emergência) ou 100 (Disque Direitos Humanos).</p>

        <button type="submit">Enviar à ouvidoria</button>
    </form>
@endsection
