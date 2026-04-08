@php
    $state = $getState();
    $display = $state['display'] ?? '·';
    $canEdit = $state['can_edit'] ?? false;
    $avaliacaoId = $state['avaliacao_id'] ?? null;
    $media = $state['media'];
    $color = $state['color'] ?? 'gray';
    $icon = $state['icon'] ?? null;
    $isIgnorada = $state['is_ignorada'] ?? false;

    // Formatar o valor para o input
    $inputValor = $media !== null ? number_format($media, 1, '.', '') : '';

    $colorClasses = [
        'success' => 'text-success-600 dark:text-success-400',
        'danger' => 'text-danger-600 dark:text-danger-400',
        'warning' => 'text-warning-600 dark:text-warning-400',
        'gray' => 'text-gray-600 dark:text-gray-400',
    ];

    $textColor = $colorClasses[$color] ?? $colorClasses['gray'];
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
    class="flex justify-center items-center w-full h-full min-h-[2rem] {{ $textColor }} {{ $isIgnorada ? 'opacity-50' : '' }}"
>
    @if($canEdit)
        <div
            x-show="!isEditing"
            @click="isEditing = true; $nextTick(() => $refs.input.focus())"
            class="flex items-center gap-1 cursor-pointer hover:ring-2 hover:ring-primary-500 rounded px-2 py-1 transition-all min-w-[2rem] text-center"
        >
            <span class="{{ $isIgnorada ? 'line-through' : '' }}" style="{{ $isIgnorada ? 'text-decoration: line-through !important' : '' }}">
                {{ $display }}
            </span>
            @if($icon)
                <x-filament::icon
                    :icon="$icon"
                    class="h-4 w-4"
                />
            @endif
        </div>
        <div x-show="isEditing" x-cloak class="flex items-center">
            <input
                x-ref="input"
                type="text"
                x-model="valor"
                @keydown.enter="save()"
                @blur="save()"
                @keydown.escape="isEditing = false; valor = '{{ $inputValor }}'"
                class="w-12 text-center rounded border-primary-500 bg-white dark:bg-gray-900 text-sm p-1 focus:ring-1 focus:ring-primary-500 focus:outline-none text-gray-900 dark:text-white"
            />
        </div>
    @else
        <div class="flex items-center gap-1 px-2 py-1">
            <span class="{{ $isIgnorada ? 'line-through' : '' }}" style="{{ $isIgnorada ? 'text-decoration: line-through !important' : '' }}">
                {{ $display }}
            </span>
            @if($icon)
                <x-filament::icon
                    :icon="$icon"
                    class="h-4 w-4"
                />
            @endif
        </div>
    @endif
</div>
