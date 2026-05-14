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

    <x-filament-tables::container>
        <x-filament-tables::table>
            <x-slot name="header">
                <x-filament-tables::row>
                    <x-filament-tables::header-cell>
                        Campo / Pergunta
                    </x-filament-tables::header-cell>
                    @foreach ($respostas as $resposta)
                        <x-filament-tables::header-cell>
                            Resposta #{{ $resposta->id }}
                        </x-filament-tables::header-cell>
                    @endforeach
                </x-filament-tables::row>
            </x-slot>

            <!-- Campos Comuns -->
            @foreach (['Questionário', 'Respondente', 'Perfil', 'Data Envio'] as $campo)
                <x-filament-tables::row>
                    <x-filament-tables::cell class="bg-gray-50/50 dark:bg-white/5">
                        <span class="font-medium px-4 py-3 block text-sm">{{ $campo }}</span>
                    </x-filament-tables::cell>
                    @foreach ($respostas as $resposta)
                        <x-filament-tables::cell>
                            <span class="px-4 py-3 block text-sm text-gray-600 dark:text-gray-400">
                                {{ $colunas[$resposta->id][$campo] }}
                            </span>
                        </x-filament-tables::cell>
                    @endforeach
                </x-filament-tables::row>
            @endforeach
            
            <!-- Separador -->
            <x-filament-tables::row>
                <x-filament-tables::cell colspan="{{ count($respostas) + 1 }}" class="bg-gray-100 dark:bg-white/10">
                    <span class="px-4 py-2 block font-bold text-gray-950 dark:text-white uppercase text-xs tracking-wider">
                        Perguntas e Respostas
                    </span>
                </x-filament-tables::cell>
            </x-filament-tables::row>
            
            <!-- Perguntas -->
            @foreach ($perguntasRow as $key => $valores)
                <x-filament-tables::row>
                    <x-filament-tables::cell class="align-top bg-gray-50/50 dark:bg-white/5 min-w-[250px]">
                        <div class="px-4 py-3">
                            <div class="prose dark:prose-invert max-w-none text-sm">{!! $perguntasLabels[$key] !!}</div>
                            @if(!str_starts_with($key, 'pergunta_'))
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono">ID: {{ $key }}</div>
                            @endif
                        </div>
                    </x-filament-tables::cell>
                    @foreach ($respostas as $resposta)
                        <x-filament-tables::cell class="align-top">
                            <div class="px-4 py-3">
                                @if(isset($valores[$resposta->id]))
                                    @if(empty($valores[$resposta->id]))
                                        (Vazio)
                                    @else
                                        <div class="prose dark:prose-invert max-w-none text-sm text-gray-600 dark:text-gray-400">{!! $valores[$resposta->id] !!}</div>
                                    @endif
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">-</span>
                                @endif
                            </div>
                        </x-filament-tables::cell>
                    @endforeach
                </x-filament-tables::row>
            @endforeach

            @if(empty($perguntasRow))
                <x-filament-tables::row>
                    <x-filament-tables::cell colspan="{{ count($respostas) + 1 }}">
                        <div class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            Nenhuma resposta encontrada para as perguntas destes questionários.
                        </div>
                    </x-filament-tables::cell>
                </x-filament-tables::row>
            @endif
        </x-filament-tables::table>
    </x-filament-tables::container>
</x-filament-panels::page>
