@php
    $etapas = $schemaComponent->getEtapas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-8">
    @if ($etapas->isEmpty())
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma etapa encontrada com avaliações para esta turma.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            <div class="fi-boletim-etapa-edicao-section">
                @livewire(\App\Livewire\BoletimEdicaoEtapaTable::class, ['matriculaId' => $matricula->id, 'etapaId' => $etapa->id], key($etapa->id))
            </div>
        @endforeach
    @endif
</div>
