@php
    $etapas = $schemaComponent->getEtapas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-10">
    @if ($etapas->isEmpty())
        <div class="fi-ta-ctn rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 text-center">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma etapa encontrada com avaliações para esta turma.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            @php
                $dados = $schemaComponent->getDadosParaEtapa($etapa->id);
                $categorias = $dados['categorias'];
                $disciplinas = $dados['disciplinas'];
                $avaliacoes = $dados['avaliacoes'];
                $mediasAluno = $dados['mediasAluno'];
                $mediasTurma = $dados['mediasTurma'];
                $linhas = $dados['linhas'];
@endphp

            <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                {{-- Header da Tabela (Igual ao do Filament) --}}
                <div class="fi-ta-header-ctn flex flex-col gap-3 p-4 sm:px-6">
                    <div class="fi-ta-header flex items-center justify-between gap-3">
                        <h2 class="fi-ta-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            {{ $etapa->nome }}
                        </h2>
                    </div>
                </div>

                <div class="fi-ta-content overflow-x-auto">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Disciplina</span>
                                </th>
                                @foreach ($categorias as $categoria)
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-center" title="{{ $categoria->descricao }}">
                                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $categoria->nome }}</span>
                                    </th>
                                @endforeach
                                <th class="fi-ta-header-cell px-3 py-3.5 text-center">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Média Etapa</span>
                                </th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-center">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Média Turma</span>
                                </th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-center">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Frequência</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($linhas as $linha)
                                @php
                                    $disciplina = $linha['disciplina'];
                                @endphp
                                <tr class="fi-ta-row [@media(hover:hover)]:hover:bg-gray-50 dark:[@media(hover:hover)]:hover:bg-white/5">
                                    <td class="fi-ta-cell p-3 sm:first-of-type:ps-6 whitespace-nowrap">
                                        <div class="fi-ta-text grid w-full gap-y-1 px-3 py-3">
                                            <div class="flex">
                                                <div class="fi-ta-text-item inline-flex items-center gap-1.5 ">
                                                    <span class="fi-ta-text-item-label text-sm font-semibold leading-6 text-gray-950 dark:text-white">
                                                        {{ $disciplina->nome }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach ($categorias as $categoria)
                                        @php
                                            $avaliacao = $avaliacoes->where('disciplina_id', $disciplina->id)->where('categoria_avaliacao_id', $categoria->id)->first();
                                            $isIgnorada = $linha['categorias'][$categoria->id]['is_ignorada'] ?? false;
                                        @endphp
                                        <td class="fi-ta-cell p-3 text-center">
                                            @if ($avaliacao)
                                                <div class="flex justify-center flex-col items-center gap-1">
                                                    <input 
                                                        type="text" 
                                                        wire:model.defer="notas.{{ $avaliacao->id }}"
                                                        @class([
                                                            'fi-input block w-16 rounded-lg border-none bg-gray-50 py-1 px-1 text-center text-sm font-bold shadow-sm transition duration-75 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-gray-900 dark:text-white',
                                                            'line-through opacity-50' => $isIgnorada
                                                        ])
                                                        placeholder="-"
                                                    >
                                                </div>
                                            @else
                                                <div class="flex justify-center">
                                                    <span class="text-gray-200 dark:text-gray-700 font-bold">·</span>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Média Aluno --}}
                                    <td class="fi-ta-cell p-3 text-center">
                                        @php $mAluno = $linha['media_final']; @endphp
                                        @if ($mAluno !== null)
                                            <div class="fi-ta-text grid w-full gap-y-1 px-3 py-3">
                                                <div class="flex justify-center">
                                                    <div @class([
                                                        'fi-ta-text-item inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold leading-5',
                                                        'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/20' => $mAluno >= 7.0,
                                                        'bg-danger-50 text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-500 dark:ring-danger-500/20' => $mAluno < 7.0,
                                                    ])>
                                                        {{ number_format(round((float) $mAluno, 2), 1, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 font-bold">—</span>
                                        @endif
                                    </td>

                                    {{-- Média Turma --}}
                                    <td class="fi-ta-cell p-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400">
                                        @php $mTurma = $linha['media_turma']; @endphp
                                        {{ $mTurma !== null ? number_format(round((float) $mTurma, 2), 1, ',', '.') : '—' }}
                                    </td>

                                    {{-- Frequencia --}}
                                    <td class="fi-ta-cell p-3 text-center">
                                        @php
                                            $frequencia = $linha['frequencia'];
                                        @endphp
                                        @if ($frequencia !== null)
                                            <div class="fi-ta-text grid w-full gap-y-1 px-3 py-3">
                                                <div class="flex justify-center">
                                                    <div @class([
                                                        'fi-ta-text-item inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold leading-5',
                                                        'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/20' => $frequencia >= 75.0,
                                                        'bg-warning-50 text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-500 dark:ring-warning-500/20' => $frequencia >= 50.0 && $frequencia < 75.0,
                                                        'bg-danger-50 text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-500 dark:ring-danger-500/20' => $frequencia < 50.0,
                                                    ])>
                                                        {{ number_format($frequencia, 1, ',', '.') }}%
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 font-bold">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
        
        {{-- Legenda Unificada (Mantendo a paridade com o boletime-grades-table) --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="flex flex-wrap items-center gap-6 text-xs text-gray-600 dark:text-gray-400">
                <div class="flex items-center gap-2">
                    <span class="font-semibold italic text-primary-600">Dica:</span>
                    <span>As notas riscadas indicam avaliações substituídas por outras de maior valor. Clique em "Salvar" no rodapé para processar todas as alterações.</span>
                </div>
            </div>
        </div>
    @endif
</div>
