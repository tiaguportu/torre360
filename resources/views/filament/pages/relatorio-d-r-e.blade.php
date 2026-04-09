<x-filament-panels::page>
    <x-filament-panels::form wire:submit="generateDRE">
        {{ $this->getSchema('content') }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-m-arrow-path">
                Atualizar Relatório
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    @if($dreData)
        <div class="space-y-6 mt-8">
            {{-- Resumo --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-filament::section class="bg-primary-50">
                    <div class="flex flex-col items-center">
                        <span class="text-sm text-gray-500 uppercase font-bold">Total Receitas</span>
                        <span class="text-2xl font-bold text-success-600">
                            R$ {{ number_format($dreData['resumo']['total_receitas'], 2, ',', '.') }}
                        </span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex flex-col items-center">
                        <span class="text-sm text-gray-500 uppercase font-bold">Total Despesas</span>
                        <span class="text-2xl font-bold text-danger-600">
                            R$ {{ number_format($dreData['resumo']['total_despesas'], 2, ',', '.') }}
                        </span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex flex-col items-center">
                        <span class="text-sm text-gray-500 uppercase font-bold">Resultado Líquido</span>
                        <span class="text-2xl font-bold {{ $dreData['resumo']['resultado_liquido'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                            R$ {{ number_format($dreData['resumo']['resultado_liquido'], 2, ',', '.') }}
                        </span>
                    </div>
                </x-filament::section>
            </div>

            {{-- Tabela DRE --}}
            <x-filament::section title="Demonstrativo Detalhado">
                <div class="overflow-hidden border rounded-lg dark:border-white/10">
                    <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Classificação</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Plano de Conta</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Valor (R$)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-white/5 dark:divide-white/10">
                            {{-- Receitas --}}
                            <tr class="bg-success-50/30 dark:bg-success-500/10 font-bold">
                                <td colspan="2" class="px-6 py-4 text-success-700 dark:text-success-400">Total de Receitas</td>
                                <td class="px-6 py-4 text-right text-success-700 dark:text-success-400">
                                    {{ number_format($dreData['resumo']['total_receitas'], 2, ',', '.') }}
                                </td>
                            </tr>
                            @foreach($dreData['receitas'] as $item)
                                @include('filament.pages.partials.dre-row', ['item' => $item, 'level' => 1])
                            @endforeach

                            {{-- Despesas --}}
                            <tr class="bg-danger-50/30 dark:bg-danger-500/10 font-bold">
                                <td colspan="2" class="px-6 py-4 text-danger-700 dark:text-danger-400 mt-6">Total de Despesas</td>
                                <td class="px-6 py-4 text-right text-danger-700 dark:text-danger-400 mt-6">
                                    {{ number_format($dreData['resumo']['total_despesas'], 2, ',', '.') }}
                                </td>
                            </tr>
                            @foreach($dreData['despesas'] as $item)
                                @include('filament.pages.partials.dre-row', ['item' => $item, 'level' => 1])
                            @endforeach
                            
                            {{-- Resultado Final --}}
                            <tr class="bg-gray-100 dark:bg-white/10 font-bold">
                                <td colspan="2" class="px-6 py-4 text-lg">RESULTADO LÍQUIDO DO EXERCÍCIO</td>
                                <td class="px-6 py-4 text-right text-lg {{ $dreData['resumo']['resultado_liquido'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ number_format($dreData['resumo']['resultado_liquido'], 2, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    @else
        <div class="flex flex-col items-center justify-center p-12 text-center border-2 border-dashed rounded-xl border-gray-200 dark:border-white/10 mt-8">
            <x-filament::icon
                icon="heroicon-o-chart-bar"
                class="w-12 h-12 text-gray-400 mb-4"
            />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Nenhum dado gerado</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Selecione o período e clique em atualizar para visualizar o relatório.</p>
        </div>
    @endif
</x-filament-panels::page>
