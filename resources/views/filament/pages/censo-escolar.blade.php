<x-filament-panels::page>
    @php
        $escola = $this->getEscola();
        $resumo = $this->getResumo();
        $linhas = $this->getLinhas();
        $filtros = ['todas' => 'Todas', 'pendencias' => 'Com pendência', 'prontas' => 'Prontas para enviar'];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="censo-unidade">Escola (unidade)</label>
                    <select id="censo-unidade" wire:model.live="unidadeId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->getUnidades() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="censo-periodo">Período letivo</label>
                    <select id="censo-periodo" wire:model.live="periodoId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->getPeriodos() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="censo-filtro">Mostrar</label>
                    <select id="censo-filtro" wire:model.live="filtro" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($filtros as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($escola)
                <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-medium">Código Inep da escola:</span> {{ $escola['inep'] ?: 'não cadastrado' }}
                    · <span class="font-medium">Gestor (registro 89):</span> {{ $escola['gestor_nome'] ?: 'não cadastrado' }}
                    · <span class="font-medium">Data de referência do Censo:</span> {{ $this->getDataReferencia() ?? '—' }}
                </div>

                @if ($escola['pendencias'] !== [])
                    <div class="mt-3 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-200">
                        <div class="font-medium">Antes de gerar o arquivo:</div>
                        <ul class="list-disc pl-5">
                            @foreach ($escola['pendencias'] as $pendencia)
                                <li>{{ $pendencia }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </x-filament::section>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            <x-filament::section>
                <div class="text-sm text-gray-500">Matrículas no Censo</div>
                <div class="text-3xl font-semibold">{{ $resumo['total'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Prontas para enviar</div>
                <div class="text-3xl font-semibold text-success-600">{{ $resumo['prontas'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Com pendência</div>
                <div class="text-3xl font-semibold text-danger-600">{{ $resumo['com_pendencia'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Sem ID Inep</div>
                <div class="text-3xl font-semibold text-warning-600">{{ $resumo['sem_id_inep'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Registro 90 · 91</div>
                <div class="text-3xl font-semibold">{{ $resumo['registro_90'] }} · {{ $resumo['registro_91'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Ajustes da secretaria</div>
                <div class="text-3xl font-semibold">{{ $resumo['ajustes'] }}</div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ $linhas->count() }} matrícula(s)</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">Aluno</th>
                            <th class="pr-3">Turma</th>
                            <th class="pr-3">Registro</th>
                            <th class="pr-3">Situação</th>
                            <th class="pr-3">Pendências e avisos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($linhas as $linha)
                            @php
                                $matricula = $linha['matricula'];
                                $situacao = $linha['situacao'];
                            @endphp
                            <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="censo-{{ $matricula->id }}">
                                <td class="py-2 pr-3 font-medium">
                                    {{ $matricula->pessoa?->nome }}
                                    <div class="text-xs font-normal text-gray-500">
                                        ID Inep: {{ $matricula->pessoa?->codigo_inep ?: '—' }} · Matrícula Inep: {{ $matricula->codigo_matricula_inep ?: '—' }}
                                    </div>
                                </td>
                                <td class="pr-3">{{ $matricula->turma?->nome }}</td>
                                <td class="pr-3">{{ $linha['registro'] ?? '—' }}</td>
                                <td class="pr-3">
                                    @if ($situacao)
                                        <x-filament::badge :color="$situacao->getColor()">{{ $situacao->getLabel() }}</x-filament::badge>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                    <div class="mt-1 text-xs text-gray-500">{{ $linha['motivo'] }}</div>
                                </td>
                                <td class="pr-3 text-xs">
                                    @foreach ($linha['pendencias'] as $pendencia)
                                        <div class="text-danger-600 dark:text-danger-400">• {{ $pendencia }}</div>
                                    @endforeach
                                    @foreach ($linha['avisos'] as $aviso)
                                        <div class="text-warning-700 dark:text-warning-400">• {{ $aviso }}</div>
                                    @endforeach
                                    @if ($linha['pendencias'] === [] && $linha['avisos'] === [])
                                        <span class="text-success-600">Pronta</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        {{ ($this->ajustarAction)(['matricula' => $matricula->id]) }}
                                        {{ ($this->codigosAction)(['matricula' => $matricula->id]) }}
                                        @if ($linha['origem'] === 'ajuste')
                                            {{ ($this->removerAjusteAction)(['matricula' => $matricula->id]) }}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">Nenhuma matrícula neste filtro. Confira a escola e o período letivo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
