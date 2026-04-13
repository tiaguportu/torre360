@php
    $disciplinaId = $getRecord()->id;
    $avaliacao = $avaliacoes->where('disciplina_id', $disciplinaId)->where('categoria_avaliacao_id', $categoria->id)->first();
@endphp

<div class="px-2 py-1 flex justify-center">
    @if ($avaliacao)
        <input 
            type="text" 
            wire:model="notas.{{ $avaliacao->id }}"
            class="fi-input block w-16 rounded-lg border-gray-300 bg-white py-1 px-1 text-center text-sm font-bold shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            placeholder="-"
        >
    @else
        <span class="text-gray-200 dark:text-gray-700 font-bold text-lg">·</span>
    @endif
</div>
