@php
    $eventos = $preceptoria->historico($limite ?? null);
    $rotulos = \App\Models\Preceptoria::EVENTOS_HISTORICO;
@endphp

<div class="space-y-2">
    @forelse ($eventos as $evento)
        <div class="p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-100 text-primary-800 dark:bg-primary-950 dark:text-primary-200">
                    {{ $rotulos[$evento->event] ?? $evento->event }}
                </span>
                <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $evento->description }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                {{ $evento->causer?->name ? 'por '.$evento->causer->name : 'pelo sistema' }}
            </p>
        </div>
    @empty
        <div class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm italic">
            Nenhuma mudança ou aviso registrado para esta preceptoria até o momento.
        </div>
    @endforelse
</div>
