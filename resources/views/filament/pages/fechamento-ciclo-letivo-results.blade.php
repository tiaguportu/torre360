@if($resultados && $resultados->isNotEmpty())
    <div class="mt-8 space-y-6">
        @foreach($resultados->groupBy(fn ($r) => $r->matricula->turma?->nome ?? 'Sem turma') as $turmaNome => $porTurma)
            <x-filament::section :heading="$turmaNome">
                <div class="overflow-hidden border rounded-lg dark:border-white/10">
                    <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Aluno</th>
                                <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Disciplina</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Média Final</th>
                                <th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-white/5 dark:divide-white/10">
                            @foreach($porTurma as $registro)
                                <tr>
                                    <td class="px-4 py-2">{{ $registro->matricula->pessoa?->nome ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $registro->disciplina?->nome ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ $registro->media_final !== null ? number_format((float) $registro->media_final, 2, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($registro->situacao)
                                            <x-filament::badge :color="$registro->situacao->getColor()">
                                                {{ $registro->situacao->getLabel() }}
                                            </x-filament::badge>
                                        @else
                                            <span class="text-gray-400">Sem dados</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    </div>
@else
    <div class="flex flex-col items-center justify-center p-12 text-center border-2 border-dashed rounded-xl border-gray-200 dark:border-white/10 mt-8">
        <x-filament::icon
            icon="heroicon-o-clipboard-document-check"
            class="w-12 h-12 text-gray-400 mb-4"
        />
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Nenhum fechamento realizado ainda</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Selecione o período letivo e clique em "Calcular Situação Final".</p>
    </div>
@endif
