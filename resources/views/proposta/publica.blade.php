@extends('publico.layout')

@section('titulo', 'Proposta '.$proposta->codigo)

@php
    $situacao = \App\Http\Controllers\Comercial\PropostaPublicaController::situacaoParaTexto($proposta);
    $moeda = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
    $temDesconto = (float) $proposta->valor_desconto_mensal > 0;
@endphp

@section('conteudo')
    <div class="card">
        <h1>Proposta de matrícula {{ $proposta->codigo }}</h1>
        <p class="mini">{{ $proposta->unidade?->nome ?? config('app.name') }}</p>

        @if (session('aceite_ok') || $situacao === 'aceita')
            <div class="ok"><strong>Proposta aceita{{ $proposta->aceite_nome ? ' por '.$proposta->aceite_nome : '' }} em {{ $proposta->aceita_em?->format('d/m/Y \à\s H:i') }}.</strong> A escola entrará em contato para a matrícula.</div>
        @elseif ($situacao === 'convertida')
            <div class="ok">Esta proposta já foi usada na matrícula. Obrigado!</div>
        @elseif ($situacao === 'expirada')
            <div class="aviso">Esta proposta passou da validade{{ $proposta->validade ? ' ('.$proposta->validade->format('d/m/Y').')' : '' }}. Fale com a escola para saber se a condição ainda está disponível.</div>
        @endif

        <table class="tabela">
            <tbody>
                <tr><th>Responsável</th><td>{{ $proposta->responsavel_nome }}</td></tr>
                @if ($proposta->aluno_nome)<tr><th>Estudante</th><td>{{ $proposta->aluno_nome }}</td></tr>@endif
                <tr><th>Segmento</th><td>{{ trim(($proposta->curso?->nome ?? '').' — '.($proposta->serie?->nome ?? ''), ' —') }}{{ $proposta->turno ? ' · '.$proposta->turno->nome : '' }}</td></tr>
                @if ($proposta->quantidade_alunos > 1)<tr><th>Estudantes</th><td>{{ $proposta->quantidade_alunos }}</td></tr>@endif
                <tr><th>Mensalidade de tabela</th><td>{{ $moeda($proposta->valor_tabela_mensal) }}</td></tr>
                @if ($temDesconto)
                    <tr><th>Desconto</th><td>{{ $moeda($proposta->valor_desconto_mensal) }} por mês</td></tr>
                @endif
                <tr><th>Mensalidade nesta proposta</th><td><strong>{{ $moeda($proposta->valor_liquido_mensal) }}</strong></td></tr>
                <tr><th>Parcelas</th><td>{{ $proposta->quantidade_parcelas }}</td></tr>
                <tr><th>Total no período</th><td>{{ $moeda($proposta->valor_total_anual) }}</td></tr>
                @if ($proposta->validade)<tr><th>Válida até</th><td>{{ $proposta->validade->format('d/m/Y') }}</td></tr>@endif
            </tbody>
        </table>

        <p class="mini" style="margin-top:14px">O aceite desta proposta registra o seu interesse e reserva a condição acima até a data de validade. <strong>Ele não substitui o contrato de prestação de serviços</strong>, que é assinado na matrícula. O valor da matrícula será o desta proposta.</p>
    </div>

    @if ($situacao === 'aberta')
        <form method="POST" action="{{ route('proposta.publica.aceitar', ['token' => request()->route('token')]) }}" class="card" autocomplete="off">
            @csrf
            <h2>Aceitar a proposta</h2>

            <label for="nome">Seu nome completo (responsável pelo estudante)</label>
            <input type="text" id="nome" name="nome" required minlength="5" maxlength="120" value="{{ old('nome') }}">
            @error('nome') <div class="erro">{{ $message }}</div> @enderror

            <label style="font-weight:400;margin-top:14px"><input type="checkbox" name="concordo" value="1" required> Li as condições acima e quero aceitar esta proposta.</label>
            @error('concordo') <div class="erro">{{ $message }}</div> @enderror

            <button type="submit">Aceitar proposta</button>
        </form>
    @endif
@endsection
