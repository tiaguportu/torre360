<x-filament-panels::page>
    @php
        $linhas = $this->getLinhas();
        $periodo = $this->getPeriodo();
        $comCaso = $linhas->filter(fn ($linha) => $linha['caso']);
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="faltas-periodo">Período letivo</label>
                    <select id="faltas-periodo" wire:model.live="periodoId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->getPeriodos() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="faltas-turma">Turma</label>
                    <select id="faltas-turma" wire:model.live="turmaId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        <option value="">Todas as turmas</option>
                        @foreach ($this->getTurmas() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Limite para notificar: faltas acima de <strong>{{ number_format($this->getLimitePercentual(), 1, ',', '.') }}%</strong> das aulas previstas no ano
                (30% do percentual permitido de faltas, LDB art. 12, VIII). Critério: {{ config('frequencia_legal.notificacao.criterio') === 'injustificadas' ? 'só as faltas injustificadas' : 'todas as faltas' }}.
            </p>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-filament::section>
                <div class="text-sm text-gray-500">Alunos acima do limite</div>
                <div class="text-3xl font-semibold {{ $linhas->isEmpty() ? 'text-success-600' : 'text-danger-600' }}">{{ $linhas->count() }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Já com caso aberto</div>
                <div class="text-3xl font-semibold text-warning-600">{{ $comCaso->count() }}</div>
            </x-filament::section>
        </div>

        <x-filament::section>
            @if ($linhas->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Nenhum aluno ativo passou do limite{{ $periodo ? " em {$periodo->nome}" : '' }}. Se a turma não tem aulas no cronograma, não há total de aulas previstas e a conta não se aplica.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Aluno</th>
                                <th class="pr-3">Turma</th>
                                <th class="pr-3 text-right">Faltas</th>
                                <th class="pr-3 text-right">Justificadas</th>
                                <th class="pr-3 text-right">Injustificadas</th>
                                <th class="pr-3 text-right">% das aulas</th>
                                <th class="pr-3">Caso</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linhas as $linha)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="falta-{{ $linha['matricula']->id }}">
                                    <td class="py-2 pr-3 font-medium">{{ $linha['matricula']->pessoa?->nome }}</td>
                                    <td class="pr-3">{{ $linha['turma']->nome }}</td>
                                    <td class="pr-3 text-right tabular-nums">
                                        {{ $linha['faltas'] }}
                                        <span class="text-xs text-gray-500">/ limite {{ $linha['limite_aulas'] }} de {{ $linha['aulas_previstas'] }}</span>
                                    </td>
                                    <td class="pr-3 text-right tabular-nums">{{ $linha['justificadas'] }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $linha['injustificadas'] }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ number_format($linha['percentual'], 1, ',', '.') }}%</td>
                                    <td class="pr-3">
                                        @if ($linha['caso'])
                                            <a href="{{ \App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource::getUrl('view', ['record' => $linha['caso']]) }}" class="text-primary-600 underline">
                                                {{ $linha['caso']->status->getLabel() }}
                                            </a>
                                        @else
                                            <span class="text-gray-500">Sem caso</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right">
                                        {{ ($this->justificarAction)(['matricula' => $linha['matricula']->id]) }}
                                        @unless ($linha['caso'])
                                            {{ ($this->abrirCasoAction)(['matricula' => $linha['matricula']->id]) }}
                                        @endunless
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
