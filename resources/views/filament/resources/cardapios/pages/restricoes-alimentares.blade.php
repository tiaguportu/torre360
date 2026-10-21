<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6 print:hidden">
            {{ $this->form }}
        </div>

        @php($refeicoes = $this->getRefeicoes())

        @forelse($refeicoes as $item)
            @php($c = $item['cardapio'])
            @php($a = $item['afetados'])
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                        {{ $c->tipo_refeicao->getLabel() }}
                        @if($c->unidade) <span class="text-xs font-normal text-gray-500">· {{ $c->unidade->nome }}</span> @endif
                        @unless($c->publicado) <x-filament::badge size="sm" color="gray">Rascunho</x-filament::badge> @endunless
                    </h3>
                    <div class="flex flex-wrap gap-1">
                        @forelse($c->alergenosEnum() as $alergeno)
                            <x-filament::badge size="sm" color="warning">{{ $alergeno->getLabel() }}</x-filament::badge>
                        @empty
                            <span class="text-xs text-gray-500">Nenhum alérgeno declarado</span>
                        @endforelse
                    </div>
                </div>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $c->descricao }}</p>

                @foreach([
                    'confirmados' => ['🔴 Confirmado na ficha médica', 'danger'],
                    'possiveis' => ['🟠 Possível (texto livre cita o alérgeno)', 'warning'],
                    'conferir' => ['🟡 Conferir (restrição em texto livre)', 'gray'],
                ] as $chave => [$titulo, $cor])
                    @if(! empty($a[$chave]))
                        <div class="mt-4">
                            <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $titulo }} ({{ count($a[$chave]) }})</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-gray-500">
                                        <tr><th class="py-1 pr-3">Aluno</th><th class="py-1 pr-3">Turma</th><th class="py-1 pr-3">Alérgenos</th><th class="py-1">Observação da família</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($a[$chave] as $linha)
                                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                                <td class="py-1 pr-3 font-medium">{{ $linha['aluno'] }}</td>
                                                <td class="py-1 pr-3">{{ $linha['turma'] ?? '—' }}</td>
                                                <td class="py-1 pr-3">{{ $linha['alergenos'] ? implode(', ', $linha['alergenos']) : '—' }}</td>
                                                <td class="py-1">{{ $linha['observacao'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if(empty($a['confirmados']) && empty($a['possiveis']) && empty($a['conferir']))
                    <p class="mt-3 text-sm text-emerald-700 dark:text-emerald-400">Nenhum aluno ativo com restrição que conflite com esta refeição.</p>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Não há refeições cadastradas para este dia.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
