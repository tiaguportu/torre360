<x-filament-panels::page>
    @php
        // Garantir que as relações estão carregadas para não haver N+1
        $respostas = $records->load(['questionario', 'user', 'perguntaRespostas.pergunta']);

        // Mapear os dados comuns (cabeçalhos)
        $colunas = [];
        foreach ($respostas as $resposta) {
            $colunas[$resposta->id] = [
                'Questionário' => $resposta->questionario->titulo ?? 'N/A',
                'Respondente' => $resposta->user->name ?? 'Anônimo',
                'Perfil' => $resposta->perfil_institucional,
                'Data Envio' => $resposta->fim_preenchimento ? $resposta->fim_preenchimento->format('d/m/Y H:i') : '-',
            ];
        }
        
        // Obter todas as perguntas únicas com base no identificador (ou fallback para ID)
        $perguntasRow = [];
        $perguntasLabels = [];
        
        foreach ($respostas as $resposta) {
            foreach ($resposta->perguntaRespostas as $pr) {
                $pergunta = $pr->pergunta;
                if (!$pergunta) continue;

                // Agrupador: identificador tem prioridade, caso não tenha, usa o id da pergunta (não será agrupado com outros questionários)
                $key = $pergunta->identificador ?? ('pergunta_' . $pergunta->id);
                $label = $pergunta->enunciado;
                
                if (!isset($perguntasRow[$key])) {
                    $perguntasRow[$key] = [];
                    $perguntasLabels[$key] = $label;
                }
                
                $valorResposta = $pr->resposta_texto;
                if (is_array($pr->resposta_json)) {
                    $valorResposta = implode(', ', $pr->resposta_json);
                }
                
                $perguntasRow[$key][$resposta->id] = $valorResposta;
            }
        }
    @endphp

    <div class="fi-ta">
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="fi-ta-main">
                <div class="fi-ta-content overflow-x-auto">
                    <table class="fi-ta-table w-full text-left divide-y table-auto divide-gray-200 dark:divide-white/5">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="fi-ta-row">
                            <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 font-semibold text-sm text-gray-950 dark:text-white w-[300px] min-w-[250px] max-w-[350px]">
                                Campo / Pergunta
                            </th>
                            @foreach ($respostas as $resposta)
                                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 font-semibold text-sm text-gray-950 dark:text-white min-w-[300px]">
                                    Resposta #{{ $resposta->id }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">
                        <!-- Campos Comuns -->
                        @foreach (['Questionário', 'Respondente', 'Perfil', 'Data Envio'] as $campo)
                            <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="fi-ta-cell px-3 py-4 sm:first-of-type:ps-6 sm:last-of-type:pe-6 font-medium text-sm text-gray-950 dark:text-white bg-gray-50/50 dark:bg-white/5 w-[300px] min-w-[250px] max-w-[350px]">
                                    {{ $campo }}
                                </td>
                                @foreach ($respostas as $resposta)
                                    <td class="fi-ta-cell px-3 py-4 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-sm text-gray-600 dark:text-gray-400 whitespace-normal break-words" style="word-break: break-word; white-space: normal;">
                                        {{ $colunas[$resposta->id][$campo] }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        
                        <!-- Separador -->
                        <tr class="fi-ta-row bg-gray-100 dark:bg-white/10">
                            <td colspan="{{ count($respostas) + 1 }}" class="fi-ta-cell px-3 py-2 sm:first-of-type:ps-6 sm:last-of-type:pe-6 font-bold text-gray-950 dark:text-white uppercase text-xs tracking-wider">
                                Perguntas e Respostas
                            </td>
                        </tr>
                        
                        <!-- Perguntas -->
                        @foreach ($perguntasRow as $key => $valores)
                            <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="fi-ta-cell px-3 py-4 sm:first-of-type:ps-6 sm:last-of-type:pe-6 align-top bg-gray-50/50 dark:bg-white/5 w-[300px] min-w-[250px] max-w-[350px]" style="word-break: break-word; white-space: normal;">
                                    <div class="prose dark:prose-invert max-w-full break-words text-sm" style="word-break: break-word; white-space: normal;">{!! $perguntasLabels[$key] !!}</div>
                                    @if(!str_starts_with($key, 'pergunta_'))
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono">ID: {{ $key }}</div>
                                    @endif
                                </td>
                                @foreach ($respostas as $resposta)
                                    <td class="fi-ta-cell px-3 py-4 sm:first-of-type:ps-6 sm:last-of-type:pe-6 align-top whitespace-normal break-words" style="word-break: break-word; white-space: normal;">
                                        @if(isset($valores[$resposta->id]))
                                            @if(empty($valores[$resposta->id]))
                                                (Vazio)
                                            @else
                                                <div class="prose dark:prose-invert max-w-full break-words text-sm text-gray-600 dark:text-gray-400" style="word-break: break-word; white-space: normal;">{!! $valores[$resposta->id] !!}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
            
                        @if(empty($perguntasRow))
                            <tr class="fi-ta-row">
                                <td colspan="{{ count($respostas) + 1 }}" class="fi-ta-cell px-3 py-6 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                                        Nenhuma resposta encontrada para as perguntas destes questionários.
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
