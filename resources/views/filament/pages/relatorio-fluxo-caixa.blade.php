<x-filament-panels::page>
    @php($resumo = $this->getResumo())
    @php($linhas = $this->getFluxoMensal())

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total de Entradas</div>
            <div class="text-2xl font-bold text-success-600">R$ {{ number_format($resumo['total_entradas'], 2, ',', '.') }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total de Saídas</div>
            <div class="text-2xl font-bold text-danger-600">R$ {{ number_format($resumo['total_saidas'], 2, ',', '.') }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Saldo do Período</div>
            <div class="text-2xl font-bold {{ $resumo['saldo_periodo'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                R$ {{ number_format($resumo['saldo_periodo'], 2, ',', '.') }}
            </div>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Mês</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Entradas</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Saídas</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Saldo</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-white/5 dark:divide-white/10">
                    @foreach($linhas as $linha)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $linha['label'] }}</td>
                            <td class="px-4 py-2 text-right text-success-600">R$ {{ number_format($linha['entradas'], 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right text-danger-600">R$ {{ number_format($linha['saidas'], 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $linha['saldo'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                R$ {{ number_format($linha['saldo'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
