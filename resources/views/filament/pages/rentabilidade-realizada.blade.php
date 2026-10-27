<x-filament-panels::page>
    @php
        $r = $this->resultado();
        $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
        $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').'%';
        $cor = fn ($n) => $n === null ? '' : ($n < 0 ? 'text-danger-600' : 'text-success-600');
        $t = $r['totais'];
        $porGrupo = $this->visao !== 'turma';
        $linhas = $porGrupo ? $r['grupos'] : $r['linhas'];
        $rotulos = ['turma' => 'Turma', 'serie' => 'Série', 'curso' => 'Curso', 'unidade' => 'Unidade'];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="rr-mes">Mês</label>
                    <select id="rr-mes" wire:model.live="mes" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->opcoesDeMes() as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="rr-criterio">Rateio do custo indireto</label>
                    <select id="rr-criterio" wire:model.live="criterio" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->criterios() as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="rr-visao">Agrupar por</label>
                    <select id="rr-visao" wire:model.live="visao" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($rotulos as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="rr-unidade">Unidade</label>
                    <select id="rr-unidade" wire:model.live="unidadeId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        <option value="">Todas</option>
                        @foreach ($this->unidades() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                O custo indireto (R$ {{ $fmt($r['indireto_total']) }} no mês) foi dividido <strong>{{ mb_strtolower($r['criterio_nome']) }}</strong>.
                Outro critério muda a margem de cada turma, não o total da escola. Isto é contabilidade gerencial, não a oficial.
            </p>
        </x-filament::section>

        @if ($r['sem_classificacao']['quantidade'] > 0)
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
                <strong>{{ $r['sem_classificacao']['quantidade'] }} saída(s) bancária(s), somando R$ {{ $fmt($r['sem_classificacao']['valor']) }}, estão sem conta do plano</strong>
                e ficaram fora destes números (podem ser transferências). Classifique-as para o resultado ficar completo.
            </div>
        @endif

        @if ($r['docente_sem_turma'] > 0)
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
                R$ {{ $fmt($r['docente_sem_turma']) }} de custo docente não tem aula no cronograma do mês (ou é de turma fora desta lista) e foram para o custo indireto.
            </div>
        @endif

        @if ($r['fora_do_resultado'] > 0)
            <p class="text-xs text-gray-500">R$ {{ $fmt($r['fora_do_resultado']) }} de despesas estão em centros de custo marcados como “fora do resultado das turmas” e não entram na conta.</p>
        @endif

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <x-filament::section>
                <div class="text-sm text-gray-500">Faturado</div>
                <div class="text-xl font-semibold tabular-nums">R$ {{ $fmt($t['faturado']) }}</div>
                <div class="text-xs text-gray-500">recebido R$ {{ $fmt($t['recebido']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Em atraso</div>
                <div class="text-xl font-semibold tabular-nums {{ $t['em_atraso'] > 0 ? 'text-danger-600' : '' }}">R$ {{ $fmt($t['em_atraso']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Custo total</div>
                <div class="text-xl font-semibold tabular-nums">R$ {{ $fmt($t['custo_total']) }}</div>
                <div class="text-xs text-gray-500">{{ $t['alunos'] }} aluno(s){{ $t['custo_por_aluno'] !== null ? ' · R$ '.$fmt($t['custo_por_aluno']).' por aluno' : '' }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Resultado</div>
                <div class="text-xl font-semibold tabular-nums {{ $cor($t['resultado']) }}">R$ {{ $fmt($t['resultado']) }}</div>
                <div class="text-xs text-gray-500">margem {{ $pct($t['margem']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Mês anterior</div>
                <div class="text-xl font-semibold tabular-nums {{ $cor($r['totais_mes_anterior']['resultado']) }}">R$ {{ $fmt($r['totais_mes_anterior']['resultado']) }}</div>
                <div class="text-xs text-gray-500">mesmo mês de {{ $r['ano'] - 1 }}: R$ {{ $fmt($r['totais_ano_anterior']['resultado']) }}</div>
            </x-filament::section>
        </div>

        <x-filament::section :heading="$porGrupo ? 'Por '.mb_strtolower($rotulos[$this->visao]) : 'Por turma'">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">{{ $rotulos[$this->visao] }}</th>
                            <th class="pr-3 text-right">Alunos</th>
                            <th class="pr-3 text-right">Faturado</th>
                            <th class="pr-3 text-right">Em atraso</th>
                            <th class="pr-3 text-right">Docente</th>
                            <th class="pr-3 text-right">Direto</th>
                            <th class="pr-3 text-right">Indireto</th>
                            <th class="pr-3 text-right">Resultado</th>
                            <th class="pr-3 text-right">Margem</th>
                            @unless ($porGrupo)
                                <th class="pr-3 text-right">Planejado</th>
                                <th class="text-right">Mês anterior</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($linhas as $l)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-3">
                                    {{ $porGrupo ? $l['nome'] : $l['turma'] }}
                                    <span class="block text-xs text-gray-500">{{ $porGrupo ? $l['turmas'].' turma(s)' : $l['serie'].' · '.$l['curso'] }}</span>
                                </td>
                                <td class="pr-3 text-right tabular-nums">{{ $l['alunos'] }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $fmt($l['faturado']) }}</td>
                                <td class="pr-3 text-right tabular-nums {{ $l['em_atraso'] > 0 ? 'text-danger-600' : '' }}">{{ $fmt($l['em_atraso']) }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $fmt($l['custo_docente']) }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $fmt($l['custo_direto']) }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $fmt($l['custo_indireto']) }}</td>
                                <td class="pr-3 text-right font-medium tabular-nums {{ $cor($l['resultado']) }}">{{ $fmt($l['resultado']) }}</td>
                                <td class="pr-3 text-right tabular-nums {{ $cor($l['margem']) }}">{{ $pct($l['margem']) }}</td>
                                @unless ($porGrupo)
                                    <td class="pr-3 text-right tabular-nums text-gray-500">{{ $pct($l['planejado_margem'] ?? null) }}</td>
                                    <td class="text-right tabular-nums text-gray-500">{{ $pct($l['mes_anterior']['margem'] ?? null) }}</td>
                                @endunless
                            </tr>
                        @empty
                            <tr><td class="py-4 text-gray-500" colspan="11">Nenhuma turma em andamento neste mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Planejado é a margem da Controladoria de Turmas (mensalidade base e custos digitados na turma). A diferença para o realizado é o que merece investigação.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
