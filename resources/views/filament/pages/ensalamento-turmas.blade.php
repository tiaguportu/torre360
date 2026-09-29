<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Barra de Filtros Principais --}}
        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-4 text-gray-700 dark:text-gray-200 font-semibold text-base">
                <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5 text-primary-500" />
                <span>Filtros do Cenário de Ensalamento</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Período Letivo --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Período Letivo</label>
                    <select
                        wire:model.live="periodoLetivoId"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="">Selecione o Período</option>
                        @foreach ($this->periodos as $periodo)
                            <option value="{{ $periodo->id }}">{{ $periodo->nome }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Curso --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Curso</label>
                    <select
                        wire:model.live="cursoId"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="">Todos os Cursos</option>
                        @foreach ($this->cursos as $curso)
                            <option value="{{ $curso->id }}">{{ $curso->nome_externo ?? $curso->nome_interno }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Série --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Série / Ano</label>
                    <select
                        wire:model.live="serieId"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 font-semibold"
                    >
                        <option value="">Selecione a Série</option>
                        @foreach ($this->series as $serie)
                            <option value="{{ $serie->id }}">{{ $serie->nome }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Turno --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Turno (Opcional)</label>
                    <select
                        wire:model.live="turnoId"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="">Todos os Turnos</option>
                        @foreach ($this->turnos as $turno)
                            <option value="{{ $turno->id }}">{{ $turno->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if (! $this->serieId || ! $this->periodoLetivoId)
            <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 text-gray-500">
                <x-filament::icon icon="heroicon-o-academic-cap" class="h-12 w-12 mx-auto text-gray-400 mb-3" />
                <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Selecione o Período Letivo e a Série</h3>
                <p class="text-sm mt-1">Escolha os filtros acima para visualizar a ocupação das turmas e a lista de alunos aguardando ensalamento.</p>
            </div>
        @else
            @php
                $turmas = $this->turmasCenario;
                $alunosPendentes = $this->alunosNaoEnsalados;
                $totalCapacidade = $turmas->sum('vagas_maximas');
                $totalOcupados = $turmas->sum('ocupados');
                $totalPendentes = $alunosPendentes->count();
                $taxaOcupacaoGeral = $totalCapacidade > 0 ? round(($totalOcupados / $totalCapacidade) * 100) : 0;
            @endphp

            {{-- Métricas Rápidas do Cenário --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center gap-3">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                        <x-filament::icon icon="heroicon-o-building-office" class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Turmas na Série</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $turmas->count() }}</div>
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center gap-3">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                        <x-filament::icon icon="heroicon-o-users" class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Capacidade Total</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $totalCapacidade }} vagas</div>
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center gap-3">
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                        <x-filament::icon icon="heroicon-o-check-badge" class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Já Ensalados</div>
                        <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalOcupados }} ({{ $taxaOcupacaoGeral }}%)</div>
                    </div>
                </div>

                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center gap-3">
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                        <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Aguardando Turma</div>
                        <div class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ $totalPendentes }}</div>
                    </div>
                </div>
            </div>

            {{-- Grid de Turmas e Capacidade --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-squares-2x2" class="h-5 w-5 text-primary-500" />
                        <span>Quadro de Turmas e Ocupação de Salas</span>
                    </h3>

                    @can('Manage:Ensalamento')
                        <x-filament::button
                            wire:click="abrirModalDistribuicao"
                            icon="heroicon-o-sparkles"
                            size="sm"
                        >
                            Distribuir Automaticamente
                        </x-filament::button>
                    @endcan
                </div>

                @if ($turmas->isEmpty())
                    <div class="p-6 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500">
                        Nenhuma turma cadastrada para esta série no período selecionado. Cadastre turmas no menu de Turmas para prosseguir com o ensalamento.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach ($turmas as $turma)
                            @php
                                $pct = $turma['percentual_ocupacao'];
                                $isLotada = $turma['vagas_restantes'] <= 0;
                                $barColor = $pct >= 100 ? 'bg-red-500' : ($pct >= 85 ? 'bg-amber-500' : 'bg-emerald-500');
                            @endphp
                            <div
                                x-data="{ expanded: false }"
                                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col justify-between overflow-hidden transition-all duration-200 hover:border-primary-400"
                            >
                                <div class="p-5">
                                    {{-- Cabeçalho da Turma --}}
                                    <div class="flex items-start justify-between gap-2 mb-3">
                                        <div>
                                            <h4 class="text-base font-bold text-gray-900 dark:text-white leading-tight">
                                                {{ $turma['nome'] }}
                                            </h4>
                                            <span class="inline-block text-xs font-mono text-gray-500 dark:text-gray-400 mt-0.5">
                                                Código: {{ $turma['codigo'] }} | Turno: {{ $turma['turno'] }}
                                            </span>
                                        </div>

                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $isLotada ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' }}">
                                            {{ $isLotada ? 'Lotada' : "{$turma['vagas_restantes']} vagas disp." }}
                                        </span>
                                    </div>

                                    {{-- Barra de Ocupação --}}
                                    <div class="mb-4">
                                        <div class="flex justify-between items-center text-xs mb-1">
                                            <span class="text-gray-600 dark:text-gray-400 font-medium">Ocupação: {{ $turma['ocupados'] }} de {{ $turma['vagas_maximas'] }} vagas</span>
                                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ $pct }}%</span>
                                        </div>
                                        <div class="w-full h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500 {{ $barColor }}" style="width: {{ min($pct, 100) }}%"></div>
                                        </div>
                                    </div>

                                    {{-- Distribuição de Gênero --}}
                                    <div class="grid grid-cols-2 gap-2 p-2.5 bg-gray-50 dark:bg-gray-900/40 rounded-lg text-xs">
                                        <div class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                                            <x-filament::icon icon="heroicon-s-user" class="h-4 w-4" />
                                            <span>Meninos: <strong>{{ $turma['meninos'] }}</strong></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-pink-600 dark:text-pink-400">
                                            <x-filament::icon icon="heroicon-s-user" class="h-4 w-4" />
                                            <span>Meninas: <strong>{{ $turma['meninas'] }}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Lista de Alunos (Accordion) --}}
                                <div class="border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                                    <button
                                        type="button"
                                        x-on:click="expanded = ! expanded"
                                        class="w-full px-5 py-2.5 text-xs font-medium text-gray-600 dark:text-gray-400 flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700/50 transition"
                                    >
                                        <span class="flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-o-user-group" class="h-4 w-4" />
                                            <span>Ver Estudantes Ensalados ({{ count($turma['alunos']) }})</span>
                                        </span>
                                        <x-filament::icon
                                            icon="heroicon-s-chevron-down"
                                            class="h-4 w-4 transition-transform duration-200"
                                            x-bind:class="{ 'rotate-180': expanded }"
                                        />
                                    </button>

                                    <div x-show="expanded" x-collapse x-cloak class="p-3 space-y-1.5 max-h-60 overflow-y-auto border-t border-gray-100 dark:border-gray-700">
                                        @forelse ($turma['alunos'] as $aluno)
                                            <div class="flex items-center justify-between p-2 text-xs bg-white dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                                                <div class="truncate mr-2">
                                                    <div class="font-medium text-gray-800 dark:text-gray-200 truncate">{{ $aluno['nome'] }}</div>
                                                    <div class="text-[10px] text-gray-400">
                                                        {{ $aluno['idade'] ? "{$aluno['idade']} anos" : 'Idade N/I' }} • {{ ucfirst($aluno['sexo']) }}
                                                    </div>
                                                </div>

                                                @can('Manage:Ensalamento')
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <button
                                                            type="button"
                                                            wire:click="abrirModalMover({{ $aluno['matricula_id'] }}, '{{ addslashes($aluno['nome']) }}')"
                                                            title="Mover para outra turma"
                                                            class="p-1 text-gray-500 hover:text-primary-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                                        >
                                                            <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-3.5 w-3.5" />
                                                        </button>

                                                        <button
                                                            type="button"
                                                            wire:click="desensalar({{ $aluno['matricula_id'] }})"
                                                            wire:confirm="Deseja desensalar este estudante? Ele voltará para a lista de aguardando turma."
                                                            title="Remover da turma (Desensalar)"
                                                            class="p-1 text-gray-500 hover:text-red-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                                        >
                                                            <x-filament::icon icon="heroicon-o-x-mark" class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                @endcan
                                            </div>
                                        @empty
                                            <div class="text-center py-3 text-xs text-gray-400">
                                                Nenhum aluno alocado nesta turma ainda.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Seção de Alunos Aguardando Ensalamento --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-user-minus" class="h-5 w-5 text-amber-500" />
                            <span>Estudantes Aguardando Turma ({{ $alunosPendentes->count() }})</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Estudantes com matrícula ativa nesta série que ainda não foram designados a nenhuma turma.
                        </p>
                    </div>

                    {{-- Barra de Alocação em Massa --}}
                    @can('Manage:Ensalamento')
                        @if ($alunosPendentes->isNotEmpty() && $turmas->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                    {{ count($selecionados) }} selecionado(s)
                                </span>

                                <select
                                    wire:model="turmaDestinoManualId"
                                    class="text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white py-1.5"
                                >
                                    <option value="">Selecione a Turma de Destino</option>
                                    @foreach ($turmas as $t)
                                        <option value="{{ $t['id'] }}">
                                            {{ $t['nome'] }} (Disp: {{ $t['vagas_restantes'] }})
                                        </option>
                                    @endforeach
                                </select>

                                <x-filament::button
                                    wire:click="alocarSelecionados"
                                    size="sm"
                                    icon="heroicon-o-arrow-right-circle"
                                >
                                    Alocar Selecionados
                                </x-filament::button>
                            </div>
                        @endif
                    @endcan
                </div>

                @if ($alunosPendentes->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 mx-auto text-emerald-500 mb-2" />
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Parabéns! Todos os estudantes desta série estão ensalados.</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Não há alunos pendentes de alocação no momento.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-300 text-xs uppercase font-semibold">
                                <tr>
                                    @can('Manage:Ensalamento')
                                        <th class="p-3 w-10 text-center">
                                            <input
                                                type="checkbox"
                                                x-on:change="$wire.set('selecionados', $el.checked ? {{ json_encode($alunosPendentes->pluck('matricula_id')->toArray()) }} : [])"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            />
                                        </th>
                                    @endcan
                                    <th class="p-3">Estudante</th>
                                    <th class="p-3">Gênero</th>
                                    <th class="p-3">Idade</th>
                                    <th class="p-3">Data Matrícula</th>
                                    @can('Manage:Ensalamento')
                                        <th class="p-3 text-right">Alocação Rápida</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                @foreach ($alunosPendentes as $aluno)
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/30 transition">
                                        @can('Manage:Ensalamento')
                                            <td class="p-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="selecionados"
                                                    value="{{ $aluno['matricula_id'] }}"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                />
                                            </td>
                                        @endcan
                                        <td class="p-3 font-medium text-gray-900 dark:text-white">
                                            {{ $aluno['nome'] }}
                                        </td>
                                        <td class="p-3">
                                            @if ($aluno['sexo'] === 'masculino')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                    Masculino
                                                </span>
                                            @elseif ($aluno['sexo'] === 'feminino')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300">
                                                    Feminino
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                    Não declarado
                                                </span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $aluno['idade'] ? "{$aluno['idade']} anos" : '—' }}
                                        </td>
                                        <td class="p-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $aluno['data_matricula'] }}
                                        </td>
                                        @can('Manage:Ensalamento')
                                            <td class="p-3 text-right">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <select
                                                        x-on:change="if ($el.value) { $wire.alocarAlunosEmTurma([{{ $aluno['matricula_id'] }}], $el.value); $el.value = ''; }"
                                                        class="text-xs rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-1"
                                                    >
                                                        <option value="">Colocar na turma...</option>
                                                        @foreach ($turmas as $t)
                                                            <option value="{{ $t['id'] }}">
                                                                {{ $t['nome'] }} ({{ $t['vagas_restantes'] }} vagas)
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- MODAL 1: DISTRIBUIÇÃO AUTOMÁTICA INTELIGENTE --}}
    @if ($showModalDistribuicao)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-xl w-full p-6 border border-gray-200 dark:border-gray-700 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-cpu-chip" class="h-6 w-6 text-primary-500" />
                            <span>Distribuição Automática Inteligente</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            O algoritmo assistente alocará os estudantes de forma equilibrada entre as turmas selecionadas.
                        </p>
                    </div>
                    <button wire:click="$set('showModalDistribuicao', false)" class="text-gray-400 hover:text-gray-600">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 text-sm">
                    {{-- Seleção de Turmas Participantes --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Turmas Participantes:</label>
                        <div class="space-y-1.5 max-h-36 overflow-y-auto p-2 border border-gray-200 dark:border-gray-700 rounded-lg">
                            @foreach ($this->turmasCenario as $turma)
                                <label class="flex items-center gap-2 p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded cursor-pointer text-xs">
                                    <input
                                        type="checkbox"
                                        wire:model="turmasSelecionadasDistribuicao"
                                        value="{{ $turma['id'] }}"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    />
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $turma['nome'] }}</span>
                                    <span class="text-gray-400">({{ $turma['ocupados'] }}/{{ $turma['vagas_maximas'] }} ocupados - {{ $turma['vagas_restantes'] }} vagas livres)</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Critério de Distribuição --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Critério do Algoritmo:</label>
                        <div class="grid grid-cols-1 gap-2">
                            <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-primary-500 transition {{ $criterioDistribuicao === 'equilibrio_genero' ? 'border-primary-500 bg-primary-50/40 dark:bg-primary-900/20' : '' }}">
                                <input type="radio" wire:model="criterioDistribuicao" value="equilibrio_genero" class="mt-0.5 text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <div class="font-semibold text-xs text-gray-900 dark:text-white">Equilíbrio Harmônico de Gênero (Recomendado)</div>
                                    <div class="text-[11px] text-gray-500">Alterna meninos e meninas round-robin entre as turmas para paridade proporcional.</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-primary-500 transition {{ $criterioDistribuicao === 'ordem_alfabetica' ? 'border-primary-500 bg-primary-50/40 dark:bg-primary-900/20' : '' }}">
                                <input type="radio" wire:model="criterioDistribuicao" value="ordem_alfabetica" class="mt-0.5 text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <div class="font-semibold text-xs text-gray-900 dark:text-white">Ordem Alfabética</div>
                                    <div class="text-[11px] text-gray-500">Ordena os estudantes de A a Z e divide em lotes sequenciais entre as turmas.</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-primary-500 transition {{ $criterioDistribuicao === 'idade' ? 'border-primary-500 bg-primary-50/40 dark:bg-primary-900/20' : '' }}">
                                <input type="radio" wire:model="criterioDistribuicao" value="idade" class="mt-0.5 text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <div class="font-semibold text-xs text-gray-900 dark:text-white">Equilíbrio por Faixa Etária</div>
                                    <div class="text-[11px] text-gray-500">Equaliza a idade média entre as salas para turmas com maturidade homogênea.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Opções Avançadas --}}
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                            <input type="checkbox" wire:model="redistribuirTodos" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Redistribuir todos da série (embaralha também quem já está em turma)</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                            <input type="checkbox" wire:model="respeitarLimiteVagas" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-gray-700 dark:text-gray-300 font-medium">Respeitar estritamente o limite de vagas de cada sala</span>
                        </label>
                    </div>
                </div>

                {{-- Rodapé do Modal --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <x-filament::button color="gray" wire:click="$set('showModalDistribuicao', false)">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button wire:click="executarDistribuicaoAutomatica" icon="heroicon-o-bolt">
                        Executar Distribuição
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 2: TRANSFERIR ESTUDANTE --}}
    @if ($showModalMover)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700 space-y-5">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-5 w-5 text-primary-500" />
                        <span>Transferir Estudante</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Selecione a nova turma para o estudante <strong>{{ $alunoMoverNome }}</strong>.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Turma de Destino:</label>
                    <select
                        wire:model="novaTurmaId"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="">Selecione a Turma...</option>
                        @foreach ($this->turmasCenario as $turma)
                            <option value="{{ $turma['id'] }}">
                                {{ $turma['nome'] }} (Vagas livres: {{ $turma['vagas_restantes'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <x-filament::button color="gray" wire:click="$set('showModalMover', false)">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button wire:click="confirmarMover" icon="heroicon-o-check">
                        Confirmar Transferência
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
