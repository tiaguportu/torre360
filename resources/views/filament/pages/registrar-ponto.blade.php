<x-filament-panels::page>
    @php
        $funcionario = $this->getFuncionario();
        $config = $this->getConfiguracao();
        $marcacoes = $this->getMarcacoesDeHoje();
        $ultimo = $this->getUltimoRegistro();
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">{{ $funcionario?->pessoa?->nome }}</x-slot>
            <x-slot name="description">{{ $funcionario?->cargo }} · {{ today()->translatedFormat('l, d \d\e F \d\e Y') }}</x-slot>

            @if (! $config || ! $config->ativo)
                <p class="text-sm text-danger-600">O ponto da sua unidade ainda não foi configurado. Procure o RH.</p>
            @else
                <div class="flex flex-col items-center gap-4 py-4" x-data="{ agora: new Date() }" x-init="setInterval(() => agora = new Date(), 1000)">
                    <div class="text-5xl font-semibold tabular-nums" x-text="agora.toLocaleTimeString('pt-BR')"></div>
                    <p class="text-xs text-gray-500">Relógio da tela, só para referência. O horário gravado é o do servidor.</p>

                    <x-filament::button size="xl" icon="heroicon-o-finger-print" wire:click="registrar" wire:loading.attr="disabled">
                        Registrar ponto agora
                    </x-filament::button>
                </div>

                @if (! $config->modo->ehOficial())
                    <p class="mt-2 text-xs text-gray-500">Modo: {{ $config->modo->getLabel() }}. O comprovante deste registro não é um comprovante de REP oficial.</p>
                @endif
            @endif

            @if ($ultimo)
                <div class="mt-4 rounded-lg border border-success-300 bg-success-50 p-4 text-sm dark:border-success-700 dark:bg-success-950">
                    <strong>Ponto registrado às {{ $ultimo->momento->format('H:i:s') }}</strong> · NSR {{ $ultimo->nsr }}
                    @unless ($ultimo->hora_legal_ok)
                        <span class="block text-warning-700">A hora legal não pôde ser confirmada neste registro. A marcação vale, e o RH foi sinalizado.</span>
                    @endunless
                    <a href="{{ route('ponto.comprovante', $ultimo) }}" target="_blank" rel="noopener" class="ml-2 underline">Baixar comprovante (PDF)</a>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Marcações de hoje">
            @forelse ($marcacoes as $marcacao)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10">
                    <span class="font-medium tabular-nums">{{ $marcacao->momento->format('H:i:s') }}</span>
                    <span class="text-gray-500">NSR {{ $marcacao->nsr }} · {{ $marcacao->origem->getLabel() }}</span>
                    <a href="{{ route('ponto.comprovante', $marcacao) }}" target="_blank" rel="noopener" class="text-primary-600 underline">Comprovante</a>
                </div>
            @empty
                <p class="text-sm text-gray-500">Nenhuma marcação hoje ainda.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
