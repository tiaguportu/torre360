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
                        Pendências de Lançamento de Frequência
                    </span>
                    <x-filament::badge color="warning">
                        {{ $pendenciasPorDia->flatten()->count() }} {{ $pendenciasPorDia->flatten()->count() === 1 ? 'aula pendente' : 'aulas pendentes' }}
                    </x-filament::badge>
                </div>
            </x-slot>

            <x-slot name="description">
                Exibindo aulas até a data de hoje com chamadas não lançadas ou incompletas. Selecione o dia para realizar o lançamento em lote.
            </x-slot>

            <div class="space-y-4">
                @foreach($pendenciasPorDia as $data => $cronogramas)
                    @php
                        $carbonData = \Illuminate\Support\Carbon::parse($data);
                        $isHoje = $carbonData->isToday();
                        $dataDisplay = $carbonData->format('d/m/Y') . ' (' . ucfirst($carbonData->translatedFormat('l')) . ')';
                    @endphp

                    <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm transition hover:shadow-md">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">
                                        {{ $dataDisplay }}
                                    </h4>
                                    @if($isHoje)
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            Hoje
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                            Atrasado
                                        </span>
                                    @endif
                                </div>

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $cronogramas->count() }} {{ $cronogramas->count() === 1 ? 'aula' : 'aulas' }} sem chamada neste dia.
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    @foreach($cronogramas as $ca)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                            <span class="font-semibold mr-1">{{ $ca->turma?->nome }}:</span>
                                            {{ $ca->disciplina?->nome }}
                                            @if($ca->hora_inicio)
                                                <span class="ml-1 text-gray-500 dark:text-gray-400">({{ \Illuminate\Support\Carbon::parse($ca->hora_inicio)->format('H:i') }})</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex items-center shrink-0">
                                <x-filament::button
                                    wire:click="abrirModalLancamento('{{ $data }}')"
                                    icon="heroicon-o-check-circle"
                                    color="success"
                                    size="sm"
                                >
                                    Lançar Chamada do Dia
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- MODAL DE LANÇAMENTO DE FREQUÊNCIA EM LOTE DO DIA --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="fecharModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200 dark:border-gray-800">
                    {{-- Modal Header --}}
                    <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-500" />
                                Lançar Frequência do Dia {{ $dataSelecionadaFormatada }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Por padrão todos os alunos recebem presença e todas as matérias do dia são incluídas. Altere as seleções conforme necessário.
                            </p>
                        </div>
                        <button wire:click="fecharModal" type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto">

                        {{-- SEÇÃO 1: SELEÇÃO DE AULAS/MATÉRIAS --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-2">
                                <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <x-heroicon-o-academic-cap class="w-4 h-4 text-primary-500" />
                                    1. Matérias / Aulas do Dia ({{ count($aulasDoDia) }})
                                </h4>
                                <div class="flex gap-2">
                                    <button wire:click="selecionarTodasAulas" type="button" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                                        Selecionar Todas
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <button wire:click="desselecionarTodasAulas" type="button" class="text-xs text-gray-500 hover:underline">
                                        Desselecionar Todas
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($aulasDoDia as $aula)
                                    <label class="flex items-start space-x-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition">
                                        <input
                                            type="checkbox"
                                            wire:model.live="aulasSelecionadas.{{ $aula['id'] }}"
                                            class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-gray-700"
                                        >
                                        <div class="text-xs">
                                            <div class="font-bold text-gray-900 dark:text-white">
                                                {{ $aula['disciplina_nome'] }}
                                            </div>
                                            <div class="text-gray-600 dark:text-gray-300">
                                                Turma: <span class="font-semibold">{{ $aula['turma_nome'] }}</span>
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-0.5">
                                                <span>Prof: {{ $aula['professor_nome'] }}</span>
                                                @if($aula['horario'])
                                                    <span>• {{ $aula['horario'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- SEÇÃO 2: SELEÇÃO DE ALUNOS E SITUAÇÃO --}}
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-2 gap-2">
                                <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <x-heroicon-o-user-group class="w-4 h-4 text-primary-500" />
                                    2. Alunos Matriculados ({{ count($alunosDoDia) }})
                                </h4>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <button wire:click="marcarTodosAlunosPresentes" type="button" class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 hover:bg-emerald-200 font-medium">
                                        Marcar Todos Presentes
                                    </button>
                                    <button wire:click="marcarTodosAlunosAusentes" type="button" class="px-2 py-1 rounded bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300 hover:bg-rose-200 font-medium">
                                        Marcar Todos Ausentes
                                    </button>
                                    <button wire:click="desselecionarTodosAlunos" type="button" class="px-2 py-1 rounded bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 font-medium">
                                        Desselecionar Alunos
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                @foreach($alunosDoDia as $matriculaId => $aluno)
                                    <div class="flex items-center justify-between p-2.5 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:model.live="alunosDoDia.{{ $matriculaId }}.selecionado"
                                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-gray-700"
                                            >
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {{ $aluno['nome'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Turma: {{ $aluno['turma_nome'] }}
                                                </div>
                                            </div>
                                        </label>

                                        <div class="flex items-center space-x-1">
                                            <button
                                                type="button"
                                                wire:click="$set('alunosDoDia.{{ $matriculaId }}.situacao', 'presente')"
                                                class="px-2.5 py-1 text-xs font-semibold rounded-md border transition {{ ($aluno['situacao'] ?? 'presente') === 'presente' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50' }}"
                                            >
                                                Presente
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="$set('alunosDoDia.{{ $matriculaId }}.situacao', 'ausente')"
                                                class="px-2.5 py-1 text-xs font-semibold rounded-md border transition {{ ($aluno['situacao'] ?? '') === 'ausente' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50' }}"
                                            >
                                                Ausente
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row justify-end gap-3">
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
                            Confirmar e Lançar Frequência
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
