<x-filament-panels::page>
    @php
        $conferencia = $this->getConferencia();
        $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
        $rotulosDeOrigem = ['banco' => 'Movimento bancário', 'fatura' => 'Faturas', 'conta_a_pagar' => 'Contas a pagar'];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="contab-inicio">De</label>
                    <input id="contab-inicio" type="date" wire:model.live="dataInicio" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="contab-fim">Até</label>
                    <input id="contab-fim" type="date" wire:model.live="dataFim" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="contab-regime">Regime</label>
                    <select id="contab-regime" wire:model.live="regime" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach (\App\Enums\RegimeContabil::cases() as $opcao)
                            <option value="{{ $opcao->value }}">{{ $opcao === \App\Enums\RegimeContabil::Caixa ? 'Caixa' : 'Competência' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="contab-formato">Arquivo</label>
                    <select id="contab-formato" wire:model.live="formato" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach (\App\Enums\FormatoExportacaoContabil::cases() as $opcao)
                            <option value="{{ $opcao->value }}">{{ $opcao->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $this->getRegime()->descricao() }}</p>
        </x-filament::section>

        @if ($conferencia['erro'])
            <x-filament::section>
                <p class="text-sm text-danger-600">{{ $conferencia['erro'] }}</p>
            </x-filament::section>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-filament::section>
                    <div class="text-sm text-gray-500">Lançamentos</div>
                    <div class="text-3xl font-semibold">{{ $conferencia['resumo']['quantidade'] }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Total movimentado</div>
                    <div class="text-3xl font-semibold tabular-nums">R$ {{ $fmt($conferencia['resumo']['total']) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Pendências</div>
                    <div class="text-3xl font-semibold {{ count($conferencia['pendencias']) > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ count($conferencia['pendencias']) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">De onde vêm</div>
                    <div class="text-sm">
                        @forelse ($conferencia['resumo']['por_origem'] as $origem => $quantidade)
                            <div>{{ $rotulosDeOrigem[$origem] ?? $origem }}: {{ $quantidade }}</div>
                        @empty
                            <div class="text-gray-500">Nada neste período.</div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>

            @foreach ($conferencia['sobrepostos'] as $lote)
                <x-filament::section>
                    <p class="text-sm text-warning-600">
                        Já existe um arquivo deste regime para {{ $lote->periodo_inicio->format('d/m/Y') }} a {{ $lote->periodo_fim->format('d/m/Y') }}, gerado em {{ $lote->created_at->format('d/m/Y H:i') }}. Gere outro só se o movimento mudou depois.
                    </p>
                </x-filament::section>
            @endforeach

            @if ($conferencia['pendencias'] !== [])
                <x-filament::section heading="O que falta classificar" description="O arquivo só sai sem pendências: o escritório não recebe lançamento com conta em branco.">
                    <ul class="space-y-3 text-sm">
                        @foreach ($conferencia['pendencias'] as $pendencia)
                            <li wire:key="pendencia-{{ $pendencia['chave'] }}">
                                <div class="font-medium">{{ $pendencia['mensagem'] }} <span class="text-gray-500">({{ $pendencia['quantidade'] }})</span></div>
                                <div class="text-gray-500">{{ implode(' · ', $pendencia['exemplos']) }}@if ($pendencia['quantidade'] > count($pendencia['exemplos'])) · …@endif</div>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif

            @if ($conferencia['avisos'] !== [])
                <x-filament::section heading="Para conferir" description="Não impedem o arquivo, mas vale olhar antes de enviar.">
                    <ul class="space-y-3 text-sm">
                        @foreach ($conferencia['avisos'] as $aviso)
                            <li wire:key="aviso-{{ $aviso['chave'] }}">
                                <div class="font-medium">{{ $aviso['mensagem'] }} <span class="text-gray-500">({{ $aviso['quantidade'] }})</span></div>
                                <div class="text-gray-500">{{ implode(' · ', $aviso['exemplos']) }}</div>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif

            @if ($conferencia['lancamentos'] !== [])
                <x-filament::section heading="Prévia" description="Os primeiros lançamentos do arquivo.">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                    <th class="py-2 pr-3">Data</th>
                                    <th class="pr-3">Débito</th>
                                    <th class="pr-3">Crédito</th>
                                    <th class="pr-3 text-right">Valor</th>
                                    <th class="pr-3">Histórico</th>
                                    <th>Documento</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($conferencia['lancamentos'], 0, 12) as $posicao => $lancamento)
                                    <tr class="border-b border-gray-100 dark:border-white/5" wire:key="previa-{{ $posicao }}">
                                        <td class="py-2 pr-3">{{ $lancamento->data->format('d/m/Y') }}</td>
                                        <td class="pr-3 font-mono">{{ $lancamento->contaDebito ?? '—' }}</td>
                                        <td class="pr-3 font-mono">{{ $lancamento->contaCredito ?? '—' }}</td>
                                        <td class="pr-3 text-right tabular-nums">{{ $fmt($lancamento->valor) }}</td>
                                        <td class="pr-3">{{ $lancamento->historico }}</td>
                                        <td class="font-mono">{{ $lancamento->documento }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @endif

        <x-filament::section heading="Arquivos gerados" collapsible>
            @php($lotes = $this->getLotes())
            @if ($lotes->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-300">Nenhum arquivo gerado ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Gerado em</th>
                                <th class="pr-3">Período</th>
                                <th class="pr-3">Regime</th>
                                <th class="pr-3 text-right">Lançamentos</th>
                                <th class="pr-3 text-right">Total</th>
                                <th class="pr-3">Por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lotes as $lote)
                                <tr class="border-b border-gray-100 dark:border-white/5" wire:key="lote-{{ $lote->id }}">
                                    <td class="py-2 pr-3">{{ $lote->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="pr-3">{{ $lote->periodo_inicio->format('d/m/Y') }} a {{ $lote->periodo_fim->format('d/m/Y') }}</td>
                                    <td class="pr-3">{{ $lote->regime === \App\Enums\RegimeContabil::Caixa ? 'Caixa' : 'Competência' }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $lote->lancamentos }}</td>
                                    <td class="pr-3 text-right tabular-nums">R$ {{ $fmt($lote->total) }}</td>
                                    <td class="pr-3">{{ $lote->geradoPor?->name ?? '—' }}</td>
                                    <td>
                                        @if ($this->podeExportar())
                                            <a class="text-primary-600 hover:underline" href="{{ route('financeiro.exportacao-contabil.download', $lote) }}">Baixar</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
