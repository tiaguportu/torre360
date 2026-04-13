<x-filament-panels::page>
    <form wire:submit="submit">
        <div class="fi-resource-editar-boletim space-y-8">
            {{ $this->schema }}
            
            <div class="flex items-center justify-end gap-x-4 border-t border-gray-200 pt-8 dark:border-gray-700">
                <x-filament::button 
                    tag="a" 
                    href="{{ $this->getResource()::getUrl('boletim', ['record' => $this->record]) }}" 
                    color="gray"
                    size="lg"
                    icon="heroicon-m-x-mark"
                >
                    Cancelar
                </x-filament::button>

                <x-filament::button 
                    type="submit" 
                    size="lg" 
                    icon="heroicon-m-check"
                    class="bg-primary-600 hover:bg-primary-500 shadow-sm"
                >
                    Salvar Alterações no Boletim
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
