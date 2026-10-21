<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if (! $this->getMatriculaSelecionada())
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @else
            @php
                $segunda = $this->getSegundaDaSemana();
                $dias = $this->getDias();
                $primeiroNome = \Illuminate\Support\Str::of($this->getMatriculaSelecionada()->pessoa?->nome ?? 'o aluno')->before(' ')->toString();
            @endphp

            <div class="pf-barra">
                <strong>Semana de {{ $segunda->format('d/m') }} a {{ $segunda->copy()->endOfWeek()->format('d/m/Y') }}</strong>
                <div class="pf-acoes">
                    <x-filament::button size="sm" color="gray" wire:click="semanaAnterior" icon="heroicon-o-chevron-left">Anterior</x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="proximaSemana" icon="heroicon-o-chevron-right" icon-position="after">Próxima</x-filament::button>
                </div>
            </div>

            @if ($this->alunoTemRestricaoRegistrada())
                <div class="pf-aviso">
                    A ficha médica de {{ $primeiroNome }} tem restrição alimentar registrada. As refeições que contêm esse alérgeno aparecem destacadas abaixo; confira sempre os ingredientes com a escola.
                </div>
            @endif

            @forelse ($dias as $dia)
                <div class="pf-box">
                    <h3>{{ ucfirst($dia['data']->locale('pt_BR')->translatedFormat('l, d/m/Y')) }}</h3>

                    @foreach ($dia['refeicoes'] as $refeicao)
                        @php($c = $refeicao['cardapio'])
                        <div style="margin-bottom: .75rem;">
                            <strong>{{ $c->tipo_refeicao->getLabel() }}</strong>
                            <div>{{ $c->descricao }}</div>

                            @if ($c->alergenosEnum())
                                <div class="pf-muted">Contém: {{ collect($c->alergenosEnum())->map(fn ($a) => $a->getLabel())->implode(', ') }}</div>
                            @endif

                            @if ($c->observacoes)
                                <div class="pf-muted">{{ $c->observacoes }}</div>
                            @endif

                            @if ($refeicao['confirmados'])
                                <div class="pf-nl" style="margin-top: .375rem;">
                                    Atenção: contém {{ implode(', ', $refeicao['confirmados']) }}, restrição registrada na ficha de {{ $primeiroNome }}.
                                </div>
                            @elseif ($refeicao['possiveis'])
                                <div class="pf-aviso" style="margin-top: .375rem;">
                                    Atenção: pode conter {{ implode(', ', $refeicao['possiveis']) }}. A ficha de {{ $primeiroNome }} cita essa restrição em texto livre; confira com a escola.
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="pf-vazio">O cardápio desta semana ainda não foi publicado.</div>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
