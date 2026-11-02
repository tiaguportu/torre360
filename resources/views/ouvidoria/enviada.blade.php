@extends('publico.layout')

@section('titulo', 'Manifestação recebida')

@section('conteudo')
    <div class="card">
        <h1>Recebemos a sua manifestação</h1>
        <div class="ok">Guarde o protocolo e o código abaixo. <strong>O código aparece só nesta tela</strong> e não pode ser recuperado depois.</div>

        <p class="mini">Protocolo</p>
        <p><span class="codigo">{{ $protocolo }}</span></p>

        <p class="mini">Código de acompanhamento</p>
        <p><span class="codigo">{{ $codigo }}</span></p>

        <p>Com os dois, você acompanha a resposta e pode enviar novas informações em <a href="{{ route('ouvidoria.acompanhar') }}">Acompanhar</a>.</p>
        <p class="mini">Prazo para a primeira providência do comitê: {{ $prazo }}.</p>
    </div>
@endsection
