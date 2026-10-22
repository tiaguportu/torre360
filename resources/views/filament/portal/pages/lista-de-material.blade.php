<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    @php
        $lista = $this->getLista();
        $grupos = $this->getGrupos($lista);
    @endphp

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if (! $this->getMatriculaSelecionada())
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @elseif (! $lista)
            <div class="pf-vazio">A escola ainda não publicou a lista de material desta série. Assim que for publicada, ela aparece aqui.</div>
        @else
            <div class="pf-box">
                <h3>{{ $lista->serie?->nome }} — {{ $lista->periodoLetivo?->nome }}</h3>
                <div class="pf-muted">Publicada em {{ $lista->publicada_em?->format('d/m/Y') }}</div>
                @if (filled($lista->observacao))
                    <p style="margin-top: .5rem;">{{ $lista->observacao }}</p>
                @endif
                <div style="margin-top: .75rem;">
                    <x-filament::button tag="a" size="sm" icon="heroicon-o-arrow-down-tray" target="_blank" :href="route('lista-material.pdf', ['lista' => $lista->id])">
                        Baixar a lista (PDF)
                    </x-filament::button>
                </div>
            </div>

            @foreach ($grupos as $categoria => $itens)
                <div class="pf-box" wire:key="grupo-{{ $loop->index }}">
                    <h3>{{ $categoria }}</h3>
                    <div class="pf-scroll">
                        <table class="pf-tabela">
                            <thead><tr><th style="width: 5rem;">Qtd.</th><th style="width: 9rem;">Unidade</th><th>Item</th></tr></thead>
                            <tbody>
                                @foreach ($itens as $item)
                                    <tr>
                                        <td>{{ $item->quantidade }}</td>
                                        <td>{{ $item->unidade ?: '—' }}</td>
                                        <td>{{ $item->descricao }}@if (filled($item->observacao)) <span class="pf-muted">— {{ $item->observacao }}</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <p class="pf-muted">
                Esta lista traz apenas material de uso individual do aluno. A escola não exige marca, loja nem kit fechado.
                Material de uso coletivo é custeado pela mensalidade (Lei nº 9.870/1999, art. 1º, § 7º).
            </p>
        @endif
    </div>
</x-filament-panels::page>
