<x-filament-panels::page>
    <div class="flex gap-4 overflow-x-auto pb-4 items-start" x-data="{
        draggingRecordId: null,
        draggingOverStatusId: null,
        handleDrop(statusId) {
            if (this.draggingRecordId) {
                $wire.updateRecordStatus(this.draggingRecordId, statusId);
                this.draggingRecordId = null;
                this.draggingOverStatusId = null;
            }
        }
    }">
        @foreach($this->getStatuses() as $status)
            <div 
                class="flex-shrink-0 w-80 h-full transition-all duration-200"
                ondragover="event.preventDefault()"
                @dragenter="draggingOverStatusId = {{ $status->id }}"
                @dragleave="if (draggingOverStatusId === {{ $status->id }}) draggingOverStatusId = null"
                @drop="handleDrop({{ $status->id }})"
            >
                <x-filament::section 
                    class="h-[calc(100vh-250px)] flex flex-col border-none shadow-none bg-gray-100/50 dark:bg-white/5"
                    :class="{ 'ring-2 ring-primary-500 bg-primary-50/50 dark:bg-primary-500/10': draggingOverStatusId === {{ $status->id }} }"
                >
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                             <div class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $status->cor === 'info' ? '#3b82f6' : ($status->cor === 'warning' ? '#f59e0b' : ($status->cor === 'success' ? '#10b981' : ($status->cor === 'danger' ? '#ef4444' : '#6366f1'))) }}"></div>
                             <span class="text-sm font-bold tracking-tight">{{ $status->nome }}</span>
                        </div>
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::badge color="gray" size="sm">
                            {{ $this->getInteressados()->where('status_interessado_id', $status->id)->count() }}
                        </x-filament::badge>
                    </x-slot>

                    <div class="space-y-4 -mx-4 -my-4 p-4 overflow-y-auto max-h-full custom-scrollbar flex-1">
                        @foreach($this->getInteressados()->where('status_interessado_id', $status->id) as $record)
                            <div 
                                draggable="true"
                                @dragstart="draggingRecordId = {{ $record->id }}"
                                @dragend="draggingRecordId = null; draggingOverStatusId = null"
                                class="cursor-move"
                                :class="{ 'opacity-50 grayscale': draggingRecordId === {{ $record->id }} }"
                            >
                                <x-filament::section compact 
                                    class="hover:border-primary-500 transition-all duration-200 shadow-sm border-gray-200 dark:border-white/10"
                                >
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-sm font-semibold leading-tight text-gray-900 dark:text-white">
                                            {{ $record->pessoa->nome }}
                                        </span>
                                        <x-filament::icon-button 
                                            icon="heroicon-m-pencil-square" 
                                            size="sm" 
                                            color="gray"
                                            :href="$this->getResource()::getUrl('edit', ['record' => $record])"
                                            tag="a"
                                        />
                                    </div>

                                    <div class="flex items-center gap-1.5 mb-4">
                                        <x-filament::icon icon="heroicon-m-tag" class="w-3.5 h-3.5 text-gray-400" />
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                            {{ $record->origem?->nome }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between pt-3 border-t dark:border-white/5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-500/20 flex items-center justify-center text-[10px] font-bold text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/30" title="{{ $record->usuario?->name }}">
                                                {{ substr($record->usuario?->name ?? '?', 0, 1) }}
                                            </div>
                                        </div>

                                        @if($record->data_proximo_contato)
                                            <x-filament::badge color="warning" size="sm" icon="heroicon-m-calendar-days">
                                                {{ \Carbon\Carbon::parse($record->data_proximo_contato)->format('d/m') }}
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                </x-filament::section>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
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
