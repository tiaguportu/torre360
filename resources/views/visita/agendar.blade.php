@extends('publico.layout')

@section('titulo', 'Agendar visita')

@php
    $diaPorExtenso = fn (string $data) => \Carbon\Carbon::parse($data)->locale('pt_BR')->translatedFormat('l, d/m');
@endphp

@section('conteudo')
    <div class="card">
        <h1>Agende uma visita à escola</h1>
        <p>Escolha o dia e o horário. Você recebe a confirmação por e-mail, com o endereço e um link para trocar ou cancelar se precisar.</p>
    </div>

    @if ($unidades->count() > 1)
        <form method="GET" action="{{ route('visita.publica.agendar') }}" class="card">
            <label for="unidade">Unidade</label>
            <select id="unidade" name="unidade" required>
                <option value="">Selecione...</option>
                @foreach ($unidades as $unidade)
                    <option value="{{ $unidade->id }}" @selected($unidadeId === $unidade->id)>{{ $unidade->nome }}</option>
                @endforeach
            </select>
            <button type="submit">Ver horários</button>
        </form>
    @endif

    @if ($unidadeId)
        @if ($horarios === [])
            <div class="card"><div class="aviso">Não há horários livres no momento. Fale com a escola para combinar sua visita.</div></div>
        @else
            @if ($errors->any())
                <div class="aviso">Confira os campos destacados e envie de novo.</div>
            @endif

            <form method="POST" action="{{ route('visita.publica.reservar') }}" class="card" autocomplete="off" id="formVisita">
                @csrf
                <input type="hidden" name="unidade_id" value="{{ $unidadeId }}">
                <input type="hidden" name="recaptcha_token" id="recaptchaToken">
                <div class="oculto-de-robo" aria-hidden="true"><label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <h2>Dia e horário</h2>
                @foreach ($horarios as $data => $horas)
                    <p style="margin-bottom:4px"><strong>{{ $diaPorExtenso($data) }}</strong></p>
                    <p>
                        @foreach (array_keys($horas) as $hora)
                            <label style="display:inline-block;font-weight:400;margin:0 12px 6px 0">
                                <input type="radio" name="data_hora" value="{{ $data }} {{ $hora }}" @checked(old('data_hora') === "$data $hora") required> {{ $hora }}
                            </label>
                        @endforeach
                    </p>
                @endforeach
                @error('data_hora') <div class="erro">{{ $message }}</div> @enderror

                <h2>Seus dados</h2>
                <label for="responsavel_nome">Seu nome *</label>
                <input type="text" id="responsavel_nome" name="responsavel_nome" required maxlength="255" value="{{ old('responsavel_nome') }}">
                @error('responsavel_nome') <div class="erro">{{ $message }}</div> @enderror

                <div class="linha duas">
                    <div>
                        <label for="responsavel_email">E-mail *</label>
                        <input type="email" id="responsavel_email" name="responsavel_email" required maxlength="255" value="{{ old('responsavel_email') }}">
                        @error('responsavel_email') <div class="erro">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="responsavel_telefone">Telefone ou WhatsApp *</label>
                        <input type="tel" id="responsavel_telefone" name="responsavel_telefone" required maxlength="30" value="{{ old('responsavel_telefone') }}">
                        @error('responsavel_telefone') <div class="erro">{{ $message }}</div> @enderror
                    </div>
                </div>

                <h2>Sobre o estudante</h2>
                <label for="aluno_nome">Nome do estudante *</label>
                <input type="text" id="aluno_nome" name="aluno_nome" required maxlength="255" value="{{ old('aluno_nome') }}">
                @error('aluno_nome') <div class="erro">{{ $message }}</div> @enderror

                <div class="linha duas">
                    <div>
                        <label for="serie_id">Série de interesse</label>
                        <select id="serie_id" name="serie_id">
                            <option value="">Ainda não sei</option>
                            @foreach ($series as $serie)
                                <option value="{{ $serie->id }}" @selected((string) old('serie_id') === (string) $serie->id)>{{ $serie->curso?->nome_externo ? $serie->curso->nome_externo.' — ' : '' }}{{ $serie->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="turno_preferencia">Turno de preferência</label>
                        <select id="turno_preferencia" name="turno_preferencia">
                            <option value="">Sem preferência</option>
                            @foreach (['Manhã', 'Tarde', 'Integral'] as $turno)
                                <option value="{{ $turno }}" @selected(old('turno_preferencia') === $turno)>{{ $turno }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label style="font-weight:400;margin-top:16px"><input type="checkbox" name="consentimento" value="1" required> Autorizo a escola a usar estes dados, inclusive os do estudante, para organizar a minha visita e o atendimento de admissão.</label>
                @error('consentimento') <div class="erro">{{ $message }}</div> @enderror

                <button type="submit">Confirmar visita</button>
            </form>
        @endif
    @endif
@endsection

{{-- reCAPTCHA v3, como no formulário de captação: sem as chaves configuradas, a página envia direto e a regra de validação não exige o token. --}}
@if (config('services.recaptcha.site_key'))
    @push('scripts')
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
        <script>
            (function () {
                var form = document.getElementById('formVisita');
                if (!form) { return; }
                form.addEventListener('submit', function (e) {
                    if (typeof grecaptcha === 'undefined') { return; }
                    e.preventDefault();
                    grecaptcha.ready(function () {
                        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'agendar_visita' }).then(function (token) {
                            document.getElementById('recaptchaToken').value = token;
                            form.submit();
                        });
                    });
                });
            })();
        </script>
    @endpush
@endif
