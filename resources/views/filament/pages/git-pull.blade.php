<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Card 1: Git Pull e Atualização Completa -->
        <div class="flex flex-col items-center justify-between p-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col items-center text-center">
                <div class="mb-4 p-4 bg-primary-50 dark:bg-primary-900/20 rounded-full text-primary-600 dark:text-primary-400">
                    <x-filament::icon
                        icon="heroicon-o-arrow-path"
                        class="h-10 w-10"
                    />
                </div>
                
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    Atualização do Sistema (Git Pull)
                </h2>
                
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-sm">
                    Busca os commits mais recentes do repositório <strong>main</strong>, limpa caches e executa migrações pendentes automaticamente.
                </p>
            </div>

            <x-filament::button
                wire:click="runGitPull"
                wire:loading.attr="disabled"
                wire:target="runGitPull"
                icon="heroicon-m-arrow-path"
                size="lg"
                class="w-full justify-center transition-all hover:scale-[1.02] active:scale-95 shadow-sm"
            >
                <span wire:loading.remove wire:target="runGitPull">
                    Executar Git Pull Origin Main
                </span>
                <span wire:loading wire:target="runGitPull">
                    Atualizando Repositório...
                </span>
            </x-filament::button>
        </div>

        <!-- Card 2: Ferramentas de Manutenção (Migrações e Caches) -->
        <div class="flex flex-col justify-between p-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <x-filament::icon
                            icon="heroicon-o-server-stack"
                            class="h-7 w-7"
                        />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            Banco de Dados & Otimização
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Ações isoladas para aplicar esquemas e recarregar caches
                        </p>
                    </div>
                </div>

                <div class="space-y-4 mb-6">
                    <div class="p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-100 dark:border-gray-800 flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-circle-stack" class="h-5 w-5 text-gray-400 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Migrações do Banco</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Cria novas tabelas ou colunas pendentes no banco de dados.</p>
                        </div>
                    </div>

                    <div class="p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-100 dark:border-gray-800 flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-5 w-5 text-gray-400 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Limpeza de Caches</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Limpa cache de views, rotas, configurações e Filament.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-filament::button
                    wire:click="runMigrate"
                    wire:loading.attr="disabled"
                    wire:target="runMigrate"
                    color="success"
                    icon="heroicon-m-circle-stack"
                    class="w-full justify-center shadow-sm"
                >
                    <span wire:loading.remove wire:target="runMigrate">
                        Rodar Migrações
                    </span>
                    <span wire:loading wire:target="runMigrate">
                        Migrando...
                    </span>
                </x-filament::button>

                <x-filament::button
                    wire:click="runOptimizeClear"
                    wire:loading.attr="disabled"
                    wire:target="runOptimizeClear"
                    color="gray"
                    icon="heroicon-m-sparkles"
                    class="w-full justify-center shadow-sm"
                >
                    <span wire:loading.remove wire:target="runOptimizeClear">
                        Limpar Caches
                    </span>
                    <span wire:loading wire:target="runOptimizeClear">
                        Limpando...
                    </span>
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
