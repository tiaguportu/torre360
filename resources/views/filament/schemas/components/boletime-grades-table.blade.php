@php
    use Filament\Infolists\Components\TextEntry;
    $etapas = $schemaComponent->getEtapasComNotas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-8">
    @if ($etapas->isEmpty())
        <div
            class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center dark:border-gray-700 dark:bg-gray-800/50">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <x-heroicon-o-academic-cap class="h-7 w-7 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma nota registrada até o momento.</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">As tabelas por etapa avaliativa (bimestre) aparecerão
                assim que as primeiras notas forem lançadas.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            <div class="fi-boletim-etapa-section">
                @livewire(\App\Livewire\BoletimEtapaTable::class, ['matriculaId' => $matricula->id, 'etapaId' => $etapa->id], key($etapa->id))
            </div>
        @endforeach

        {{-- Legenda Unificada --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            {{ $schemaComponent->getLegendInfolist() }}


        </div>
    @endif
</div>