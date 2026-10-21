<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    @php
        $informes = $this->getInformes();
        $ano = $this->anoValido();
        $reais = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
    @endphp

    <div class="pf-stack">
        <div class="pf-barra">
            <label for="informe-ano"><strong>Ano-calendário</strong></label>
            <select id="informe-ano" wire:model.live="ano" style="border-radius: .5rem; padding: .25rem .5rem;">
                @foreach ($this->getAnos() as $valor => $rotulo)
                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        @forelse ($informes as $informe)
            <div class="pf-box" wire:key="informe-{{ $informe['matricula_id'] }}">
                <h3>{{ $informe['aluno']['nome'] }}</h3>
                <div class="pf-muted">{{ $informe['turma'] }}{{ $informe['serie'] ? ' · '.$informe['serie'] : '' }}</div>
                <div style="margin: .5rem 0;">
                    Total pago em {{ $ano }}: <strong>{{ $reais($informe['total_pago']) }}</strong><br>
                    Parte estimada como despesa com instrução: <strong>{{ $reais($informe['total_dedutivel']) }}</strong>
                </div>
                <x-filament::button tag="a" size="sm" icon="heroicon-o-arrow-down-tray" target="_blank" :href="route('informe-anual.aluno', ['ano' => $ano, 'matricula' => $informe['matricula_id']])">
                    Baixar informe (PDF)
                </x-filament::button>
            </div>
        @empty
            <div class="pf-vazio">Não encontramos pagamentos recebidos em {{ $ano }} para os alunos da sua família.</div>
        @endforelse
    </div>
</x-filament-panels::page>
