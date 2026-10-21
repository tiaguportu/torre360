<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if (! $this->getMatriculaSelecionada())
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @else
            @php
                $pareceres = $this->getPareceres();
                $itens = $this->getItens();
            @endphp

            <div class="pf-box">
                <h3>Pareceres</h3>
                @forelse ($pareceres as $parecer)
                    <div style="margin-bottom: 1rem;">
                        <strong>{{ $parecer->etapaAvaliativa?->nome }}</strong>
                        <span class="pf-muted"> · publicado em {{ $parecer->publicado_em?->format('d/m/Y') }}</span>
                        <div style="white-space: pre-line; margin-top: .25rem;">{{ $parecer->texto }}</div>
                    </div>
                @empty
                    <div class="pf-vazio">Nenhum parecer foi publicado ainda.</div>
                @endforelse
            </div>

            <div class="pf-box">
                <h3>Portfólio</h3>
                @forelse ($itens as $item)
                    <div style="margin-bottom: 1.25rem;">
                        <strong>{{ $item->titulo }}</strong>
                        <span class="pf-muted"> · {{ $item->data_registro->format('d/m/Y') }}</span>

                        @if ($item->descricao)
                            <div style="white-space: pre-line;">{{ $item->descricao }}</div>
                        @endif

                        @if ($item->habilidades->isNotEmpty())
                            <div class="pf-muted">Habilidades: {{ $item->habilidades->map(fn ($h) => ($h->codigo ? $h->codigo.' ' : '').$h->nome)->implode(' · ') }}</div>
                        @endif

                        @foreach ($item->caminhosDosAnexos() as $indice => $caminho)
                            @if ($item->anexoEhImagem($indice))
                                <a href="{{ $item->urlDoAnexo($indice) }}" target="_blank" rel="noopener">
                                    <img src="{{ $item->urlDoAnexo($indice) }}" alt="{{ $item->titulo }}" loading="lazy" style="max-width: 16rem; width: 100%; border-radius: .5rem; margin-top: .5rem;">
                                </a>
                            @else
                                <div><a href="{{ $item->urlDoAnexo($indice) }}" target="_blank" rel="noopener" style="text-decoration: underline;">{{ basename($caminho) }}</a></div>
                            @endif
                        @endforeach
                    </div>
                @empty
                    <div class="pf-vazio">A escola ainda não compartilhou evidências do portfólio.</div>
                @endforelse
            </div>
        @endif
    </div>
</x-filament-panels::page>
