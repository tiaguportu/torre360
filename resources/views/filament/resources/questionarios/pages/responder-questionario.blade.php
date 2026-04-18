<x-filament-panels::page>
    <div class="space-y-6">
        <header class="fi-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-gray-500 dark:text-gray-400">
                    {{ $this->record->descricao }}
                </p>
            </div>
        </header>

        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
