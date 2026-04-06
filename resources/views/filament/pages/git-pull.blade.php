<x-filament-panels::page>
    <div class="flex flex-col items-center justify-center p-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="mb-6 p-4 bg-primary-50 dark:bg-primary-900/20 rounded-full text-blue-500">
            <x-filament::icon
                icon="heroicon-o-arrow-path"
                class="h-12 w-12"
            />
        </div>
        
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 text-center">
            Atualização do Sistema
        </h2>
        
        <p class="text-gray-500 dark:text-gray-400 mb-8 text-center max-w-md">
            Clique no botão abaixo para buscar as atualizações mais recentes do repositório <strong>main</strong>.
        </p>

        <x-filament::button
            wire:click="runGitPull"
            wire:loading.attr="disabled"
            icon="heroicon-m-arrow-path"
            size="lg"
            class="transition-all hover:scale-105 active:scale-95"
        >
            <span wire:loading.remove>
                Executar Git Pull Origin Main
            </span>
            <span wire:loading>
                Atualizando...
            </span>
        </x-filament::button>
    </div>
</x-filament-panels::page>
