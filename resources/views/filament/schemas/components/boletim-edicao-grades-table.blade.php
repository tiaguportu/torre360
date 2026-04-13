@php
    $etapas = $schemaComponent->getEtapas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-12">
    @if ($etapas->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-center text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma etapa encontrada com avaliações para esta turma.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            @php
                $dados = $schemaComponent->getDadosParaEtapa($etapa->id);
                $categorias = $dados['categorias'];
                $disciplinas = $dados['disciplinas'];
                $avaliacoes = $dados['avaliacoes'];
            @endphp

            <div class="fi-ta-ctn overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="bg-gray-50/50 px-4 py-3 border-b border-gray-200 dark:bg-gray-700/50 dark:border-gray-700 flex items-center gap-3">
                    <div class="h-6 w-1 rounded-full bg-primary-600"></div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ $etapa->nome }}</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-left dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <th class="fi-ta-header-cell px-4 py-3.5 sm:px-6">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Disciplina</span>
                                </th>
                                @foreach ($categorias as $categoria)
                                    <th class="fi-ta-header-cell px-4 py-3.5 text-center" title="{{ $categoria->descricao }}">
                                        <div class="flex flex-col items-center">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $categoria->nome }}</span>
                                            <span class="text-[10px] text-gray-400 font-normal uppercase leading-tight">{{ $categoria->descricao }}</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($disciplinas as $disciplina)
                                <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="fi-ta-cell px-4 py-4 sm:px-6 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $disciplina->nome }}</span>
                                        </div>
                                    </td>
                                    @foreach ($categorias as $categoria)
                                        @php
                                            $avaliacao = $avaliacoes->where('disciplina_id', $disciplina->id)->where('categoria_avaliacao_id', $categoria->id)->first();
                                        @endphp
                                        <td class="fi-ta-cell px-4 py-4 text-center">
                                            @if ($avaliacao)
                                                <div class="inline-flex items-center">
                                                    <input 
                                                        type="text" 
                                                        wire:model.defer="notas.{{ $avaliacao->id }}"
                                                        class="fi-input block w-24 rounded-lg border-gray-300 bg-white py-1.5 px-3 text-center text-sm font-medium shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-400 sm:w-20"
                                                        placeholder="-"
                                                    >
                                                </div>
                                            @else
                                                <div class="flex justify-center">
                                                    <div class="h-1 w-6 rounded-full bg-gray-100 dark:bg-gray-700"></div>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
