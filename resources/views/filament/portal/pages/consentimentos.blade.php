<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if ($this->consentimentos->isEmpty())
            <div class="pf-vazio">Nenhum tipo de consentimento ativo no momento.</div>
        @else
            @foreach ($this->consentimentos as $consentimento)
                @php
                    $statusEfetivo = $consentimento->statusEfetivo();
                    $precisaResposta = $consentimento->precisaResposta();
                @endphp
                <section class="pf-box">
                    <h3>{{ $consentimento->tipoConsentimento->nome }}</h3>
                    <p>{{ $consentimento->tipoConsentimento->texto_padrao }}</p>

                    <p>
                        Status atual:
                        <strong>{{ $statusEfetivo->getLabel() }}</strong>
                        @if ($statusEfetivo === \App\Enums\StatusConsentimento::Autorizado && $consentimento->vigencia_fim)
                            (válido até {{ $consentimento->vigencia_fim->format('d/m/Y') }})
                        @endif
                    </p>

                    @if ($precisaResposta)
                        <div style="display:flex; gap:.5rem; margin-top:.5rem;">
                            <button
                                type="button"
                                wire:click="responder({{ $consentimento->id }}, 'autorizado')"
                                class="fi-btn fi-color-success"
                                style="padding:.5rem 1rem; border-radius:.5rem; background:#16a34a; color:#fff;"
                            >
                                Autorizar
                            </button>
                            <button
                                type="button"
                                wire:click="responder({{ $consentimento->id }}, 'nao_autorizado')"
                                style="padding:.5rem 1rem; border-radius:.5rem; background:#dc2626; color:#fff;"
                            >
                                Não Autorizar
                            </button>
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="responder({{ $consentimento->id }}, '{{ $consentimento->status === \App\Enums\StatusConsentimento::Autorizado ? 'nao_autorizado' : 'autorizado' }}')"
                            style="padding:.4rem .8rem; border-radius:.5rem; background:#e5e7eb; color:#111827; margin-top:.5rem;"
                        >
                            Alterar resposta
                        </button>
                    @endif
                </section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
