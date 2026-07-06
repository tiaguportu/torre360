<x-filament-panels::page>
    <form wire:submit="gerar">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" size="lg" color="success" icon="heroicon-o-identification">
                Gerar Crachás em PDF
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
