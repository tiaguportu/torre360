<x-filament-panels::page>
    @php
        $r = $this->resultado();
        $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
        $cores = ['ok' => 'success', 'atencao' => 'warning', 'estourado' => 'danger'];
        $rotulos = ['ok' => 'Dentro do previsto', 'atencao' => 'Atenção', 'estourado' => 'Fora do previsto'];
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="oc-orcamento">Orçamento</label>
                    <select id="oc-orcamento" wire:model.live="orcamentoId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        <option value="">Escolha…</option>
                        @foreach ($this->opcoesDeOrcamento() as $id => $rotulo)
                            <option value="{{ $id }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="oc-mes">Acompanhar até</label>
                    <select id="oc-mes" wire:model.live="ateMes" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($meses as $posicao => $nome)
                            <option value="{{ $posicao + 1 }}">{{ ucfirst($nome) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        @if (! $r['orcamento'])
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">Não há orçamento aprovado para acompanhar. Crie e aprove um em <strong>Orçamento Anual</strong>.</p>
            </x-filament::section>
        @else
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach (['receita' => 'Receitas', 'despesa' => 'Despesas'] as $tipo => $rotulo)
                    <x-filament::section>
                        <div class="text-sm text-gray-500">{{ $rotulo }} previstas</div>
                        <div class="text-2xl font-semibold tabular-nums">R$ {{ $fmt($r['totais'][$tipo]['previsto']) }}</div>
                    </x-filament::section>
                    <x-filament::section>
                        <div class="text-sm text-gray-500">{{ $rotulo }} realizadas</div>
                        <div class="text-2xl font-semibold tabular-nums">R$ {{ $fmt($r['totais'][$tipo]['realizado']) }}</div>
                    </x-filament::section>
                @endforeach
            </div>

            @if ($r['sem_classificacao']['quantidade'] > 0)
                <x-filament::section>
                    <p class="text-sm text-warning-600">
                        {{ $r['sem_classificacao']['quantidade'] }} movimento(s) do banco, somando R$ {{ $fmt($r['sem_classificacao']['valor']) }}, estão sem conta do plano neste período e ficam fora do realizado.
                    </p>
                </x-filament::section>
            @endif

            <x-filament::section :heading="'Acumulado até '.$meses[$r['mes'] - 1].' de '.$r['orcamento']->ano" :description="$r['orcamento']->rotulo()">
                @if ($r['linhas'] === [])
                    <p class="text-sm text-gray-600 dark:text-gray-300">O orçamento não tem linhas.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                    <th class="py-2 pr-3">Conta</th>
                                    <th class="pr-3 text-right">Previsto</th>
                                    <th class="pr-3 text-right">Realizado</th>
                                    <th class="pr-3 text-right">Variação</th>
                                    <th class="pr-3 text-right">%</th>
                                    <th>Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($r['linhas'] as $posicao => $linha)
                                    <tr class="border-b border-gray-100 dark:border-white/5" wire:key="linha-{{ $posicao }}">
                                        <td class="py-2 pr-3">
                                            <div class="font-medium">{{ $linha['plano']->codigo }} - {{ $linha['plano']->nome }}</div>
                                            <div class="text-xs text-gray-500">{{ $linha['tipo'] === 'receita' ? 'Receita' : 'Despesa' }}@if ($linha['centro']) · {{ $linha['centro']->nome }}@endif</div>
                                        </td>
                                        <td class="pr-3 text-right tabular-nums">{{ $fmt($linha['previsto']) }}</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $fmt($linha['realizado']) }}</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $fmt($linha['variacao']) }}</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $linha['percentual'] === null ? '—' : number_format($linha['percentual'], 1, ',', '.').'%' }}</td>
                                        <td><x-filament::badge :color="$cores[$linha['situacao']]">{{ $rotulos[$linha['situacao']] }}</x-filament::badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
