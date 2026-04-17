<x-filament-panels::page>
    <div class="kanban-container" x-data="{
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
                class="kanban-column"
                ondragover="event.preventDefault()"
                @dragenter="draggingOverStatusId = {{ $status->id }}"
                @dragleave="if (draggingOverStatusId === {{ $status->id }}) draggingOverStatusId = null"
                @drop="handleDrop({{ $status->id }})"
            >
                <div class="kanban-column-content" :class="draggingOverStatusId === {{ $status->id }} ? 'kanban-column-dragging' : ''">
                    <div class="kanban-column-header">
                        <div class="flex items-center gap-2">
                            <span class="kanban-column-title">{{ $status->nome }}</span>
                            <span class="kanban-column-count">
                                {{ $this->getInteressados()->where('status_interessado_id', $status->id)->count() }}
                            </span>
                        </div>
                        <div class="kanban-column-color" style="background-color: {{ $status->cor_hex ?? ($status->cor === 'info' ? '#3b82f6' : ($status->cor === 'warning' ? '#f59e0b' : ($status->cor === 'success' ? '#10b981' : ($status->cor === 'danger' ? '#ef4444' : '#6366f1')))) }}"></div>
                    </div>

                    <div class="kanban-cards-container custom-scrollbar">
                        @foreach($this->getInteressados()->where('status_interessado_id', $status->id) as $record)
                            <div 
                                draggable="true"
                                @dragstart="draggingRecordId = {{ $record->id }}"
                                @dragend="draggingRecordId = null; draggingOverStatusId = null"
                                class="kanban-card-wrapper"
                                :class="draggingRecordId === {{ $record->id }} ? 'opacity-40' : ''"
                            >
                                <div class="kanban-card {{ $record->precisaDeContato() ? 'kanban-card-error' : '' }}" title="{{ $record->ultimoHistorico ? 'Último Contato (' . $record->ultimoHistorico->created_at->format('d/m/Y') . '): ' . $record->ultimoHistorico->relato : 'Sem histórico de contato registrado' }}">
                                    <div class="flex justify-between items-start gap-2 mb-2">
                                        <h4 class="kanban-card-title">{{ $record->pessoa->nome }}</h4>
                                        <a href="{{ $this->getResource()::getUrl('edit', ['record' => $record]) }}" class="kanban-card-edit">
                                            <x-filament::icon icon="heroicon-m-pencil-square" class="w-4 h-4" />
                                        </a>
                                    </div>

                                    @php
                                        $dependentesPorSerie = $record->dependentes->groupBy('serie.nome');
                                    @endphp

                                    <div class="flex flex-wrap gap-1 mb-3">
                                        @if($record->origem)
                                            <x-filament::badge color="info" size="sm" class="text-[10px] px-1.5 py-0">
                                                {{ $record->origem->nome }}
                                            </x-filament::badge>
                                        @endif

                                        @foreach($dependentesPorSerie as $serieNome => $dependentes)
                                            <x-filament::badge color="success" size="sm" class="text-[10px] px-1.5 py-0 border border-success-600/20">
                                                {{ $dependentes->count() }}x {{ $serieNome ?? 'Série não def.' }}
                                            </x-filament::badge>
                                        @endforeach
                                    </div>

                                    <div class="kanban-card-footer">
                                        <div class="flex items-center gap-2">
                                            <div class="kanban-avatar" title="{{ $record->usuario?->name }}">
                                                {{ substr($record->usuario?->name ?? '?', 0, 1) }}
                                            </div>
                                        </div>

                                        @if($record->data_proximo_contato)
                                            <div class="kanban-date {{ \Carbon\Carbon::parse($record->data_proximo_contato)->isPast() ? 'kanban-date-past' : (\Carbon\Carbon::parse($record->data_proximo_contato)->isToday() ? 'kanban-date-today' : '') }}">
                                                <x-filament::icon icon="heroicon-m-calendar-days" class="w-3.5 h-3.5" />
                                                <span>{{ \Carbon\Carbon::parse($record->data_proximo_contato)->format('d/m') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .kanban-container {
            display: flex !important;
            flex-direction: row !important;
            gap: 1.25rem;
            overflow-x: auto;
            padding: 0.5rem 0.5rem 1.5rem 0.5rem;
            align-items: flex-start;
            min-height: calc(100vh - 180px);
            width: 100%;
        }

        .kanban-column {
            flex: 0 0 320px !important;
            width: 320px !important;
            max-width: 320px !important;
            height: auto;
        }

        .kanban-column-content {
            background-color: #ebedf0;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 200px);
            transition: all 0.2s ease;
            border: 2px solid transparent;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .dark .kanban-column-content {
            background-color: #1a1a1b;
        }

        .kanban-column-dragging {
            border-color: var(--primary-500);
            background-color: #e2e4e9;
        }

        .dark .kanban-column-dragging {
            background-color: #262627;
        }

        .kanban-column-header {
            padding: 0.75rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            position: relative;
        }

        .kanban-column-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #172b4d;
        }

        .dark .kanban-column-title {
            color: #9fadbc;
        }

        .kanban-column-count {
            font-size: 0.75rem;
            background: rgba(0,0,0,0.05);
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            color: #5e6c84;
        }

        .dark .kanban-column-count {
            background: rgba(255,255,255,0.05);
            color: #8c9bab;
        }

        .kanban-column-color {
            height: 3px;
            width: 40px;
            border-radius: 3px;
            margin-top: 0.25rem;
        }

        .kanban-cards-container {
            padding: 0.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .kanban-card-wrapper {
            margin-bottom: 0.5rem;
            cursor: move;
        }

        .kanban-card {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.1s ease, box-shadow 0.1s ease;
            border-bottom: 1px solid #ddd;
        }

        .dark .kanban-card {
            background: #22272b;
            border-bottom: 1px solid #333;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        .kanban-card-error {
            border: 2px solid #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .dark .kanban-card-error {
            border-color: #ef4444 !important;
            background-color: #2c1a1a !important;
        }

        .kanban-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: #f4f5f7;
        }

        .dark .kanban-card:hover {
            background: #2c333a;
        }

        .kanban-card-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #172b4d;
            line-height: 1.25;
        }

        .dark .kanban-card-title {
            color: #b6c2cf;
        }

        .kanban-card-edit {
            color: #5e6c84;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .kanban-card:hover .kanban-card-edit {
            opacity: 1;
        }

        .dark .kanban-card-edit {
            color: #9fadbc;
        }

        .kanban-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }

        .kanban-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #dfe1e6;
            display: flex;
            items-center: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: #42526e;
        }

        .dark .kanban-avatar {
            background: #38414a;
            color: #9fadbc;
        }

        .kanban-date {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
            color: #5e6c84;
        }

        .dark .kanban-date {
            color: #9fadbc;
        }

        .kanban-date-past {
            background-color: #ffd2cc;
            color: #ae2e24;
        }

        .dark .kanban-date-past {
            background-color: #5d1f1a;
            color: #f87171;
        }

        .kanban-date-today {
            background-color: #fff0b3;
            color: #89632a;
        }

        .dark .kanban-date-today {
            background-color: #533f17;
            color: #fbbf24;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.15);
            border-radius: 10px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
        }
    </style>
</x-filament-panels::page>
