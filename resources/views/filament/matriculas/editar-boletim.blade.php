<x-filament-panels::page>
    <form wire:submit="submit">
        <div class="fi-resource-editar-boletim space-y-6">
            {{ $this->schema }}
            
            <div class="flex justify-end mt-6">
                <x-filament::button type="submit" size="lg">
                    Salvar Notas do Boletim
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
