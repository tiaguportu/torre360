@php
    $etapas = $schemaComponent->getEtapasComNotas();
    $notasAluno = $schemaComponent->getNotasAluno();
@endphp

<div class="mt-6 space-y-12">
    @if ($etapas->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center dark:border-gray-700 dark:bg-gray-800/50">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <x-heroicon-o-academic-cap class="h-7 w-7 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma nota registrada até o momento.</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">As tabelas por etapa avaliativa (bimestre) aparecerão assim que as primeiras notas forem lançadas.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            @php
                $avaliacoesEtapa = $schemaComponent->getAvaliacoesPorEtapa($etapa->id);
                $disciplinas = $avaliacoesEtapa->map(fn($avs) => $avs->first()->disciplina)->filter()->unique('id')->sortBy('nome');
                $categoriasEtapa = $avaliacoesEtapa->flatten(1)->map(fn($av) => $av->categoria)->filter()->unique('id');
                $notasTurma = $schemaComponent->getNotasTurmaAgrupadas($schemaComponent->getMatricula()->turma_id);

                // Variáveis para média geral da etapa
                $mediasAlunosEtapa = [];
                $mediasTurmaEtapa = [];
            @endphp

            <div class="fi-boletim-etapa-section space-y-4">
                {{-- Header da Etapa --}}
                <div class="flex items-center gap-3 px-1">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                        <span class="text-lg font-bold">{{ $loop->iteration }}</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ $etapa->nome }}</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Resultados acadêmicos referentes ao período</p>
                    </div>
                </div>

                {{-- Tabela da Etapa --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 dark:bg-gray-800/80 dark:border-gray-700">
                                    <th class="sticky left-0 z-10 bg-gray-50 px-4 py-3 font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">Disciplina</th>
                                    
                                    {{-- Colunas das Categorias --}}
                                    @foreach ($categoriasEtapa as $cat)
                                        <th class="px-4 py-3 text-center font-bold text-gray-500 dark:text-gray-400 border-l border-gray-100 dark:border-gray-700">
                                            {{ $cat->nome }}
                                        </th>
                                    @endforeach

                                    {{-- Média e Resumo --}}
                                    <th class="px-4 py-3 text-center font-bold text-primary-700 bg-primary-50/30 dark:bg-primary-900/10 dark:text-primary-400 border-l border-gray-200 dark:border-gray-700">Média Etapa</th>
                                    <th class="px-4 py-3 text-center font-bold text-purple-700 bg-purple-50/30 dark:bg-purple-900/10 dark:text-purple-400 border-l border-gray-100 dark:border-gray-700">Turma</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($disciplinas as $disciplina)
                                    @php
                                        $mediaAluno = $schemaComponent->calcularMediaFinal($disciplina->id, $etapa->id, $avaliacoesEtapa, $notasAluno);
                                        $mediaTurma = $schemaComponent->getMediaTurmaEtapa($disciplina->id, $etapa->id, $avaliacoesEtapa, $notasTurma);
                                        if ($mediaAluno !== null) $mediasAlunosEtapa[] = $mediaAluno;
                                        if ($mediaTurma !== null) $mediasTurmaEtapa[] = $mediaTurma;
                                    @endphp
                                    <tr class="group hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                        <td class="sticky left-0 z-10 bg-white group-hover:bg-gray-50/50 px-4 py-3 font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:group-hover:bg-gray-800/30">
                                            {{ $disciplina->nome }}
                                        </td>

                                        {{-- Notas por Categoria --}}
                                        @foreach ($categoriasEtapa as $cat)
                                            <td class="px-4 py-3 text-center border-l border-gray-50 dark:border-gray-800/50">
                                                @php
                                                    $valorCat = $schemaComponent->getMediaConsolidadaCategoria($cat->id, $disciplina->id, $etapa->id, $avaliacoesEtapa, $notasAluno);
                                                    $isIgnorada = $valorCat !== null && $schemaComponent->isCategoriaIgnorada($cat->id, $disciplina->id, $etapa->id, $avaliacoesEtapa, $notasAluno);
                                                @endphp
                                                @if ($valorCat !== null)
                                                    <span @class([
                                                        'inline-flex items-center justify-center rounded px-2 py-0.5 text-sm font-bold tabular-nums',
                                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $valorCat >= 6 && !$isIgnorada,
                                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $valorCat < 6 && !$isIgnorada,
                                                        'bg-gray-100 text-gray-400 line-through dark:bg-gray-800 dark:text-gray-500' => $isIgnorada,
                                                    ])>
                                                        {{ number_format($valorCat, 1, ',', '.') }}
                                                    </span>
                                                @else
                                                    @php
                                                        $temAvaliacao = $avaliacoesEtapa->get($disciplina->id, collect())->where('categoria_avaliacao_id', $cat->id)->isNotEmpty();
                                                    @endphp
                                                    <span class="text-xs text-gray-300 dark:text-gray-700">{{ $temAvaliacao ? '—' : '·' }}</span>
                                                @endif
                                            </td>
                                        @endforeach

                                        {{-- Média Final da Disciplina na Etapa --}}
                                        <td class="px-4 py-3 text-center bg-primary-50/20 dark:bg-primary-900/5 border-l border-gray-200 dark:border-gray-700">
                                            @if ($mediaAluno !== null)
                                                <span @class([
                                                    'inline-flex items-center justify-center rounded-lg px-2.5 py-1 text-sm font-extrabold tabular-nums',
                                                    'bg-primary-500 text-white shadow-sm' => $mediaAluno >= 7.0,
                                                    'bg-yellow-500 text-white' => $mediaAluno >= 5.0 && $mediaAluno < 7.0,
                                                    'bg-red-500 text-white' => $mediaAluno < 5.0,
                                                ])>
                                                    {{ number_format($mediaAluno, 1, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                            @endif
                                        </td>

                                        {{-- Média da Turma na Etapa --}}
                                        <td class="px-4 py-3 text-center bg-purple-50/20 dark:bg-purple-900/5 border-l border-gray-50 dark:border-gray-800">
                                            @if ($mediaTurma !== null)
                                                <span class="text-xs font-medium text-purple-600 dark:text-purple-400">
                                                    {{ number_format($mediaTurma, 1, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-200 dark:text-gray-700">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            {{-- Rodapé com Média Geral da Etapa --}}
                            <tfoot>
                                <tr class="bg-gray-50/50 border-t border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                                    <td class="sticky left-0 z-10 bg-gray-50 px-4 py-3 font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400 text-xs uppercase tracking-wider">Média Geral do Período</td>
                                    @foreach ($categoriasEtapa as $cat)
                                        <td class="px-4 py-3 text-center"></td>
                                    @endforeach
                                    <td class="px-4 py-3 text-center bg-primary-50/40 dark:bg-primary-900/10 font-black text-primary-700 dark:text-primary-400">
                                        @if (count($mediasAlunosEtapa) > 0)
                                            {{ number_format(array_sum($mediasAlunosEtapa) / count($mediasAlunosEtapa), 1, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center bg-purple-50/40 dark:bg-purple-900/10 font-bold text-purple-600 dark:text-purple-400">
                                        @if (count($mediasTurmaEtapa) > 0)
                                            {{ number_format(array_sum($mediasTurmaEtapa) / count($mediasTurmaEtapa), 1, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Legenda Unificada --}}
        <div class="flex flex-wrap items-center gap-6 rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs dark:border-gray-700 dark:bg-gray-800/50">
            <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-sm bg-primary-500"></div>
                <span class="text-gray-600 dark:text-gray-400 font-medium">Aprovado (≥ 7,0)</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-sm bg-yellow-500"></div>
                <span class="text-gray-600 dark:text-gray-400 font-medium">Recuperação (5,0 – 6,9)</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-3 w-3 rounded-sm bg-red-500"></div>
                <span class="text-gray-600 dark:text-gray-400 font-medium">Reprovado (< 5,0)</span>
            </div>
            <div class="flex items-center gap-2">
                <x-heroicon-m-exclamation-circle class="h-4 w-4 text-gray-400" />
                <span class="text-gray-600 dark:text-gray-400">As notas riscadas indicam avaliações substituídas por outras de maior valor.</span>
            </div>
            <div class="ml-auto text-gray-400 dark:text-gray-500 italic">
                * Notas consolidadas por categoria de avaliação.
            </div>
        </div>
    @endif
</div>
