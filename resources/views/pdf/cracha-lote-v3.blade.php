<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crachás V3</title>
    <style>
        @page {
            margin: 15pt;
            size: A4 portrait;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
        }
        table.grid {
            border-collapse: collapse;
            width: 100%;
        }
        table.grid td {
            padding: 4pt;
            vertical-align: top;
        }
        .badge-container {
            position: relative;
            border: 0.5pt solid #cccccc;
            box-sizing: border-box;
            overflow: hidden;
        }
        .badge-element {
            position: absolute;
            box-sizing: border-box;
        }
        .badge-element span {
            display: block;
            width: 100%;
        }
    </style>
</head>
<body>
    @php
        // Área útil do A4 em pontos (595pt - 30pt margens = 565pt)
        $pageWidth = 565;
        $pageHeight = 812; // 842pt - 30pt margens

        // Quantos crachás cabem por linha e por coluna
        $cols = max(1, floor($pageWidth / ($crachaLargura + 8))); // +8 = padding
        $rows = max(1, floor($pageHeight / ($crachaAltura + 8)));
        $perPage = $cols * $rows;

        // Divide os crachás em páginas
        $pages = $crachas->chunk($perPage);
    @endphp

    @foreach ($pages as $pageIndex => $pageCrachas)
        @php
            $rowChunks = $pageCrachas->chunk($cols);
        @endphp

        <table class="grid">
            @foreach ($rowChunks as $rowCrachas)
                <tr>
                    @foreach ($rowCrachas as $item)
                        <td>
                            <div class="badge-container" style="width: {{ $crachaLargura }}pt; height: {{ $crachaAltura }}pt; background: {{ $fundo }};">
                                
                                @foreach ($item->elementos as $el)
                                    @php
                                        $est = $el['estilos_processados'] ?? [];
                                        
                                        // Rotação transform
                                        $transform = '';
                                        if (isset($el['rotacao']) && $el['rotacao'] != 0) {
                                            $transform = "transform: rotate({$el['rotacao']}deg);";
                                        }

                                        // Estilos básicos
                                        $bgColor = !empty($est['bgTransparent']) ? 'transparent' : ($est['backgroundColor'] ?? 'transparent');
                                        $opacity = isset($est['opacity']) ? $est['opacity'] / 100 : 1;
                                        
                                        $border = 'none';
                                        if (isset($est['borderWidthPt']) && floatval($est['borderWidthPt']) > 0) {
                                            $border = "{$est['borderWidthPt']} solid " . ($est['borderColor'] ?? '#000000');
                                        }

                                        $borderRadius = $est['borderRadiusPt'] ?? '0px';

                                        // Estilo geral
                                        $elementStyle = "
                                            left: {$el['x_pt']}pt;
                                            top: {$el['y_pt']}pt;
                                            width: {$el['w_pt']}pt;
                                            height: {$el['h_pt']}pt;
                                            background-color: {$bgColor};
                                            opacity: {$opacity};
                                            border: {$border};
                                            border-radius: {$borderRadius};
                                            overflow: hidden;
                                            {$transform}
                                        ";

                                        // Estilos específicos de texto
                                        $textStyle = "";
                                        if ($el['tipo'] === 'texto' || ($el['tipo'] === 'variavel' && $el['variavel'] !== '{foto}')) {
                                            $fontFamily = $est['fontFamily'] ?? 'sans-serif';
                                            $fontSize = $est['fontSizePt'] ?? '10pt';
                                            $fontWeight = $est['fontWeight'] ?? 'normal';
                                            $color = $est['color'] ?? '#000000';
                                            $textAlign = $est['textAlign'] ?? 'left';
                                            $textStyle = "
                                                font-family: {$fontFamily};
                                                font-size: {$fontSize};
                                                font-weight: {$fontWeight};
                                                color: {$color};
                                                text-align: {$textAlign};
                                                line-height: 1.2;
                                            ";
                                        }
                                    @endphp

                                    @if ($el['tipo'] === 'variavel' && $el['variavel'] === '{foto}')
                                        <img src="{{ $el['foto_url'] }}" 
                                             class="badge-element" 
                                             style="{{ $elementStyle }} object-fit: cover;" />
                                    @elseif ($el['tipo'] === 'imagem')
                                        <img src="{{ $el['conteudo'] }}" 
                                             class="badge-element" 
                                             style="{{ $elementStyle }} object-fit: contain;" />
                                    @else
                                        <div class="badge-element" style="{{ $elementStyle }} {{ $textStyle }}">
                                            @if ($el['tipo'] === 'texto' || $el['tipo'] === 'variavel')
                                                <span>{{ $el['conteudo'] ?? '' }}</span>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach

                            </div>
                        </td>
                    @endforeach

                    {{-- Preenche células vazias na última linha --}}
                    @for ($i = $rowCrachas->count(); $i < $cols; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>

        @if (!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
</body>
</html>
