<div class="overflow-x-auto">
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

    <table class="w-full text-left divide-y table-auto divide-gray-200 dark:divide-white/5">
        <thead>
            <tr>
                <th class="px-4 py-3 bg-gray-50 dark:bg-white/5 font-medium text-sm text-gray-950 dark:text-white">Campo / Pergunta</th>
                @foreach ($respostas as $resposta)
                    <th class="px-4 py-3 bg-gray-50 dark:bg-white/5 font-medium text-sm text-gray-950 dark:text-white">Resposta #{{ $resposta->id }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5 text-sm">
            <!-- Campos Comuns -->
            @foreach (['Questionário', 'Respondente', 'Perfil', 'Data Envio'] as $campo)
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white bg-gray-50/50 dark:bg-white/5 whitespace-nowrap">{{ $campo }}</td>
                    @foreach ($respostas as $resposta)
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $colunas[$resposta->id][$campo] }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            
            <!-- Separador -->
            <tr>
                <td colspan="{{ count($respostas) + 1 }}" class="px-4 py-2 bg-gray-100 dark:bg-white/10 font-bold text-gray-950 dark:text-white uppercase text-xs tracking-wider">
                    Perguntas e Respostas
                </td>
            </tr>
            
            <!-- Perguntas -->
            @foreach ($perguntasRow as $key => $valores)
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white min-w-[250px] align-top bg-gray-50/50 dark:bg-white/5">
                        {{ $perguntasLabels[$key] }}
                        @if(!str_starts_with($key, 'pergunta_'))
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono">ID: {{ $key }}</div>
                        @endif
                    </td>
                    @foreach ($respostas as $resposta)
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 align-top">
                            @if(isset($valores[$resposta->id]))
                                {{ empty($valores[$resposta->id]) ? '(Vazio)' : $valores[$resposta->id] }}
                            @else
                                <span class="text-gray-300 dark:text-gray-600">-</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach

            @if(empty($perguntasRow))
                <tr>
                    <td colspan="{{ count($respostas) + 1 }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                        Nenhuma resposta encontrada para as perguntas destes questionários.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
