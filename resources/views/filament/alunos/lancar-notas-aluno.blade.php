<x-filament-panels::page>
    {{-- Cabeçalho Superior --}}
    <div class="flex flex-col md:flex-row items-center gap-6 px-4 mb-8">
        <div class="relative">
            <x-filament::avatar
                :src="$record->foto"
                :alt="$record->nome"
                size="lg"
                class="h-24 w-24 border-4 border-white dark:border-gray-800 shadow-xl ring-2 ring-primary-500/20"
            />
            <div class="absolute -bottom-1 -right-1 h-6 w-6 bg-success-500 border-2 border-white dark:border-gray-800 rounded-full shadow-sm"></div>
        </div>
        
        <div class="flex-1 text-center md:text-left space-y-1">
            <h2 class="text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                {{ $record->nome }}
            </h2>
            <div class="flex flex-wrap justify-center md:justify-start items-center gap-x-4 gap-y-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <x-heroicon-m-envelope class="h-4 w-4" />
                    {{ $record->user?->email ?? 'Sem e-mail' }}
                </span>
                <span class="w-1 h-1 bg-gray-300 dark:bg-gray-700 rounded-full hidden sm:inline"></span>
                <span class="flex items-center gap-1.5">
                    <x-heroicon-m-academic-cap class="h-4 w-4" />
                    {{ $record->matriculas->first()?->turma?->nome ?? 'Sem Turma' }}
                </span>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="saveNotas">
        {{ $this->form }}

        @if($record->matriculas()->whereNotNull('turma_id')->exists())
        <div class="fi-ac mt-8 flex flex-wrap items-center justify-end gap-3">
            <x-filament::button
                type="submit"
                color="primary"
                icon="heroicon-m-check-circle"
            >
                Salvar Notas
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\Alunos\AlunoResource::getUrl('index') }}"
                color="gray"
            >
                Cancelar
            </x-filament::button>
        </div>
        @else
        <div class="fi-ac mt-8 flex flex-wrap items-center justify-end gap-3">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\Alunos\AlunoResource::getUrl('index') }}"
                color="gray"
            >
                Voltar
            </x-filament::button>
        </div>
        @endif
    </form>
</x-filament-panels::page>
