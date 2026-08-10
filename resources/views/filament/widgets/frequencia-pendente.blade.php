<x-filament-widgets::widget>
    @php
        $pendenciasPorDia = $this->getPendenciasAgrupadas();
    @endphp

    @if($pendenciasPorDia->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-500 animate-pulse" />
                    <span class="text-lg font-bold text-gray-900 dark:text-white">
                        Pendências de Chamada
                    </span>
                    <x-filament::badge color="warning">
                        Últimos {{ $pendenciasPorDia->count() }} {{ $pendenciasPorDia->count() === 1 ? 'dia' : 'dias' }}
                    </x-filament::badge>
                </div>
            </x-slot>

            <x-slot name="description">
                Exibindo os 3 últimos dias com chamadas não lançadas ou incompletas. Cada card ocupa 1/3 da largura. Clique para abrir a chamada em lote.
            </x-slot>

            {{-- GRID DE 3 COLUNAS (1/3 DA LARGURA CADA) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($pendenciasPorDia as $data => $cronogramas)
                    @php
                        $carbonData = \Illuminate\Support\Carbon::parse($data);
                        $isHoje = $carbonData->isToday();
                        $dataDisplay = $carbonData->format('d/m/Y');
                        $diaSemana = ucfirst($carbonData->translatedFormat('l'));
                    @endphp

                    <div class="p-5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm transition hover:shadow-md flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    {{ $diaSemana }}
                                </span>
                                @if($isHoje)
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">
                                        Hoje
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                        Atrasado
                                    </span>
                                @endif
                            </div>

                            <div>
                                <h4 class="text-xl font-extrabold text-gray-900 dark:text-white">
                                    {{ $dataDisplay }}
                                </h4>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $cronogramas->count() }} {{ $cronogramas->count() === 1 ? 'aula pendente' : 'aulas pendentes' }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-2">
                            <x-filament::button
                                wire:click="abrirModalLancamento('{{ $data }}')"
                                icon="heroicon-o-check-circle"
                                color="success"
                                size="sm"
                                class="w-full justify-center"
                            >
                                Lançar Chamada do Dia
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- MODAL INTUITIVO DE LANÇAMENTO EM LOTE --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="fecharModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200 dark:border-gray-800">
                    {{-- Modal Header --}}
                    <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="p-2.5 rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <x-heroicon-o-check-badge class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                    Lançamento de Frequência — {{ $dataSelecionadaFormatada }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Selecione as matérias e marque a presença dos alunos matriculados.
                                </p>
                            </div>
                        </div>
                        <button wire:click="fecharModal" type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="px-6 py-5 space-y-6 max-h-[72vh] overflow-y-auto">

                        {{-- SEÇÃO 1: SELEÇÃO DE AULAS/MATÉRIAS (MULTISELECT TAGS) --}}
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold">1</span>
                                    <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200">
                                        Matérias e Aulas do Dia
                                    </h4>
                                </div>
                                <div class="flex gap-3 text-xs font-medium">
                                    <button wire:click="selecionarTodasAulas" type="button" class="text-emerald-600 dark:text-emerald-400 hover:underline">
                                        Selecionar Todas
                                    </button>
                                    <span class="text-gray-300 dark:text-gray-700">•</span>
                                    <button wire:click="desselecionarTodasAulas" type="button" class="text-gray-500 hover:underline">
                                        Desmarcar Todas
                                    </button>
                                </div>
                            </div>

                            {{-- TAGS CLICÁVEIS ESTILO BADGE --}}
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach($aulasDoDia as $aula)
                                    @php
                                        $isSelecionada = $aulasSelecionadas[$aula['id']] ?? false;
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleAula({{ $aula['id'] }})"
                                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer select-none border {{ $isSelecionada ? 'bg-emerald-50 text-emerald-900 border-emerald-300 shadow-sm dark:bg-emerald-950/80 dark:text-emerald-200 dark:border-emerald-700 ring-2 ring-emerald-500/20' : 'bg-gray-100 text-gray-400 border-gray-200 dark:bg-gray-800 dark:text-gray-500 dark:border-gray-700 opacity-60 hover:opacity-90 line-through' }}"
                                    >
                                        @if($isSelecionada)
                                            <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                        @else
                                            <x-heroicon-o-minus-circle class="w-4 h-4 text-gray-400 shrink-0" />
                                        @endif

                                        <span>{{ $aula['disciplina_nome'] }}</span>
                                        <span class="text-[11px] opacity-80">({{ $aula['turma_nome'] }})</span>

                                        @if($aula['horario'])
                                            <span class="text-[10px] opacity-75 font-normal">({{ $aula['horario'] }})</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- SEÇÃO 2: LISTA DE ALUNOS & CHAMADA --}}
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-1 gap-2">
                                <div class="flex items-center space-x-2">
                                    <span class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-bold">2</span>
                                    <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200">
                                        Alunos da Turma ({{ count($alunosDoDia) }})
                                    </h4>
                                </div>

                                {{-- BOTOES DE ATALHO DE CHAMADA --}}
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <button wire:click="marcarTodosAlunosPresentes" type="button" class="px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 hover:bg-emerald-200 font-semibold transition flex items-center gap-1">
                                        <x-heroicon-o-check class="w-3.5 h-3.5" />
                                        Todos Presentes
                                    </button>
                                    <button wire:click="marcarTodosAlunosAusentes" type="button" class="px-3 py-1.5 rounded-lg bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300 hover:bg-rose-200 font-semibold transition flex items-center gap-1">
                                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                        Todos Ausentes
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 max-h-72 overflow-y-auto pr-1">
                                @foreach($alunosDoDia as $matriculaId => $aluno)
                                    @php
                                        $isAlunoSelecionado = $aluno['selecionado'] ?? true;
                                        $situacao = $aluno['situacao'] ?? 'presente';
                                    @endphp
                                    <div class="p-3 rounded-xl border transition flex items-center justify-between {{ $isAlunoSelecionado ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/30 border-gray-100 dark:border-gray-800 opacity-50' }}">
                                        <label class="flex items-center space-x-3 cursor-pointer select-none shrink">
                                            <input
                                                type="checkbox"
                                                wire:model.live="alunosDoDia.{{ $matriculaId }}.selecionado"
                                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-gray-700"
                                            >
                                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 flex items-center justify-center font-bold text-xs shrink-0">
                                                {{ mb_substr($aluno['nome'], 0, 2) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-xs font-bold text-gray-900 dark:text-white truncate max-w-[170px]">
                                                    {{ $aluno['nome'] }}
                                                </div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                    Turma: {{ $aluno['turma_nome'] }}
                                                </div>
                                            </div>
                                        </label>

                                        {{-- SELETOR DE STATUS PRESENTE / AUSENTE --}}
                                        <div class="flex items-center space-x-1 shrink-0 ml-2">
                                            <button
                                                type="button"
                                                wire:click="$set('alunosDoDia.{{ $matriculaId }}.situacao', 'presente')"
                                                class="px-2.5 py-1 text-xs font-bold rounded-lg border transition {{ $situacao === 'presente' ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:bg-gray-100' }}"
                                            >
                                                P
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="$set('alunosDoDia.{{ $matriculaId }}.situacao', 'ausente')"
                                                class="px-2.5 py-1 text-xs font-bold rounded-lg border transition {{ $situacao === 'ausente' ? 'bg-rose-600 text-white border-rose-600 shadow-xs' : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:bg-gray-100' }}"
                                            >
                                                F
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row justify-between items-center gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                            Total previsto: <span class="font-bold text-gray-900 dark:text-white">{{ $this->getTotalPrevistoLancamentos() }}</span> lançamentos
                        </div>

                        <div class="flex items-center space-x-3 w-full sm:w-auto justify-end">
                            <x-filament::button
                                wire:click="fecharModal"
                                color="gray"
                            >
                                Cancelar
                            </x-filament::button>

                            <x-filament::button
                                wire:click="salvarFrequenciasDoDia"
                                icon="heroicon-o-check"
                                color="success"
                            >
                                Confirmar e Lançar Frequências ({{ $this->getTotalPrevistoLancamentos() }})
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
