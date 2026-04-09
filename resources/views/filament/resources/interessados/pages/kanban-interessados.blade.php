<x-filament-panels::page>
    <div class="flex gap-4 overflow-x-auto pb-4" x-data="{
        draggingRecordId: null,
        handleDrop(statusId) {
            if (this.draggingRecordId) {
                $wire.updateRecordStatus(this.draggingRecordId, statusId);
                this.draggingRecordId = null;
            }
        }
    }">
        @foreach($this->getStatuses() as $status)
            <div 
                class="flex-shrink-0 w-80 bg-gray-100 dark:bg-white/5 rounded-xl flex flex-col h-[calc(100vh-250px)]"
                ondragover="event.preventDefault()"
                @drop="handleDrop({{ $status->id }})"
            >
                <div class="p-4 border-b dark:border-white/10 flex justify-between items-center">
                    <h3 class="font-bold flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $status->cor === 'info' ? '#3b82f6' : ($status->cor === 'warning' ? '#f59e0b' : ($status->cor === 'success' ? '#10b981' : ($status->cor === 'danger' ? '#ef4444' : '#6366f1'))) }}"></span>
                        {{ $status->nome }}
                    </h3>
                    <span class="bg-gray-200 dark:bg-white/10 px-2 py-1 rounded-full text-xs">
                        {{ $this->getInteressados()->where('status_interessado_id', $status->id)->count() }}
                    </span>
                </div>

                <div class="p-2 space-y-3 overflow-y-auto flex-1 custom-scrollbar">
                    @foreach($this->getInteressados()->where('status_interessado_id', $status->id) as $record)
                        <div 
                            draggable="true"
                            @dragstart="draggingRecordId = {{ $record->id }}"
                            class="bg-white dark:bg-white/10 p-4 rounded-lg shadow-sm border dark:border-white/5 cursor-move hover:border-primary-500 transition-colors"
                        >
                            <div class="flex justify-between items-start">
                                <div class="font-medium text-sm">{{ $record->pessoa->nome }}</div>
                                <a href="{{ $this->getResource()::getUrl('edit', ['record' => $record]) }}" class="text-gray-400 hover:text-primary-500">
                                    <x-filament::icon icon="heroicon-m-pencil-square" class="w-4 h-4" />
                                </a>
                            </div>
                            
                            <div class="text-[11px] text-gray-500 mt-1 flex items-center gap-1">
                                <x-filament::icon icon="heroicon-m-tag" class="w-3 h-3" />
                                {{ $record->origem?->nome }}
                            </div>

                            <div class="flex items-center justify-between mt-4">
                                <div class="flex -space-x-1">
                                    <div class="w-6 h-6 rounded-full bg-primary-500 flex items-center justify-center text-[10px] text-white border-2 border-white dark:border-gray-800" title="{{ $record->usuario?->name }}">
                                        {{ substr($record->usuario?->name ?? '?', 0, 1) }}
                                    </div>
                                </div>
                                <div class="flex gap-1">
                                    @if($record->data_proximo_contato)
                                        <span class="text-[10px] bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400 px-1.5 py-0.5 rounded flex items-center gap-1">
                                            <x-filament::icon icon="heroicon-m-calendar-days" class="w-3 h-3" />
                                            {{ \Carbon\Carbon::parse($record->data_proximo_contato)->format('d/m') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
        }
    </style>
</x-filament-panels::page>
