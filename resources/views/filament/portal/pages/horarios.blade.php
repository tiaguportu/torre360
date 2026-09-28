@php
    $agenda = $this->getAgenda();
    $hoje = now()->toDateString();
    $hora = fn (?string $valor): string => $valor ? substr($valor, 0, 5) : '';
@endphp

<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if ($agenda === null)
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @else
            <div class="pf-barra">
                <strong>{{ $agenda['inicio']->format('d/m') }} a {{ $agenda['fim']->format('d/m/Y') }}</strong>
                <div class="pf-acoes">
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="semanaAnterior">Anterior</x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="semanaAtual">Hoje</x-filament::button>
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" wire:click="proximaSemana">Próxima</x-filament::button>
                </div>
            </div>

            @if (collect($agenda['dias'])->every(fn ($dia) => $dia['aulas']->isEmpty() && $dia['nao_letivo'] === null))
                <div class="pf-vazio">Não há aulas cadastradas para esta semana.</div>
            @else
                <div class="pf-semana">
                    @foreach ($agenda['dias'] as $dia)
                        <section class="pf-dia {{ $dia['data']->toDateString() === $hoje ? 'pf-hoje' : '' }}">
                            <h4>{{ $dia['nome'] }} <span class="pf-muted">{{ $dia['data']->format('d/m') }}</span></h4>

                            @if ($dia['nao_letivo'])
                                <div class="pf-nl">{{ $dia['nao_letivo'] }} (sem aula)</div>
                            @endif

                            @forelse ($dia['aulas'] as $aula)
                                <div class="pf-aula">
                                    <span class="pf-hora">
                                        {{ $hora($aula->hora_inicio) }}@if ($aula->hora_fim) – {{ $hora($aula->hora_fim) }}@endif
                                    </span>
                                    <strong>{{ $aula->disciplina?->nome ?? 'Aula' }}</strong>
                                    @if ($aula->professor?->nome)
                                        <span class="pf-muted">Prof. {{ $aula->professor->nome }}</span>
                                    @endif
                                    @if ($aula->conteudo_ministrado)
                                        <div class="pf-muted"><em>Conteúdo:</em> {{ $aula->conteudo_ministrado }}</div>
                                    @endif
                                    @if ($aula->dever_casa)
                                        <div class="pf-muted"><em>Dever de casa:</em> {{ $aula->dever_casa }}</div>
                                    @endif
                                </div>
                            @empty
                                @unless ($dia['nao_letivo'])
                                    <span class="pf-muted">Sem aulas</span>
                                @endunless
                            @endforelse
                        </section>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
