@php
    $data = $component->getViewData();
    $matricula = $data['matricula'];
    $avaliacoesPorDisciplina = $data['avaliacoesPorDisciplina'];
    $disciplinas = $data['disciplinas'];
    $notasAluno = $data['notasAluno'];
    $notasTurma = $data['notasTurma'];

    // Coleta todas as avaliações únicas (colunas da tabela), mantendo ordem por disciplina e etapa
    $todasAvaliacoes = $avaliacoesPorDisciplina->flatten(1)->sortBy(['disciplina_id', 'etapa_avaliativa_id', 'id']);

    $mediaGeralAluno = $component->getMediaGeralAluno($avaliacoesPorDisciplina, $notasAluno);
    $mediaGeralTurma = $component->getMediaGeralTurma($avaliacoesPorDisciplina, $notasTurma);
@endphp

<div class="mt-6 space-y-6">
    {{-- Verificação de dados --}}
    @if ($disciplinas->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center dark:border-gray-700 dark:bg-gray-800/50">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <x-heroicon-o-academic-cap class="h-7 w-7 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma avaliação encontrada para esta matrícula.</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Verifique se existem avaliações cadastradas para a turma deste aluno.</p>
        </div>
    @else
        {{-- Tabela do Boletim --}}
        <div class="fi-boletim-section overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-table-cells class="h-5 w-5 text-primary-500" />
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Notas por Disciplina e Avaliação</h3>
                </div>
                <span class="rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                    {{ $disciplinas->count() }} {{ $disciplinas->count() === 1 ? 'Disciplina' : 'Disciplinas' }}
                </span>
            </div>

            {{-- Wrapper com scroll horizontal --}}
            <div class="overflow-x-auto">
                <table class="fi-boletim-table w-full min-w-max border-collapse text-sm">
                    {{-- Cabeçalho: Disciplina | AvalX | ... | Média Aluno | Média Turma --}}
                    <thead>
                        {{-- Linha 1: grupos de cabeçalho por disciplina (avaliacoes agrupadas) --}}
                        <tr class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                            {{-- Coluna de Disciplina --}}
                            <th
                                rowspan="2"
                                class="sticky left-0 z-10 min-w-[180px] border-r border-gray-200 bg-gray-100 px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            >
                                Disciplina
                            </th>

                            {{-- Uma coluna por avaliação de cada disciplina --}}
                            @foreach ($avaliacoesPorDisciplina->sortKeys() as $disciplinaId => $avaliacoes)
                                @php $count = $avaliacoes->count(); @endphp
                                <th
                                    colspan="{{ $count }}"
                                    class="border-r border-gray-200 px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:border-gray-600 dark:text-gray-400"
                                >
                                    <span class="block truncate text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        {{ $avaliacoes->first()?->disciplina?->nome ?? '—' }}
                                    </span>
                                </th>
                            @endforeach

                            {{-- Células de Média --}}
                            <th
                                rowspan="2"
                                class="min-w-[90px] border-l border-gray-300 bg-blue-50 px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-blue-700 dark:border-gray-600 dark:bg-blue-900/20 dark:text-blue-400"
                            >
                                Média<br><span class="text-[10px] font-normal normal-case">Aluno</span>
                            </th>
                            <th
                                rowspan="2"
                                class="min-w-[90px] border-l border-gray-200 bg-purple-50 px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-purple-700 dark:border-gray-600 dark:bg-purple-900/20 dark:text-purple-400"
                            >
                                Média<br><span class="text-[10px] font-normal normal-case">Turma</span>
                            </th>
                        </tr>

                        {{-- Linha 2: nome de cada avaliação --}}
                        <tr class="border-b-2 border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800/70">
                            @foreach ($avaliacoesPorDisciplina->sortKeys() as $disciplinaId => $avaliacoes)
                                @foreach ($avaliacoes->sortBy('etapa_avaliativa_id') as $avaliacao)
                                    <th class="min-w-[80px] border-r border-gray-200 px-3 py-2 text-center dark:border-gray-700">
                                        <span class="block text-[11px] font-semibold leading-tight text-gray-700 dark:text-gray-200">
                                            {{ $avaliacao->categoria?->nome ?? $avaliacao->etapaAvaliativa?->nome ?? "Av. {$avaliacao->id}" }}
                                        </span>
                                        @if ($avaliacao->nota_maxima)
                                            <span class="block text-[9px] text-gray-400 dark:text-gray-500">
                                                (máx {{ number_format($avaliacao->nota_maxima, 1, ',', '.') }})
                                            </span>
                                        @endif
                                    </th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @foreach ($disciplinas as $disciplina)
                            @php
                                $avsDisciplina = $avaliacoesPorDisciplina->get($disciplina->id, collect())->sortBy('etapa_avaliativa_id');
                                $mediaAluno = $component->getMediaAlunoPorDisciplina($disciplina->id, $notasAluno, $avaliacoesPorDisciplina);
                                $mediaTurma = $component->getMediaTurmaPorDisciplina($disciplina->id, $notasTurma, $avaliacoesPorDisciplina);
                            @endphp
                            <tr class="group transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                {{-- Nome da Disciplina --}}
                                <td class="sticky left-0 z-10 min-w-[180px] border-r border-gray-200 bg-white px-4 py-3 font-semibold text-gray-800 group-hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:group-hover:bg-gray-800/40">
                                    {{ $disciplina->nome }}
                                </td>

                                {{-- Notas de cada avaliação para ESTA disciplina --}}
                                @foreach ($avaliacoesPorDisciplina->sortKeys() as $discId => $avaliacoes)
                                    @foreach ($avaliacoes->sortBy('etapa_avaliativa_id') as $avaliacao)
                                        @if ($discId === $disciplina->id)
                                            @php
                                                $nota = $notasAluno->get($avaliacao->id);
                                                $valor = $nota?->valor;
                                                $notaMax = (float) ($avaliacao->nota_maxima ?? 10);
                                                $percentual = $valor !== null ? ((float)$valor / $notaMax) * 100 : null;
                                                $isIgnorada = $valor !== null && $component->isNotaIgnorada($avaliacao->id, $disciplina->id, $notasAluno, $avaliacoesPorDisciplina);
                                            @endphp
                                            <td class="border-r border-gray-100 px-3 py-3 text-center dark:border-gray-700/60">
                                                @if ($valor !== null)
                                                    <div class="relative inline-block">
                                                        <span @class([
                                                            'inline-flex items-center justify-center rounded-md px-2 py-0.5 text-sm font-semibold tabular-nums',
                                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' => $percentual >= 60 && ! $isIgnorada,
                                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' => $percentual < 60 && ! $isIgnorada,
                                                            'bg-gray-100 text-gray-400 line-through dark:bg-gray-800 dark:text-gray-500' => $isIgnorada,
                                                        ]) title="{{ $isIgnorada ? 'Nota substituída' : '' }}">
                                                            {{ number_format((float)$valor, 1, ',', '.') }}
                                                        </span>
                                                        @if ($isIgnorada)
                                                            <div class="absolute -top-1 -right-1">
                                                                <span class="flex h-3 w-3 items-center justify-center rounded-full bg-gray-500 text-[8px] text-white">!</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>
                                        @else
                                            {{-- Célula vazia para avaliações de outras disciplinas --}}
                                            <td class="border-r border-gray-100 px-3 py-3 text-center dark:border-gray-700/60">
                                                <span class="text-xs text-gray-200 dark:text-gray-700">·</span>
                                            </td>
                                        @endif
                                    @endforeach
                                @endforeach

                                {{-- Média do Aluno na Disciplina --}}
                                <td class="border-l border-gray-300 bg-blue-50/70 px-3 py-3 text-center dark:border-gray-600 dark:bg-blue-900/10">
                                    @if ($mediaAluno !== null)
                                        @php $pct = ($mediaAluno / 10) * 100; @endphp
                                        <span @class([
                                            'inline-flex items-center justify-center rounded-lg px-2.5 py-1 text-sm font-bold tabular-nums',
                                            'bg-green-500 text-white' => $mediaAluno >= 7,
                                            'bg-yellow-400 text-white' => $mediaAluno >= 5 && $mediaAluno < 7,
                                            'bg-red-500 text-white' => $mediaAluno < 5,
                                        ])>
                                            {{ number_format($mediaAluno, 1, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                {{-- Média da Turma na Disciplina --}}
                                <td class="border-l border-gray-200 bg-purple-50/50 px-3 py-3 text-center dark:border-gray-600 dark:bg-purple-900/10">
                                    @if ($mediaTurma !== null)
                                        <span @class([
                                            'inline-flex items-center justify-center rounded-lg px-2.5 py-1 text-sm font-medium tabular-nums',
                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' => $mediaTurma >= 7,
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' => $mediaTurma >= 5 && $mediaTurma < 7,
                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' => $mediaTurma < 5,
                                        ])>
                                            {{ number_format($mediaTurma, 1, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        {{-- Linha de Média Geral --}}
                        <tr class="border-t-2 border-gray-300 bg-gray-100 font-bold dark:border-gray-600 dark:bg-gray-800">
                            <td class="sticky left-0 z-10 border-r border-gray-200 bg-gray-100 px-4 py-3 text-sm font-bold uppercase tracking-wide text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                Média Geral
                            </td>

                            {{-- Células vazias para colunas de avaliações --}}
                            @foreach ($avaliacoesPorDisciplina->sortKeys() as $discId => $avaliacoes)
                                @foreach ($avaliacoes as $avaliacao)
                                    <td class="border-r border-gray-200 px-3 py-3 dark:border-gray-700"></td>
                                @endforeach
                            @endforeach

                            {{-- Média Geral do Aluno --}}
                            <td class="border-l border-gray-300 bg-blue-100 px-3 py-3 text-center dark:border-gray-600 dark:bg-blue-900/20">
                                @if ($mediaGeralAluno !== null)
                                    <span @class([
                                        'inline-flex items-center justify-center rounded-lg px-3 py-1 text-base font-extrabold tabular-nums shadow-sm',
                                        'bg-green-600 text-white' => $mediaGeralAluno >= 7,
                                        'bg-yellow-500 text-white' => $mediaGeralAluno >= 5 && $mediaGeralAluno < 7,
                                        'bg-red-600 text-white' => $mediaGeralAluno < 5,
                                    ])>
                                        {{ number_format($mediaGeralAluno, 1, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Média Geral da Turma --}}
                            <td class="border-l border-gray-200 bg-purple-100 px-3 py-3 text-center dark:border-gray-600 dark:bg-purple-900/20">
                                @if ($mediaGeralTurma !== null)
                                    <span @class([
                                        'inline-flex items-center justify-center rounded-lg px-3 py-1 text-base font-bold tabular-nums',
                                        'bg-green-200 text-green-900 dark:bg-green-900/40 dark:text-green-200' => $mediaGeralTurma >= 7,
                                        'bg-yellow-200 text-yellow-900 dark:bg-yellow-900/40 dark:text-yellow-200' => $mediaGeralTurma >= 5 && $mediaGeralTurma < 7,
                                        'bg-red-200 text-red-900 dark:bg-red-900/40 dark:text-red-200' => $mediaGeralTurma < 5,
                                    ])>
                                        {{ number_format($mediaGeralTurma, 1, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Legenda --}}
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs dark:border-gray-700 dark:bg-gray-800/50">
            <span class="font-semibold text-gray-500 dark:text-gray-400">Legenda:</span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-sm bg-green-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Aprovado (≥ 7,0)</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-sm bg-yellow-400"></span>
                <span class="text-gray-600 dark:text-gray-400">Em Recuperação (5,0 – 6,9)</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-sm bg-red-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Reprovado (< 5,0)</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-sm bg-gray-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Nota Substituída</span>
            </span>
            <span class="ml-auto text-gray-400 dark:text-gray-500">
                Médias calculadas com base no peso (<em>nota_maxima</em>) e lógica de substituição de avaliações.
            </span>
        </div>
    @endif
</div>
