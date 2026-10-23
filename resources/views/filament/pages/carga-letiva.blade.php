<x-filament-panels::page>
    @php
        $apuracoes = $this->getTurmasApuradas();
        $turmaAberta = $this->getTurma();
        $contagem = $apuracoes->groupBy(fn ($a) => $a['situacao']->value)->map->count();
        $fmt = fn ($n, $casas = 1) => number_format((float) $n, $casas, ',', '.');
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="max-w-md">
                <label class="mb-1 block text-sm font-medium" for="carga-periodo">Período letivo</label>
                <select id="carga-periodo" wire:model.live="periodoId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                    @foreach ($this->getPeriodos() as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([['em_dia', 'Em dia', 'text-success-600'], ['em_risco', 'Em risco', 'text-warning-600'], ['insuficiente', 'Calendário insuficiente', 'text-danger-600'], ['sem_minimo', 'Sem mínimo definido', 'text-gray-500']] as [$chave, $rotulo, $cor])
                <x-filament::section>
                    <div class="text-sm text-gray-500">{{ $rotulo }}</div>
                    <div class="text-3xl font-semibold {{ $cor }}">{{ $contagem[$chave] ?? 0 }}</div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            @if ($apuracoes->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-300">Não há turmas neste período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Turma</th>
                                <th class="pr-3">Etapa</th>
                                <th class="pr-3 text-right">Dias previstos / realizados</th>
                                <th class="pr-3 text-right">Horas previstas / realizadas / projetadas</th>
                                <th class="pr-3 text-right">Mínimo</th>
                                <th class="pr-3 text-right">Sem registro</th>
                                <th class="pr-3">Situação</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($apuracoes as $a)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="carga-{{ $a['turma']->id }}">
                                    <td class="py-2 pr-3 font-medium">{{ $a['turma']->nome }}</td>
                                    <td class="pr-3">{{ $a['etapa'] ? ucfirst($a['etapa']) : '—' }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $a['dias_previstos'] }} / {{ $a['dias_realizados'] }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $fmt($a['horas_previstas']) }} / {{ $fmt($a['horas_realizadas']) }} / {{ $fmt($a['horas_projetadas']) }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $a['minimo'] ? $a['minimo']['horas'].' h · '.$a['minimo']['dias'].' d' : '—' }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $a['aulas_sem_registro'] }}</td>
                                    <td class="pr-3">
                                        <x-filament::badge :color="$a['situacao']->getColor()">{{ $a['situacao']->getLabel() }}</x-filament::badge>
                                        @if ($a['dias_faltantes'] > 0 || $a['horas_faltantes'] > 0)
                                            <div class="text-xs text-gray-500">faltam {{ $a['dias_faltantes'] }} dia(s) e {{ $fmt($a['horas_faltantes']) }} h</div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ \App\Filament\Pages\CargaLetiva::getUrl(['periodo' => $this->periodoId, 'turma' => $a['turma']->id]) }}" class="text-primary-600 underline">Aulas</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if ($turmaAberta)
            @php
                $semRegistro = $this->getAulasSemRegistro($turmaAberta);
                $naoRealizadas = $this->getAulasNaoRealizadas($turmaAberta);
            @endphp

            <x-filament::section>
                <x-slot name="heading">{{ $turmaAberta->nome }}: aulas passadas sem chamada nem conteúdo</x-slot>
                <x-slot name="description">Ou aconteceram e falta lançar a chamada, ou não aconteceram e pedem reposição.</x-slot>

                @if ($semRegistro->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">Todas as aulas passadas têm registro.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                        @foreach ($semRegistro->take(40) as $aula)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="sem-registro-{{ $aula->id }}">
                                <span>{{ $aula->data->format('d/m/Y') }} · {{ substr((string) $aula->hora_inicio, 0, 5) }}–{{ substr((string) $aula->hora_fim, 0, 5) }} · {{ $aula->disciplina?->nome ?? 'Aula' }}</span>
                                <span>{{ ($this->naoRealizadaAction)(['aula' => $aula->id]) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($semRegistro->count() > 40)
                        <p class="mt-2 text-xs text-gray-500">e mais {{ $semRegistro->count() - 40 }} aula(s).</p>
                    @endif
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ $turmaAberta->nome }}: aulas não realizadas e reposições</x-slot>

                @if ($naoRealizadas->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">Nenhuma aula marcada como não realizada.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                        @foreach ($naoRealizadas as $aula)
                            @php $reposicao = $aula->reposicoes->first(fn ($r) => $r->nao_realizada_em === null); @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="nao-realizada-{{ $aula->id }}">
                                <span>
                                    {{ $aula->data->format('d/m/Y') }} · {{ $aula->disciplina?->nome ?? 'Aula' }}
                                    <span class="text-xs text-gray-500">— {{ $aula->motivo_nao_realizacao }}</span>
                                    @if ($reposicao)
                                        <x-filament::badge color="success" class="ml-1 inline-flex">reposição em {{ $reposicao->data->format('d/m/Y') }}</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning" class="ml-1 inline-flex">sem reposição</x-filament::badge>
                                    @endif
                                </span>
                                <span class="flex gap-2">
                                    @unless ($reposicao)
                                        {{ ($this->reporAction)(['aula' => $aula->id]) }}
                                        {{ ($this->desfazerAction)(['aula' => $aula->id]) }}
                                    @endunless
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
