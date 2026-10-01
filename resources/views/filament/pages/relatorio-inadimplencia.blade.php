<x-filament-panels::page>
    @php($resumo = $this->getResumo())

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Faturas em Atraso</div>
            <div class="text-2xl font-bold">{{ $resumo['total_faturas'] }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Devido</div>
            <div class="text-2xl font-bold text-danger-600">R$ {{ number_format($resumo['total_devido'], 2, ',', '.') }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Responsáveis Inadimplentes</div>
            <div class="text-2xl font-bold">{{ $resumo['total_responsaveis'] }}</div>
        </x-filament::section>
    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
