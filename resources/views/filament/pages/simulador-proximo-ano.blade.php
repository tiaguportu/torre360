<x-filament-panels::page>
    @php
        $r = $this->projecao();
        $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, ',', '.');
        $um = fn ($n) => number_format((float) $n, 1, ',', '.');
        $sugestao = $this->sugestoes();
        $situacoes = [
            'ok' => ['Dentro do esperado', 'text-success-600'],
            'acima_da_capacidade' => ['Acima da capacidade', 'text-danger-600'],
            'sem_turma' => ['Demanda sem turma', 'text-danger-600'],
            'abaixo_do_equilibrio' => ['Abaixo do equilíbrio', 'text-warning-600'],
        ];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-3">
                    <label class="mb-1 block text-sm font-medium" for="sim-campanha">Campanha de rematrícula</label>
                    <select id="sim-campanha" wire:model.live="periodoId" class="w-full max-w-md rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        <option value="">Escolha…</option>
                        @foreach ($this->campanhas() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="sim-andamento">Rematrículas em andamento que terminam (%)</label>
                    <input id="sim-andamento" type="number" min="0" max="100" step="0.1" wire:model.live.debounce.500ms="taxaAndamento" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="sim-naoiniciadas">Quem ainda não começou e renova (%)</label>
                    <input id="sim-naoiniciadas" type="number" min="0" max="100" step="0.1" wire:model.live.debounce.500ms="taxaNaoIniciadas" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="sim-risco">Tirar da conta risco de evasão a partir de (0 desliga)</label>
                    <input id="sim-risco" type="number" min="0" max="100" step="1" wire:model.live.debounce.500ms="corteRisco" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="sim-reajuste">Reajuste extra sobre o preço (%)</label>
                    <input id="sim-reajuste" type="number" min="-50" max="100" step="0.1" wire:model.live.debounce.500ms="reajuste" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
                <label class="flex items-center gap-2 pt-6 text-sm">
                    <input type="checkbox" wire:model.live="incluirEspera" class="rounded border-gray-300">
                    Somar a lista de espera (até as vagas que sobram)
                </label>
                <div class="flex items-end gap-2">
                    <x-filament::button color="gray" size="sm" wire:click="restaurar">Voltar aos padrões</x-filament::button>
                    @if ($sugestao && ($sugestao['taxa_andamento'] !== null || $sugestao['taxa_renovacao'] !== null))
                        <x-filament::button color="gray" size="sm" wire:click="usarSugestao">Usar taxas de {{ $sugestao['periodo'] }}</x-filament::button>
                    @endif
                </div>
            </div>
            @if ($sugestao && $sugestao['periodo'])
                <p class="mt-3 text-xs text-gray-500">
                    Na campanha anterior ({{ $sugestao['periodo'] }}):
                    {{ $sugestao['taxa_andamento'] !== null ? $um($sugestao['taxa_andamento']).'% das rematrículas iniciadas terminaram' : 'sem rematrículas iniciadas para comparar' }};
                    {{ $sugestao['taxa_renovacao'] !== null ? $um($sugestao['taxa_renovacao']).'% dos alunos renovaram.' : 'sem alunos elegíveis para comparar.' }}
                </p>
            @endif
        </x-filament::section>

        @if (! $r)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">Cadastre uma campanha de rematrícula para simular o próximo ano.</p>
            </x-filament::section>
        @else
            <div class="rounded-lg border border-info-300 bg-info-50 p-3 text-xs text-info-800 dark:border-info-700 dark:bg-info-950 dark:text-info-200">
                <strong>Isto é uma projeção com os dados de hoje, não um compromisso.</strong>
                Entram: rematrículas confirmadas (inteiras), em andamento (pela taxa escolhida, sem quem tem risco alto de evasão), lista de espera até as vagas que sobram
                e, só no total, quem ainda não escolheu a série de destino.
            </div>

            @foreach ($r['avisos'] as $aviso)
                <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">{{ $aviso }}</div>
            @endforeach

            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <x-filament::section>
                    <div class="text-sm text-gray-500">Alunos projetados</div>
                    <div class="text-xl font-semibold tabular-nums">{{ $um($r['totais']['projecao']) }}</div>
                    <div class="text-xs text-gray-500">de {{ $r['totais']['elegiveis'] }} ativos hoje</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Confirmadas</div>
                    <div class="text-xl font-semibold tabular-nums">{{ $r['totais']['confirmadas'] }}</div>
                    <div class="text-xs text-gray-500">{{ $r['totais']['em_andamento'] }} em andamento · {{ $r['totais']['nao_iniciadas'] }} sem começar · {{ $r['totais']['recusaram'] }} recusaram</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Turmas e vagas</div>
                    <div class="text-xl font-semibold tabular-nums">{{ $r['totais']['turmas'] }} / {{ $r['totais']['vagas'] }}</div>
                    <div class="text-xs text-gray-500">{{ $r['totais']['series_com_problema'] }} série(s) com alerta</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Receita mensal prevista</div>
                    <div class="text-xl font-semibold tabular-nums">R$ {{ $fmt($r['totais']['receita_mensal']) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Contra o ano em curso</div>
                    <div class="text-xl font-semibold tabular-nums {{ $r['totais']['variacao_receita'] < 0 ? 'text-danger-600' : 'text-success-600' }}">R$ {{ $fmt($r['totais']['variacao_receita']) }}</div>
                    <div class="text-xs text-gray-500">hoje: R$ {{ $fmt($r['totais']['receita_atual_mensal']) }} por mês</div>
                </x-filament::section>
            </div>

            <x-filament::section heading="Por série de destino" description="Mude as turmas ou a capacidade para ver o efeito. Em branco vale o que já está cadastrado para o período de destino.">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Série</th>
                                <th class="pr-3 text-right">Confirm.</th>
                                <th class="pr-3 text-right">Em andam.</th>
                                <th class="pr-3 text-right">Espera</th>
                                <th class="pr-3 text-right">Projeção</th>
                                <th class="pr-3 text-right">Turmas</th>
                                <th class="pr-3 text-right">Vagas por turma</th>
                                <th class="pr-3 text-right">Por turma</th>
                                <th class="pr-3 text-right">Equilíbrio</th>
                                <th class="pr-3 text-right">Receita/mês</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($r['linhas'] as $l)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                    <td class="py-2 pr-3">{{ $l['serie'] }}
                                        <span class="block text-xs text-gray-500">{{ $l['preco_origem'] === 'tabela' ? 'preço da tabela' : ($l['preco_origem'] === 'media' ? 'preço: média dos contratos' : 'sem preço') }}</span>
                                    </td>
                                    <td class="pr-3 text-right tabular-nums">{{ $l['confirmadas'] }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $l['em_andamento'] }}@if ($l['em_risco'] > 0)<span class="block text-xs text-warning-600">{{ $l['em_risco'] }} em risco</span>@endif</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $l['espera'] }}@if ($l['espera_incluida'] > 0)<span class="block text-xs text-gray-500">+{{ $um($l['espera_incluida']) }} na conta</span>@endif</td>
                                    <td class="pr-3 text-right font-medium tabular-nums">{{ $um($l['projecao']) }}</td>
                                    <td class="pr-3 text-right">
                                        <input type="number" min="0" aria-label="Turmas de {{ $l['serie'] }}" placeholder="{{ $l['turmas_atuais'] }}" wire:model.live.debounce.600ms="turmas.{{ $l['serie_id'] }}" class="w-16 rounded-lg border-gray-300 text-right text-sm dark:border-white/20 dark:bg-gray-900">
                                    </td>
                                    <td class="pr-3 text-right">
                                        <input type="number" min="1" aria-label="Vagas por turma de {{ $l['serie'] }}" placeholder="{{ $l['capacidade_por_turma'] }}" wire:model.live.debounce.600ms="capacidade.{{ $l['serie_id'] }}" class="w-16 rounded-lg border-gray-300 text-right text-sm dark:border-white/20 dark:bg-gray-900">
                                    </td>
                                    <td class="pr-3 text-right tabular-nums">{{ $l['alunos_por_turma'] !== null ? $um($l['alunos_por_turma']) : '—' }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $l['ponto_equilibrio'] ?? '—' }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $fmt($l['receita_mensal']) }}</td>
                                    <td class="{{ $situacoes[$l['situacao']][1] }}">
                                        {{ $situacoes[$l['situacao']][0] }}
                                        @if ($l['excesso'] > 0)<span class="block text-xs">{{ $um($l['excesso']) }} a mais que as vagas</span>@endif
                                        @if ($l['espera_maior_que_vagas'])<span class="block text-xs text-warning-600">espera maior que as vagas que sobram</span>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="py-4 text-gray-500" colspan="11">Ainda não há rematrícula com série de destino nem turma no período de destino.</td></tr>
                            @endforelse
                            <tr class="text-gray-600 dark:text-gray-300">
                                <td class="py-2 pr-3">Sem série de destino definida
                                    <span class="block text-xs text-gray-500">{{ $r['sem_serie']['em_andamento'] }} em andamento sem série · {{ $r['sem_serie']['nao_iniciadas'] }} que ainda não começaram ({{ $r['sem_serie']['nao_iniciadas_em_risco'] }} com risco alto)</span>
                                </td>
                                <td colspan="3"></td>
                                <td class="pr-3 text-right font-medium tabular-nums">{{ $um($r['sem_serie']['projecao']) }}</td>
                                <td colspan="6" class="text-xs text-gray-500">Entra só no total: ainda não dá para dizer em qual série essas famílias vão matricular.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if (count($this->seriesParaAbrir()) > 0)
                    <div class="mt-4 flex flex-wrap items-end gap-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="sim-nova">Abrir turma em outra série</label>
                            <select id="sim-nova" wire:model="novaSerie" class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                                <option value="">Escolha…</option>
                                @foreach ($this->seriesParaAbrir() as $id => $nome)
                                    <option value="{{ $id }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-filament::button color="gray" size="sm" wire:click="abrirTurma">Abrir turma</x-filament::button>
                    </div>
                @endif
            </x-filament::section>

            @php($cenarios = $this->cenarios())
            @if (count($cenarios) > 0)
                <x-filament::section heading="Cenários salvos" description="A fotografia do resultado no dia em que foram salvos. “Carregar” devolve os parâmetros à tela para recalcular com os dados de hoje.">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                    <th class="py-2 pr-3">Cenário</th>
                                    <th class="pr-3 text-right">Taxas</th>
                                    <th class="pr-3 text-right">Alunos</th>
                                    <th class="pr-3 text-right">Turmas/vagas</th>
                                    <th class="pr-3 text-right">Receita/mês</th>
                                    <th class="pr-3 text-right">Contra hoje</th>
                                    <th class="pr-3 text-right">Alertas</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cenarios as $c)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <td class="py-2 pr-3">{{ $c['nome'] }}<span class="block text-xs text-gray-500">{{ $c['criado_em']?->format('d/m/Y H:i') }}</span></td>
                                        <td class="pr-3 text-right text-xs tabular-nums">{{ $um($c['taxa_andamento']) }}% / {{ $um($c['taxa_nao_iniciadas']) }}%@if ($c['reajuste_percentual'] != 0) · reajuste {{ $um($c['reajuste_percentual']) }}%@endif</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $um($c['projecao']) }}</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $c['turmas'] }} / {{ $c['vagas'] }}</td>
                                        <td class="pr-3 text-right tabular-nums">R$ {{ $fmt($c['receita_mensal']) }}</td>
                                        <td class="pr-3 text-right tabular-nums {{ $c['variacao_receita'] < 0 ? 'text-danger-600' : 'text-success-600' }}">R$ {{ $fmt($c['variacao_receita']) }}</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $c['series_com_problema'] }}</td>
                                        <td class="whitespace-nowrap text-right">
                                            <x-filament::link tag="button" wire:click="carregarCenario({{ $c['id'] }})">Carregar</x-filament::link>
                                            <x-filament::link tag="button" color="danger" wire:click="excluirCenario({{ $c['id'] }})" wire:confirm="Excluir este cenário?">Excluir</x-filament::link>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
