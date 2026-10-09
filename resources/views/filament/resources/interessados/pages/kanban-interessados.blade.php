<x-filament-panels::page>
    @php
        $todasVagasSeries = app(\App\Services\TermometroVagasService::class)->calcularVagasPorSerie();
        $podeMover = $this->podeMoverLeads();
    @endphp
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
        @foreach($this->colunas as $coluna)
            @php
                $status = $coluna['status'];
                $statusInteressados = $coluna['leads'];
                $valorTotalColuna = $coluna['valor'];
            @endphp
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
                            <span class="kanban-column-count" @if($coluna['janela_dias']) title="Leads movidos nos últimos {{ $coluna['janela_dias'] }} dias" @endif>
                                {{ $coluna['total'] }}
                            </span>
                        </div>
                        @if($valorTotalColuna > 0)
                            <div class="kanban-column-value">
                                R$ {{ number_format($valorTotalColuna, 2, ',', '.') }}
                            </div>
                        @endif
                        <div class="kanban-column-color" style="background-color: {{ $status->cor_hex ?? ($status->cor === 'info' ? '#3b82f6' : ($status->cor === 'warning' ? '#f59e0b' : ($status->cor === 'success' ? '#10b981' : ($status->cor === 'danger' ? '#ef4444' : '#6366f1')))) }}"></div>
                    </div>

                    <div class="kanban-cards-container custom-scrollbar">
                        @foreach($statusInteressados as $record)
                            <div 
                                @if($podeMover)
                                    draggable="true"
                                    @dragstart="draggingRecordId = {{ $record->id }}"
                                    @dragend="draggingRecordId = null; draggingOverStatusId = null"
                                @endif
                                class="kanban-card-wrapper"
                                :class="draggingRecordId === {{ $record->id }} ? 'opacity-40' : ''"
                            >
                                <div class="kanban-card {{ $record->precisaDeContato() ? 'kanban-card-error' : '' }}" title="{{ $record->ultimoHistorico ? 'Último Contato (' . $record->ultimoHistorico->created_at->format('d/m/Y') . '): ' . $record->ultimoHistorico->relato : 'Sem histórico de contato registrado' }}">
                                    <div class="flex justify-between items-start gap-2 mb-1">
                                        <h4 class="kanban-card-title">{{ $record->pessoa->nome }}</h4>
                                        <a href="{{ $this->getResource()::getUrl('edit', ['record' => $record]) }}" class="kanban-card-edit">
                                            <x-filament::icon icon="heroicon-m-pencil-square" class="w-4 h-4" />
                                        </a>
                                    </div>

                                    {{-- Lead Score (automático) + Temperatura (percepção do consultor) --}}
                                    <div class="kanban-card-temperature">
                                        @if($record->lead_score !== null)
                                            <span class="kanban-score-badge kanban-score-{{ \App\Services\LeadScoreService::cor($record->lead_score) }}" title="Lead Score (automático)">
                                                {{ $record->lead_score }}
                                            </span>
                                        @endif
                                        @if($record->temperatura)
                                            <span class="kanban-temp-badge kanban-temp-{{ $record->temperatura }}" title="Temperatura (percepção do consultor)">
                                                {{ match($record->temperatura) { 'quente' => '🔥', 'morno' => '🟡', 'frio' => '🔵', default => '—' } }}
                                            </span>
                                        @endif
                                        @if($record->valor_estimado)
                                            <span class="kanban-card-valor">
                                                R$ {{ number_format($record->valor_estimado, 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </div>

                                    @php
                                        $dependentesPorSerie = $record->dependentes->groupBy('serie.nome');
                                        $ultimaVisitaRealizada = $record->visitas->where('status', \App\Enums\StatusVisitaInteressado::Realizada)->sortByDesc('data_hora')->first();
                                        $proximaVisitaAgendada = ! $ultimaVisitaRealizada ? $record->visitas->where('status', \App\Enums\StatusVisitaInteressado::Agendada)->sortBy('data_hora')->first() : null;
                                    @endphp

                                    @if($ultimaVisitaRealizada)
                                        @php
                                            $pesquisa = $ultimaVisitaRealizada->pesquisa;
                                            $notaNps = $pesquisa?->isRespondida() ? $pesquisa->nota_nps : null;
                                            $dataVisitaFormatada = $ultimaVisitaRealizada->data_hora ? $ultimaVisitaRealizada->data_hora->format('d/m') : '';
                                        @endphp
                                        <div class="mb-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold border shadow-xs
                                                {{ $notaNps >= 9 ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-800' : '' }}
                                                {{ $notaNps !== null && $notaNps >= 7 && $notaNps < 9 ? 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/70 dark:text-amber-300 dark:border-amber-800' : '' }}
                                                {{ $notaNps !== null && $notaNps < 7 ? 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/70 dark:text-rose-300 dark:border-rose-800' : '' }}
                                                {{ $notaNps === null ? 'bg-indigo-50 text-indigo-800 border-indigo-200 dark:bg-indigo-950/70 dark:text-indigo-300 dark:border-indigo-800' : '' }}
                                            " title="Tour presencial realizado em {{ $ultimaVisitaRealizada->data_hora ? $ultimaVisitaRealizada->data_hora->format('d/m/Y') : '' }}{{ $notaNps !== null ? ' - NPS: ' . $notaNps . '/10 (' . $pesquisa->classificacaoNps() . ')' : ' - Avaliação Pendente' }}">
                                                <span>🏫</span>
                                                <span>Tour Realizado ({{ $dataVisitaFormatada }})</span>
                                                @if($notaNps !== null)
                                                    <span class="font-extrabold ml-0.5 px-1 py-0.2 rounded text-[10px] {{ $notaNps >= 9 ? 'bg-emerald-200 text-emerald-950' : ($notaNps >= 7 ? 'bg-amber-200 text-amber-950' : 'bg-rose-200 text-rose-950') }}">
                                                        NPS {{ $notaNps }}
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    @elseif($proximaVisitaAgendada)
                                        <div class="mb-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950/70 dark:text-blue-300 dark:border-blue-800 shadow-xs" title="Visita agendada para {{ $proximaVisitaAgendada->data_hora ? $proximaVisitaAgendada->data_hora->format('d/m/Y H:i') : '' }}">
                                                <span>📅</span>
                                                <span>Visita Agendada: {{ $proximaVisitaAgendada->data_hora ? $proximaVisitaAgendada->data_hora->format('d/m') : '' }}</span>
                                            </span>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap gap-1 mb-3">
                                        @if($record->indicacao)
                                            <x-filament::badge color="success" size="sm" class="text-[10px] px-1.5 py-0" title="Indicação: {{ $record->indicacao->quemIndicou?->nome }}">
                                                🤝 Indicação
                                            </x-filament::badge>
                                        @endif

                                        @if($record->documentos_inseridos_count > 0)
                                            <x-filament::badge color="primary" size="sm" class="text-[10px] px-1.5 py-0" title="{{ $record->documentos_inseridos_count }} documento(s) anexados">
                                                🗂️ {{ $record->documentos_inseridos_count }} doc(s)
                                            </x-filament::badge>
                                        @endif

                                        @if($record->origem)
                                            <x-filament::badge color="info" size="sm" class="text-[10px] px-1.5 py-0">
                                                {{ $record->origem->nome }}
                                            </x-filament::badge>
                                        @endif

                                        @foreach($dependentesPorSerie as $serieNome => $dependentes)
                                            @php
                                                $depColecao = collect($dependentes);
                                                $serieObj = $depColecao->first()?->serie;
                                                $dadosVagas = $serieObj ? $todasVagasSeries->firstWhere('serie_id', $serieObj->id) : null;
                                                $temAlertaVaga = $dadosVagas && in_array($dadosVagas['nivel_escassez'], ['esgotado', 'critico', 'alerta'], true);
                                                $corBadgeSerie = match(true) {
                                                    $temAlertaVaga && in_array($dadosVagas['nivel_escassez'], ['esgotado', 'critico'], true) => 'danger',
                                                    $temAlertaVaga => 'warning',
                                                    default => 'success',
                                                };
                                            @endphp
                                            <x-filament::badge 
                                                :color="$corBadgeSerie" 
                                                size="sm" 
                                                class="text-[10px] px-1.5 py-0"
                                                :title="$dadosVagas ? 'Vagas na Série: ' . $dadosVagas['vagas_restantes'] . ' livres de ' . $dadosVagas['capacidade_total'] . ' (' . $dadosVagas['taxa_ocupacao'] . '% ocupada)' : null"
                                            >
                                                {{ $depColecao->count() }}x {{ $serieNome ?? 'Série não def.' }}
                                                @if($temAlertaVaga)
                                                    @if($dadosVagas['nivel_escassez'] === 'esgotado')
                                                        (⛔ 0 vagas)
                                                    @elseif($dadosVagas['nivel_escassez'] === 'critico')
                                                        (🔥 {{ $dadosVagas['vagas_restantes'] }} vagas)
                                                    @else
                                                        (🟡 {{ $dadosVagas['vagas_restantes'] }} restam)
                                                    @endif
                                                @endif
                                            </x-filament::badge>
                                        @endforeach
                                    </div>

                                    <div class="kanban-card-footer">
                                        <div class="flex items-center gap-2">
                                            <div class="kanban-avatar" title="{{ $record->usuario?->name }}">
                                                {{ substr($record->usuario?->name ?? '?', 0, 1) }}
                                            </div>
                                            @if($record->diasNoFunil() > 30)
                                                <span class="kanban-dias-alerta" title="{{ $record->diasNoFunil() }} dias no funil">
                                                    {{ $record->diasNoFunil() }}d
                                                </span>
                                            @endif
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

                        @if($coluna['tem_mais'])
                            <button
                                type="button"
                                wire:click="carregarMais({{ $status->id }})"
                                wire:loading.attr="disabled"
                                class="w-full py-2 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline"
                            >
                                Carregar mais ({{ $statusInteressados->count() }} de {{ $coluna['total'] }})
                            </button>
                        @endif

                        @if($coluna['janela_dias'])
                            <p class="py-1 text-center text-[10px] text-gray-400">
                                Mostrando leads movidos nos últimos {{ $coluna['janela_dias'] }} dias. Os mais antigos estão na aba <em>Finalizados</em> da lista.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal Obrigatório de Motivo de Perda (Stage Gate) --}}
    <x-filament::modal
        id="modal-motivo-perda"
        :heading="'Registrar Motivo da Perda'"
        :description="'Para mover este lead para ' . ($statusPerdaNome ?? 'Perdido') . ', informe a razão do encerramento comercial.'"
        icon="heroicon-o-x-circle"
        icon-color="danger"
        width="md"
        :close-by-clicking-away="false"
        :close-by-escaping="true"
    >
        <form wire:submit.prevent="confirmarPerda" class="space-y-4 py-2">
            <div class="p-3 bg-danger-50 dark:bg-danger-950/40 border border-danger-200 dark:border-danger-800 rounded-lg text-sm text-danger-900 dark:text-danger-200 flex items-center gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6 text-danger-600 dark:text-danger-400 shrink-0" />
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $leadPerdaNome }}</p>
                    <p class="text-xs text-danger-700 dark:text-danger-300">O registro da razão da perda é obrigatório para qualificar os relatórios e métricas de conversão da escola.</p>
                </div>
            </div>

            <div>
                <label for="motivoPerda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Motivo da Perda <span class="text-danger-500 font-bold">*</span>
                </label>
                <select
                    id="motivoPerda"
                    wire:model.live="motivoPerda"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm shadow-xs focus:ring-danger-500 focus:border-danger-500"
                    required
                >
                    <option value="">Selecione o motivo da perda...</option>
                    @foreach(\App\Models\Interessado::MOTIVOS_PERDA as $chave => $rotulo)
                        <option value="{{ $chave }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
                @error('motivoPerda')
                    <span class="text-xs text-danger-600 dark:text-danger-400 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            @if(in_array($motivoPerda, ['Concorrência', 'Preço', 'Metodologia', 'Distância'], true))
                @php
                    $concorrentesCadastrados = \App\Models\Concorrente::ativos()->orderBy('nome')->get();
                @endphp
                <div class="space-y-3" x-transition>
                    <div>
                        <label for="concorrenteId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Escola Concorrente Escolhida <span class="text-xs text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <select
                            id="concorrenteId"
                            wire:model.live="concorrenteId"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm shadow-xs focus:ring-danger-500 focus:border-danger-500"
                        >
                            <option value="">Selecione na lista ou digite abaixo se for nova...</option>
                            @foreach($concorrentesCadastrados as $concorrenteItem)
                                <option value="{{ $concorrenteItem->id }}">
                                    {{ $concorrenteItem->nome }} {{ $concorrenteItem->sigla ? '('.$concorrenteItem->sigla.')' : '' }} {{ $concorrenteItem->bairro ? '— ' . $concorrenteItem->bairro : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(empty($concorrenteId))
                        <div>
                            <label for="concorrentePerda" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                Ou digite o nome de outro colégio concorrente
                            </label>
                            <input
                                id="concorrentePerda"
                                type="text"
                                wire:model="concorrentePerda"
                                placeholder="Ex: Colégio Santo Agostinho, Escola Dom Bosco..."
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm shadow-xs focus:ring-danger-500 focus:border-danger-500"
                            />
                        </div>
                    @endif

                    <div>
                        <label for="fatorDecisivoConcorrente" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Principal Fator Decisivo da Família <span class="text-xs text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <select
                            id="fatorDecisivoConcorrente"
                            wire:model="fatorDecisivoConcorrente"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm shadow-xs focus:ring-danger-500 focus:border-danger-500"
                        >
                            <option value="">Selecione o que pesou na decisão...</option>
                            @foreach(\App\Models\Concorrente::FATORES_DECISAO as $chaveFator => $rotuloFator)
                                <option value="{{ $chaveFator }}">{{ $rotuloFator }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            <div>
                <label for="observacoesPerda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Observações / Objeções Relatadas <span class="text-xs text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea
                    id="observacoesPerda"
                    wire:model="observacoesPerda"
                    rows="3"
                    placeholder="Descreva o que a família informou, dificuldades ou se há interesse em rematricular em outro momento..."
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm shadow-xs focus:ring-danger-500 focus:border-danger-500"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="fecharModalPerda"
                >
                    Cancelar
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    color="danger"
                    icon="heroicon-o-check"
                >
                    Confirmar e Marcar como Perdido
                </x-filament::button>
            </div>
        </form>
    </x-filament::modal>

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

        .kanban-column-value {
            font-size: 0.7rem;
            font-weight: 600;
            color: #059669;
            margin-top: 0.125rem;
        }

        .dark .kanban-column-value {
            color: #34d399;
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

        .kanban-card-temperature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .kanban-temp-badge {
            font-size: 0.8rem;
        }

        .kanban-score-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.05rem 0.4rem;
            border-radius: 10px;
        }

        .kanban-score-success {
            background: #dcfce7;
            color: #15803d;
        }

        .dark .kanban-score-success {
            background: #14532d;
            color: #4ade80;
        }

        .kanban-score-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .dark .kanban-score-warning {
            background: #533f17;
            color: #fbbf24;
        }

        .kanban-score-danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dark .kanban-score-danger {
            background: #5d1f1a;
            color: #f87171;
        }

        .kanban-score-gray {
            background: #e5e7eb;
            color: #4b5563;
        }

        .dark .kanban-score-gray {
            background: #374151;
            color: #9ca3af;
        }

        .kanban-card-valor {
            font-size: 0.7rem;
            font-weight: 600;
            color: #059669;
            background: #ecfdf5;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
        }

        .dark .kanban-card-valor {
            color: #34d399;
            background: #064e3b;
        }

        .kanban-dias-alerta {
            font-size: 0.65rem;
            font-weight: 700;
            color: #ef4444;
            background: #fef2f2;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
        }

        .dark .kanban-dias-alerta {
            color: #f87171;
            background: #5d1f1a;
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
            align-items: center;
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
