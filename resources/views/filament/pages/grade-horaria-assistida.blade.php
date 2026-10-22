<x-filament-panels::page>
    @php
        $turma = $this->getTurma();
        $cobertura = $this->getCobertura();
        $grade = $this->getGrade();
        $professores = $this->getProfessores();
        $rotulos = [
            'completa' => ['Completa', 'success'],
            'incompleta' => ['Faltam aulas', 'warning'],
            'excedente' => ['Aulas a mais', 'danger'],
            'fora_da_matriz' => ['Fora da matriz', 'gray'],
            'sem_carga' => ['Sem carga na matriz', 'gray'],
        ];
        $daMatriz = $cobertura->where('na_matriz', true);
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="grade-periodo">Período letivo</label>
                    <select id="grade-periodo" wire:model.live="periodoId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->getPeriodos() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="grade-turma">Turma</label>
                    <select id="grade-turma" wire:model.live="turmaId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        <option value="">Escolha a turma</option>
                        @foreach ($this->getTurmas() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        @if ($turma && $grade)
            @if ($grade['tempos']->isEmpty())
                <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-200">
                    O turno desta turma{{ $turma->turno ? ' ('.$turma->turno->nome.')' : '' }} não tem tempos de aula cadastrados. Cadastre em Configurações &gt; Turnos (aba "Tempos de aula") para a conferência e a sugestão funcionarem.
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <x-filament::section>
                    <div class="text-sm text-gray-500">Aulas pela matriz</div>
                    <div class="text-3xl font-semibold">{{ $daMatriz->sum('esperadas') }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Já na grade</div>
                    <div class="text-3xl font-semibold">{{ $daMatriz->sum('alocadas') }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Faltam</div>
                    <div class="text-3xl font-semibold {{ $daMatriz->sum('faltam') > 0 ? 'text-warning-600' : 'text-success-600' }}">{{ $daMatriz->sum('faltam') }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Fora da matriz</div>
                    <div class="text-3xl font-semibold">{{ $cobertura->where('na_matriz', false)->count() }}</div>
                </x-filament::section>
            </div>

            <x-filament::section heading="Conferência com a matriz curricular" description="Aulas por semana que a matriz da série pede, contra o que está na grade da turma.">
                @if ($cobertura->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">A série da turma não tem matriz curricular cadastrada e a grade está vazia.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    <th class="py-2 pe-3">Disciplina</th>
                                    <th class="py-2 pe-3">Professor</th>
                                    <th class="py-2 pe-3 text-right">Esperadas</th>
                                    <th class="py-2 pe-3 text-right">Na grade</th>
                                    <th class="py-2">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cobertura as $linha)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <td class="py-2 pe-3 font-medium">{{ $linha['disciplina'] }}</td>
                                        <td class="py-2 pe-3">
                                            @if ($linha['professor'])
                                                {{ $linha['professor'] }}
                                            @elseif ($linha['na_matriz'])
                                                <span class="text-warning-600">sem professor definido</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="py-2 pe-3 text-right">{{ $linha['esperadas'] ?? '—' }}</td>
                                        <td class="py-2 pe-3 text-right">{{ $linha['alocadas'] }}</td>
                                        <td class="py-2">
                                            @php
                                                $situacao = $rotulos[$linha['situacao']][0].($linha['faltam'] > 0 ? " ({$linha['faltam']})" : ($linha['excedem'] > 0 ? " (+{$linha['excedem']})" : ''));
                                            @endphp
                                            <x-filament::badge :color="$rotulos[$linha['situacao']][1]">{{ $situacao }}</x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Grade semanal" description="O que já está na grade da turma, tempo a tempo.">
                @if ($grade['tempos']->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">Sem tempos de aula no turno, a grade não pode ser desenhada tempo a tempo.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th class="border border-gray-200 p-2 text-left dark:border-white/10">Tempo</th>
                                    @foreach ($grade['dias'] as $nomeDia)
                                        <th class="border border-gray-200 p-2 text-left dark:border-white/10">{{ $nomeDia }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grade['tempos'] as $tempo)
                                    <tr>
                                        <td class="whitespace-nowrap border border-gray-200 p-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">{{ $tempo->rotulo() }}</td>
                                        @foreach ($grade['dias'] as $dia => $nomeDia)
                                            <td class="border border-gray-200 p-2 align-top dark:border-white/10">
                                                @foreach ($grade['celulas'][$tempo->ordem][$dia] ?? [] as $horario)
                                                    <div class="mb-1 rounded bg-primary-50 px-2 py-1 text-xs dark:bg-primary-950/30">
                                                        <div class="font-medium">{{ $horario->disciplina?->nome }}</div>
                                                        @if ($horario->professor)
                                                            <div class="text-gray-600 dark:text-gray-300">{{ $horario->professor->nome }}</div>
                                                        @endif
                                                        @if ($horario->sala)
                                                            <div class="text-gray-500">{{ $horario->sala->nome }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($grade['fora_dos_tempos']->isNotEmpty())
                    <div class="mt-4 text-sm">
                        <div class="font-medium">Horários que não batem com os tempos do turno</div>
                        <ul class="list-disc pl-5 text-gray-600 dark:text-gray-300">
                            @foreach ($grade['fora_dos_tempos'] as $horario)
                                <li>{{ $horario->nomeDiaSemana() }}, {{ substr((string) $horario->hora_inicio, 0, 5) }} às {{ substr((string) $horario->hora_fim, 0, 5) }}: {{ $horario->disciplina?->nome }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Disponibilidade dos professores da turma" description="A sugestão só coloca o professor dentro das janelas em que ele pode dar aula.">
                @if ($professores->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">Nenhuma disciplina da turma tem professor definido ainda (vínculo da disciplina com a turma).</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                        @foreach ($professores as $professor)
                            <li class="flex flex-col gap-2 py-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="font-medium">{{ $professor['nome'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $professor['disciplinas'] }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">{{ $professor['resumo'] }}</div>
                                </div>
                                <div>{{ ($this->disponibilidadeAction)(['professor' => $professor['id']]) }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @elseif (! $turma)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">Escolha uma turma para conferir a grade e pedir a sugestão de horários.</p>
            </x-filament::section>
        @endif

        <x-filament::section heading="Visão geral do período" description="Quantas aulas da matriz ainda não estão na grade de cada turma." collapsible collapsed>
            @php($visao = $this->getVisaoGeral())

            @if ($visao->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-300">Não há turmas ativas neste período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="py-2 pe-3">Turma</th>
                                <th class="py-2 pe-3 text-right">Esperadas</th>
                                <th class="py-2 pe-3 text-right">Na grade</th>
                                <th class="py-2 pe-3 text-right">Faltam</th>
                                <th class="py-2">Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($visao as $linha)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2 pe-3 font-medium">
                                        <a href="{{ $this::getUrl(['turma' => $linha['turma']->id]) }}" class="text-primary-600 hover:underline">{{ $linha['turma']->nome }}</a>
                                    </td>
                                    <td class="py-2 pe-3 text-right">{{ $linha['esperadas'] }}</td>
                                    <td class="py-2 pe-3 text-right">{{ $linha['alocadas'] }}</td>
                                    <td class="py-2 pe-3 text-right {{ $linha['faltam'] > 0 ? 'text-warning-600 font-medium' : '' }}">{{ $linha['faltam'] }}</td>
                                    <td class="py-2 text-xs text-gray-600 dark:text-gray-300">
                                        @if ($linha['sem_tempos']) turno sem tempos de aula @endif
                                        @if ($linha['excedem'] > 0) {{ $linha['excedem'] }} aula(s) a mais @endif
                                        @if ($linha['fora_da_matriz'] > 0) {{ $linha['fora_da_matriz'] }} disciplina(s) fora da matriz @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
