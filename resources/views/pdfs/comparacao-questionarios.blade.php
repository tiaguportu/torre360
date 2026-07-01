<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Comparação de Respostas de Questionários</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        @page {
            margin: 30px;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            text-transform: uppercase;
            color: #111;
        }

        .header p {
            margin: 3px 0 0 0;
            font-size: 10px;
            color: #666;
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }

        .comparison-table th,
        .comparison-table td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        .comparison-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .category-header {
            background-color: #e6e6e6;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }

        .field-label {
            background-color: #fcfcfc;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #999;
        }

        .prose {
            font-size: 10px;
            line-height: 1.4;
        }

        .prose p {
            margin: 0 0 4px 0;
        }

        .prose p:last-child {
            margin-bottom: 0;
        }

        .footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 15px;
            text-align: right;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>

<body>

    @php
        // Garantir que as relações estão carregadas para não haver N+1
        $respostas = $records->load(['questionario', 'user', 'perguntaRespostas.pergunta.bloco']);

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
        $ordemPerguntas = [];
        
        foreach ($respostas as $resposta) {
            foreach ($resposta->perguntaRespostas as $pr) {
                $pergunta = $pr->pergunta;
                if (!$pergunta) continue;

                $key = $pergunta->identificador ?? ('pergunta_' . $pergunta->id);
                $label = $pergunta->enunciado;
                
                if (!isset($perguntasRow[$key])) {
                    $perguntasRow[$key] = [];
                    $perguntasLabels[$key] = $label;
                    $ordemPerguntas[$key] = [
                        'bloco_ordem' => $pergunta->bloco->ordem ?? 0,
                        'pergunta_ordem' => $pergunta->ordem ?? 0,
                        'pergunta_id' => $pergunta->id
                    ];
                }
                
                $valorResposta = $pr->valor_exibicao;
                $perguntasRow[$key][$resposta->id] = $valorResposta;
            }
        }

        // Ordenar as chaves de perguntasRow com base no array de ordens (Bloco primeiro, depois Pergunta)
        uksort($perguntasRow, function ($a, $b) use ($ordemPerguntas) {
            $ordemA = $ordemPerguntas[$a] ?? ['bloco_ordem' => 0, 'pergunta_ordem' => 0, 'pergunta_id' => 0];
            $ordemB = $ordemPerguntas[$b] ?? ['bloco_ordem' => 0, 'pergunta_ordem' => 0, 'pergunta_id' => 0];
            
            if ($ordemA['bloco_ordem'] !== $ordemB['bloco_ordem']) {
                return $ordemA['bloco_ordem'] <=> $ordemB['bloco_ordem'];
            }
            
            if ($ordemA['pergunta_ordem'] !== $ordemB['pergunta_ordem']) {
                return $ordemA['pergunta_ordem'] <=> $ordemB['pergunta_ordem'];
            }
            
            return $ordemA['pergunta_id'] <=> $ordemB['pergunta_id'];
        });

        $totalRespostas = count($respostas);
        $colWidth = floor(60 / $totalRespostas); // Divide o espaço restante igualmente

        $instituicao = \App\Models\InstituicaoEnsino::first();
        $logoBase64 = null;
        if ($instituicao?->logo) {
            try {
                $disk = config('filament.default_filesystem_disk', 'local');
                if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($instituicao->logo)) {
                    $logoContent = \Illuminate\Support\Facades\Storage::disk($disk)->get($instituicao->logo);
                    $mimeType = \Illuminate\Support\Facades\Storage::disk($disk)->mimeType($instituicao->logo);
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoContent);
                } else if (\Illuminate\Support\Facades\Storage::disk('public')->exists($instituicao->logo)) {
                    $logoContent = \Illuminate\Support\Facades\Storage::disk('public')->get($instituicao->logo);
                    $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($instituicao->logo);
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoContent);
                }
            } catch (\Exception $e) {
                // Erro ao carregar logo
            }
        }
    @endphp

    <div class="header">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 80px; border: none; vertical-align: middle;">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" style="width: 80px; height: auto;">
                    @else
                        <div style="width: 80px; height: 80px; background-color: #eee; border-radius: 5px; text-align: center; line-height: 80px; color: #999; font-size: 16px; font-weight: bold;">
                            T360
                        </div>
                    @endif
                </td>
                <td style="border: none; vertical-align: middle; padding-left: 20px; text-align: left;">
                    <h1 style="margin: 0; font-size: 16px; text-transform: uppercase; color: #111;">Comparação de Respostas de Questionários</h1>
                    <p style="margin: 3px 0 0 0; font-size: 10px; color: #666;">Documento gerado em: {{ now()->format('d/m/Y H:i:s') }}</p>
                    @if($instituicao)
                        <p style="margin: 5px 0 0 0; font-size: 12px; font-weight: bold; color: #333;">{{ $instituicao->nome }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="comparison-table">
        <thead>
            <tr>
                <th style="width: 40%;">Campo / Pergunta</th>
                @foreach ($respostas as $resposta)
                    <th style="width: {{ $colWidth }}%;">Resposta #{{ $resposta->id }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <!-- Campos Comuns -->
            @foreach (['Questionário', 'Respondente', 'Data Envio'] as $campo)
                <tr>
                    <td class="field-label">{{ $campo }}</td>
                    @foreach ($respostas as $resposta)
                        <td>{{ $colunas[$resposta->id][$campo] }}</td>
                    @endforeach
                </tr>
            @endforeach
            
            <!-- Separador -->
            <tr>
                <td colspan="{{ $totalRespostas + 1 }}" class="category-header">
                    Perguntas e Respostas
                </td>
            </tr>
            
            <!-- Perguntas -->
            @foreach ($perguntasRow as $key => $valores)
                <tr>
                    <td>
                        <div class="prose">{!! $perguntasLabels[$key] !!}</div>
                        @if(!str_starts_with($key, 'pergunta_'))
                            <div style="margin-top: 3px; font-size: 8px; color: #777; font-family: monospace;">ID: {{ $key }}</div>
                        @endif
                    </td>
                    @foreach ($respostas as $resposta)
                        <td>
                            @if(isset($valores[$resposta->id]))
                                @if(empty($valores[$resposta->id]))
                                    <span class="text-muted">(Vazio)</span>
                                @else
                                    <div class="prose">{!! $valores[$resposta->id] !!}</div>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach

            @if(empty($perguntasRow))
                <tr>
                    <td colspan="{{ $totalRespostas + 1 }}" class="text-center text-muted" style="padding: 15px;">
                        Nenhuma resposta encontrada para as perguntas destes questionários.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Torre360 - Página 1 de 1
    </div>

</body>

</html>
