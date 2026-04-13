@php
    $etapas = $schemaComponent->getEtapas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-12">
    @if ($etapas->isEmpty())
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nenhuma etapa encontrada com avaliações para esta turma.</p>
        </div>
    @else
        @foreach ($etapas as $etapa)
            @php
                $dados = $schemaComponent->getDadosParaEtapa($etapa->id);
                $categorias = $dados['categorias'];
                $disciplinas = $dados['disciplinas'];
                $avaliacoes = $dados['avaliacoes'];
            @endphp

            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-1 rounded-full bg-primary-600"></div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $etapa->nome }}</h3>
                </div>
                
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-4 font-bold text-gray-900 dark:text-white" style="min-width: 200px;">Disciplina</th>
                                    @foreach ($categorias as $categoria)
                                        <th class="px-4 py-4 text-center font-bold text-gray-900 dark:text-white" title="{{ $categoria->descricao }}">
                                            {{ $categoria->nome }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($disciplinas as $disciplina)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white">
                                            {{ $disciplina->nome }}
                                        </td>
                                        @foreach ($categorias as $categoria)
                                            @php
                                                $avaliacao = $avaliacoes->where('disciplina_id', $disciplina->id)->where('categoria_avaliacao_id', $categoria->id)->first();
                                            @endphp
                                            <td class="px-4 py-4 text-center">
                                                @if ($avaliacao)
                                                    <input 
                                                        type="text" 
                                                        wire:model.defer="notas.{{ $avaliacao->id }}"
                                                        class="w-20 rounded-lg border-gray-300 py-1.5 text-center text-sm shadow-sm transition-colors focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                                        placeholder="0,0"
                                                    >
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">————</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
