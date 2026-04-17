<x-filament-panels::page.simple>
    <div class="max-h-96 overflow-y-auto mb-6 p-4 border rounded-lg bg-gray-50 dark:bg-gray-800">
        @include('filament.pages.auth.terms-content')
    </div>

    <x-filament-schemas::form wire:submit="accept">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
            alignment="end"
        />
    </x-filament-schemas::form>
</x-filament-panels::page.simple>
