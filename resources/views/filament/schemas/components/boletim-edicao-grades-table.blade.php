@php
    $etapas = $schemaComponent->getEtapas();
    $matricula = $schemaComponent->getMatricula();
@endphp

<div class="mt-6 space-y-10">
    @if ($etapas->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 text-center">
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
            @endphp

            <div class="fi-ta-ctn overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="bg-gray-50/50 px-4 py-3 border-b border-gray-200 dark:bg-gray-700/50 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ $etapa->nome }}</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-left dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-4 py-3.5 sm:px-6 text-sm font-bold text-gray-900 dark:text-white" style="min-width: 220px;">
                                    Disciplina
                                </th>
                                @foreach ($categorias as $categoria)
                                    <th class="px-4 py-3.5 text-center text-sm font-bold text-gray-900 dark:text-white" title="{{ $categoria->descricao }}">
                                        {{ $categoria->nome }}
                                    </th>
                                @endforeach
                                <th class="px-4 py-3.5 text-center text-sm font-bold text-gray-900 dark:text-white">
                                    Média Etapa
                                </th>
                                <th class="px-4 py-3.5 text-center text-sm font-bold text-gray-900 dark:text-white">
                                    Média Turma
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($disciplinas as $disciplina)
                                <tr class="transition duration-75 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-4 sm:px-6 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $disciplina->nome }}
                                    </td>
                                    @foreach ($categorias as $categoria)
                                        @php
                                            $avaliacao = $avaliacoes->where('disciplina_id', $disciplina->id)->where('categoria_avaliacao_id', $categoria->id)->first();
                                        @endphp
                                        <td class="px-4 py-4 text-center">
                                            @if ($avaliacao)
                                                <div class="flex justify-center">
                                                    <input 
                                                        type="text" 
                                                        wire:model.defer="notas.{{ $avaliacao->id }}"
                                                        class="fi-input block w-20 rounded-lg border-gray-300 bg-white py-1.5 px-2 text-center text-sm font-bold shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                                        placeholder="-"
                                                    >
                                                </div>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600 font-bold">·</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Média Aluno --}}
                                    <td class="px-4 py-4 text-center">
                                        @php $mAluno = $mediasAluno[$disciplina->id]; @endphp
                                        @if ($mAluno !== null)
                                            <span @class([
                                                'text-sm font-bold px-2.5 py-0.5 rounded-full inline-block',
                                                'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-500' => $mAluno >= 7.0,
                                                'bg-danger-100 text-danger-700 dark:bg-danger-500/10 dark:text-danger-500' => $mAluno < 7.0,
                                            ])>
                                                {{ number_format(round((float) $mAluno, 2), 1, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 font-bold">—</span>
                                        @endif
                                    </td>

                                    {{-- Média Turma --}}
                                    <td class="px-4 py-4 text-center">
                                        @php $mTurma = $mediasTurma[$disciplina->id]; @endphp
                                        @if ($mTurma !== null)
                                            <span class="text-sm font-bold text-gray-500 dark:text-gray-400">
                                                {{ number_format(round((float) $mTurma, 2), 1, ',', '.') }}
                                            </span>
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
    @endif
</div>
