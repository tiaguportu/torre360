@php
    $state = $getState();
    $display = $state['display'] ?? '·';
    $canEdit = $state['can_edit'] ?? false;
    $avaliacaoId = $state['avaliacao_id'] ?? null;
    $media = $state['media'];
    // Formatar o valor para o input (usar ponto como separador decimal para facilitar o processamento)
    $inputValor = $media !== null ? number_format($media, 1, '.', '') : '';
@endphp

<div
    x-data="{
        isEditing: false,
        valor: '{{ $inputValor }}',
        save() {
            if (!this.isEditing) return;
            this.isEditing = false;
            $wire.updateNota({{ $avaliacaoId }}, this.valor);
        }
    }"
    class="flex justify-center items-center w-full h-full min-h-[2rem]"
>
    @if($canEdit)
        <div
            x-show="!isEditing"
            @click="isEditing = true; $nextTick(() => $refs.input.focus())"
            class="cursor-pointer hover:ring-2 hover:ring-primary-500 rounded px-2 py-1 transition-all min-w-[2rem] text-center"
        >
            {{ $display }}
        </div>
        <div x-show="isEditing" x-cloak class="flex items-center">
            <input
                x-ref="input"
                type="text"
                x-model="valor"
                @keydown.enter="save()"
                @blur="save()"
                @keydown.escape="isEditing = false; valor = '{{ $inputValor }}'"
                class="w-12 text-center rounded border-primary-500 bg-white dark:bg-gray-900 text-sm p-1 focus:ring-1 focus:ring-primary-500 focus:outline-none"
            />
        </div>
    @else
        <div class="px-2 py-1">{{ $display }}</div>
    @endif
</div>
