<x-filament-panels::page>
    <form wire:submit="agendar">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-calendar-days">
                Confirmar Agendamento
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
