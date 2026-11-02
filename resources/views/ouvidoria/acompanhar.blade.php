@extends('publico.layout')

@section('titulo', 'Acompanhar manifestação')

@section('conteudo')
    @if (! isset($denuncia))
        <form method="POST" action="{{ route('ouvidoria.acompanhar.consultar') }}" class="card" autocomplete="off">
            @csrf
            <h1>Acompanhar a manifestação</h1>
            <p>Informe o protocolo e o código que você recebeu ao enviar.</p>

            @if (! empty($erro))
                <div class="aviso">{{ $erro }}</div>
            @endif

            <label for="protocolo">Protocolo</label>
            <input type="text" id="protocolo" name="protocolo" required maxlength="32" value="{{ old('protocolo') }}" placeholder="OUV-2026-XXXXXX">

            <label for="codigo">Código de acompanhamento</label>
            <input type="text" id="codigo" name="codigo" required maxlength="16" placeholder="XXXXX-XXXXX">

            <button type="submit">Consultar</button>
        </form>
    @else
        <div class="card">
            <h1>Manifestação {{ $denuncia->protocolo }}</h1>
            <p>Situação: <strong>{{ $denuncia->status->getLabel() }}</strong>. Prazo para a primeira providência: {{ $denuncia->prazo_em?->format('d/m/Y') ?? '—' }}.</p>

            <table class="tabela">
                <thead><tr><th>Data</th><th>O que aconteceu</th></tr></thead>
                <tbody>
                    @foreach ($movimentacoes as $movimentacao)
                        <tr>
                            <td>{{ $movimentacao->created_at->format('d/m/Y H:i') }}</td>
                            <td><strong>{{ $movimentacao->tipo->getLabel() }}</strong><br>{!! nl2br(e($movimentacao->texto)) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (! $denuncia->status->estaEncerrada())
            <form method="POST" action="{{ route('ouvidoria.acompanhar.complementar') }}" class="card" autocomplete="off">
                @csrf
                <input type="hidden" name="protocolo" value="{{ $denuncia->protocolo }}">
                <input type="hidden" name="codigo" value="{{ $codigo }}">
                <h2>Quer acrescentar alguma informação?</h2>
                <textarea name="texto" required minlength="5" maxlength="5000"></textarea>
                @error('texto') <div class="erro">{{ $message }}</div> @enderror
                <button type="submit">Enviar informação</button>
            </form>
        @endif

        <a class="botao claro" href="{{ route('ouvidoria.acompanhar') }}">Sair</a>
    @endif
@endsection
