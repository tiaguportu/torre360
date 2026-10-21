<x-filament-panels::page>
    @php
        $linhas = $this->getLinhas();
        $resumo = $this->getResumo();
        $filtros = ['atencao' => 'Exigem atenção', 'todas' => 'Todas'] + collect(\App\Enums\SituacaoConcessaoFerias::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all();
    @endphp

    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500">Vencidas</div>
                <div class="text-3xl font-semibold text-danger-600">{{ $resumo['vencida'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Prazo acabando (até 90 dias)</div>
                <div class="text-3xl font-semibold text-warning-600">{{ $resumo['a_vencer'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Períodos sem registro</div>
                <div class="text-3xl font-semibold text-warning-600">{{ $resumo['sem_registro'] }}</div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <div class="mb-4 max-w-xs">
                <label class="mb-1 block text-sm font-medium" for="filtro-ferias">Mostrar</label>
                <select id="filtro-ferias" wire:model.live="filtro" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                    @foreach ($filtros as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">Funcionário</th>
                            <th class="pr-3">Período aquisitivo</th>
                            <th class="pr-3">Conceder até</th>
                            <th class="pr-3 text-right">Saldo (dias)</th>
                            <th class="pr-3">Situação</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($linhas as $linha)
                            @php
                                $url = $this->urlDoFuncionario($linha['funcionario']);
                                $dias = $linha['dias_para_limite'];
                            @endphp
                            <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="ferias-{{ $linha['funcionario']->id }}-{{ $linha['inicio']->format('Ymd') }}">
                                <td class="py-2 pr-3 font-medium">
                                    @if ($url)
                                        <a href="{{ $url }}" class="text-primary-600 underline">{{ $linha['funcionario']->pessoa?->nome }}</a>
                                    @else
                                        {{ $linha['funcionario']->pessoa?->nome }}
                                    @endif
                                    <div class="text-xs font-normal text-gray-500">{{ $linha['funcionario']->cargo }}</div>
                                </td>
                                <td class="pr-3 whitespace-nowrap">{{ $linha['inicio']->format('d/m/Y') }} – {{ $linha['fim']->format('d/m/Y') }}</td>
                                <td class="pr-3 whitespace-nowrap">{{ $linha['limite']->format('d/m/Y') }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $linha['saldo'] }}</td>
                                <td class="pr-3">
                                    <x-filament::badge :color="$linha['situacao']->getColor()">{{ $linha['situacao']->getLabel() }}</x-filament::badge>
                                </td>
                                <td class="text-gray-600 dark:text-gray-300">
                                    @switch($linha['situacao']->value)
                                        @case('vencida')
                                            Venceu há {{ abs($dias) }} dia(s). {{ $linha['dobro_dias'] }} dia(s) a pagar em dobro se forem gozados agora.
                                            @break
                                        @case('a_vencer')
                                            Faltam {{ $dias }} dia(s) para o fim do prazo.
                                            @break
                                        @case('a_conceder')
                                            Prazo em {{ $dias }} dia(s).
                                            @break
                                        @case('sem_registro')
                                            Cadastre o período na aba Férias do funcionário.
                                            @break
                                        @case('quitada_fora_do_prazo')
                                            {{ $linha['dias_fora_do_prazo'] }} dia(s) gozados após o prazo: confira o pagamento em dobro.
                                            @break
                                    @endswitch
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500">Nenhum período neste filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
