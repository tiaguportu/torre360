<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <x-filament::section class="fi-section-stat hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
            <div class="p-2.5 bg-primary-100 dark:bg-primary-950 rounded-xl text-primary-600 dark:text-primary-400">
                <x-heroicon-m-identification class="h-5 w-5 flex-shrink-0" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">RA / Código</p>
                <p class="text-xl font-black text-gray-950 dark:text-white">{{ $getRecord()->matriculas->first()?->codigo ?? 'N/A' }}</p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="fi-section-stat hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
            <div class="p-2.5 bg-primary-100 dark:bg-primary-950 rounded-xl text-primary-600 dark:text-primary-400">
                <x-heroicon-m-academic-cap class="h-5 w-5 flex-shrink-0" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Turma Atual</p>
                <p class="text-xl font-black text-gray-950 dark:text-white">{{ $getRecord()->matriculas->first()?->turma?->nome ?? 'Sem Turma' }}</p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="fi-section-stat hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
            @php $situacao = $getRecord()->matriculas->first()?->situacaoMatricula?->nome ?? 'Ativa'; @endphp
            <div @class([
                'p-2.5 rounded-xl',
                'bg-success-100 dark:bg-success-950 text-success-600 dark:text-success-400' => $situacao === 'Ativa',
                'bg-warning-100 dark:bg-warning-950 text-warning-600 dark:text-warning-400' => $situacao !== 'Ativa',
            ])>
                <x-heroicon-m-check-circle class="h-5 w-5 flex-shrink-0" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Situação</p>
                <p class="text-xl font-black text-gray-950 dark:text-white">{{ $situacao }}</p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="fi-section-stat hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
            <div class="p-2.5 bg-primary-100 dark:bg-primary-950 rounded-xl text-primary-600 dark:text-primary-400">
                <x-heroicon-m-calendar class="h-5 w-5 flex-shrink-0" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Ano Letivo</p>
                <p class="text-xl font-black text-gray-950 dark:text-white">{{ now()->year }}</p>
            </div>
        </div>
    </x-filament::section>
</div>
