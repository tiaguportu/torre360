@extends('publico.layout')

@section('titulo', 'Sua visita')

@php
    $agendada = $visita->status === \App\Enums\StatusVisitaInteressado::Agendada && ! $visita->data_hora->isPast();
    $quando = $visita->data_hora->copy()->locale('pt_BR')->translatedFormat('l, d/m/Y \à\s H:i');
@endphp

@section('conteudo')
    <div class="card">
        @if ($visita->status === \App\Enums\StatusVisitaInteressado::Cancelada)
            <h1>Visita cancelada</h1>
            <div class="aviso">A visita de {{ $quando }} foi cancelada.</div>
            <a class="botao" href="{{ route('visita.publica.agendar') }}">Marcar outro horário</a>
        @else
            <h1>{{ $agendada ? 'Sua visita está marcada' : 'Sua visita' }}</h1>

            @if (session('visita_marcada'))
                <div class="ok">Visita confirmada! Enviamos os detalhes para {{ $visita->interessado->pessoa->email }}.</div>
            @elseif (session('visita_reagendada'))
                <div class="ok">Horário atualizado.</div>
            @endif

            @error('visita') <div class="aviso">{{ $message }}</div> @enderror

            <table class="tabela">
                <tbody>
                    <tr><th>Quando</th><td><strong>{{ $quando }}</strong></td></tr>
                    <tr><th>Onde</th><td>{{ $visita->unidade?->nome ?? config('app.name') }}{{ $visita->unidade?->telefone ? ' · '.$visita->unidade->telefone : '' }}</td></tr>
                    <tr><th>Quem recebe</th><td>{{ $visita->usuario?->name ?? 'Equipe de admissão' }}</td></tr>
                </tbody>
            </table>

            <p class="mini" style="margin-top:14px">{{ config('visitas.o_que_levar') }}</p>

            @if ($agendada)
                <a class="botao claro" href="{{ route('visita.publica.reagendar', ['token' => request()->route('token')]) }}">Trocar o horário</a>

                <form method="POST" action="{{ route('visita.publica.cancelar', ['token' => request()->route('token')]) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="claro" style="background:#fee2e2;color:#991b1b">Cancelar a visita</button>
                </form>
            @endif
        @endif
    </div>
@endsection
