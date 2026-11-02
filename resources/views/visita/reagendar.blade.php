@extends('publico.layout')

@section('titulo', 'Trocar o horário da visita')

@php
    $diaPorExtenso = fn (string $data) => \Carbon\Carbon::parse($data)->locale('pt_BR')->translatedFormat('l, d/m');
@endphp

@section('conteudo')
    <div class="card">
        <h1>Trocar o horário da visita</h1>
        <p>Horário atual: <strong>{{ $visita->data_hora->copy()->locale('pt_BR')->translatedFormat('l, d/m/Y \à\s H:i') }}</strong>. O horário antigo volta a ficar livre para outra família.</p>
    </div>

    @if ($horarios === [])
        <div class="card"><div class="aviso">Não há outros horários livres agora. Fale com a escola.</div></div>
    @else
        <form method="POST" action="{{ route('visita.publica.reagendar.salvar', ['token' => request()->route('token')]) }}" class="card">
            @csrf
            @foreach ($horarios as $data => $horas)
                <p style="margin-bottom:4px"><strong>{{ $diaPorExtenso($data) }}</strong></p>
                <p>
                    @foreach (array_keys($horas) as $hora)
                        <label style="display:inline-block;font-weight:400;margin:0 12px 6px 0">
                            <input type="radio" name="data_hora" value="{{ $data }} {{ $hora }}" required> {{ $hora }}
                        </label>
                    @endforeach
                </p>
            @endforeach
            @error('data_hora') <div class="erro">{{ $message }}</div> @enderror

            <button type="submit">Confirmar novo horário</button>
            <a class="botao claro" href="{{ route('visita.publica.mostrar', ['token' => request()->route('token')]) }}">Voltar</a>
        </form>
    @endif
@endsection
