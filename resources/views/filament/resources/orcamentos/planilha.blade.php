<x-filament-panels::page>
    @php
        $rotulos = $this->rotulos();
        $editavel = $this->editavel();
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
    @endphp

    <div class="space-y-4">
        @if ($editavel)
            <x-filament::section>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium" for="novo-plano">Conta do plano</label>
                        <select id="novo-plano" wire:model="novoPlano" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                            <option value="">Escolha…</option>
                            @foreach ($this->opcoesDePlano() as $id => $rotulo)
                                <option value="{{ $id }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium" for="novo-centro">Centro de custo (opcional)</label>
                        <select id="novo-centro" wire:model="novoCentro" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                            <option value="">Todos</option>
                            @foreach ($this->opcoesDeCentro() as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <x-filament::button wire:click="adicionarLinha" color="gray" class="w-full">Adicionar linha</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            @if ($grade === [])
                <p class="text-sm text-gray-600 dark:text-gray-300">A planilha está vazia. {{ $editavel ? 'Adicione uma conta acima ou importe um CSV na tela do orçamento.' : '' }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Conta</th>
                                @foreach ($meses as $mes)
                                    <th class="px-1 text-right">{{ $mes }}</th>
                                @endforeach
                                <th class="px-2 text-right">Ano</th>
                                @if ($editavel)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grade as $chave => $valores)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="linha-{{ $chave }}">
                                    <td class="py-2 pr-3">
                                        <div class="font-medium">{{ $rotulos[$chave]['plano'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $rotulos[$chave]['tipo'] === 'receita' ? 'Receita' : 'Despesa' }}@if ($rotulos[$chave]['centro']) · {{ $rotulos[$chave]['centro'] }}@endif</div>
                                    </td>
                                    @for ($m = 1; $m <= 12; $m++)
                                        <td class="px-1">
                                            @if ($editavel)
                                                <input type="text" inputmode="decimal" wire:model.blur="grade.{{ $chave }}.{{ $m }}" class="w-20 rounded border-gray-300 px-1 py-1 text-right text-xs dark:border-white/20 dark:bg-gray-900" aria-label="{{ $rotulos[$chave]['plano'] }} {{ $meses[$m - 1] }}">
                                            @else
                                                <span class="block w-20 text-right text-xs tabular-nums">{{ ($valores[$m] ?? '') !== '' ? $valores[$m] : '—' }}</span>
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="px-2 text-right font-medium tabular-nums">{{ $fmt($this->totalDaLinha($chave)) }}</td>
                                    @if ($editavel)
                                        <td><button type="button" wire:click="removerLinha('{{ $chave }}')" class="text-xs text-danger-600 hover:underline">Remover</button></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @foreach (['receita' => 'Receitas', 'despesa' => 'Despesas'] as $tipo => $rotulo)
                                <tr class="text-xs text-gray-600 dark:text-gray-300">
                                    <td class="py-2 pr-3 font-medium">{{ $rotulo }}</td>
                                    @for ($m = 1; $m <= 12; $m++)
                                        <td class="px-1 text-right tabular-nums">{{ $fmt($this->totalDoMes($m, $tipo)) }}</td>
                                    @endfor
                                    <td class="px-2 text-right font-medium tabular-nums">{{ $fmt(collect(range(1, 12))->sum(fn ($m) => $this->totalDoMes($m, $tipo))) }}</td>
                                    @if ($editavel)<td></td>@endif
                                </tr>
                            @endforeach
                        </tfoot>
                    </table>
                </div>

                @if ($editavel)
                    <div class="mt-4">
                        <x-filament::button wire:click="salvar">Salvar planilha</x-filament::button>
                    </div>
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
