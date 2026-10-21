<x-filament-panels::page>
    @php
        $linhas = $this->getLinhas();
        $ano = $this->anoValido();
        $reais = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="informe-ano">Ano-calendário</label>
                    <select id="informe-ano" wire:model.live="ano" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        @foreach ($this->getAnos() as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="informe-turma">Turma</label>
                    <select id="informe-turma" wire:model.live="turmaId" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                        <option value="">Todas</option>
                        @foreach ($this->getTurmas() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="informe-busca">Aluno</label>
                    <input id="informe-busca" type="search" wire:model.live.debounce.500ms="busca" placeholder="Buscar pelo nome" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $linhas->count() }} aluno(s) com pagamento em {{ $ano }}</x-slot>
            <x-slot name="description">Total recebido: {{ $reais((float) $linhas->sum('total_pago')) }} · parte estimada de instrução: {{ $reais((float) $linhas->sum('total_dedutivel')) }}</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">Aluno</th><th class="pr-3">Turma</th><th class="pr-3">Responsáveis</th><th class="pr-3 text-right">Total pago</th><th class="pr-3 text-right">Instrução (estim.)</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($linhas as $linha)
                            <tr class="border-b border-gray-100 dark:border-white/5" wire:key="informe-{{ $linha['matricula_id'] }}">
                                <td class="py-2 pr-3 font-medium">{{ $linha['aluno']['nome'] }}</td>
                                <td class="pr-3">{{ $linha['turma'] }}</td>
                                <td class="pr-3 text-gray-600 dark:text-gray-300">{{ collect($linha['responsaveis'])->pluck('nome')->implode(', ') ?: '—' }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $reais($linha['total_pago']) }}</td>
                                <td class="pr-3 text-right tabular-nums">{{ $reais($linha['total_dedutivel']) }}</td>
                                <td class="text-right"><a href="{{ route('informe-anual.aluno', ['ano' => $ano, 'matricula' => $linha['matricula_id']]) }}" target="_blank" rel="noopener" class="text-primary-600 underline">PDF</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">Nenhum pagamento recebido no ano com estes filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
