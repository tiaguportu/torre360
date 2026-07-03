<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crachás V2</title>
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
            overflow: hidden;
            background-color: #ffffff;
            border: 0.5pt solid #cccccc;
            box-sizing: border-box;
        }
        .badge-container svg {
            display: block;
            width: 100%;
            height: 100%;
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

        // Divide as pessoas/svgs em páginas
        $pages = $svgs->chunk($perPage);
    @endphp

    @foreach ($pages as $pageIndex => $pageSvgs)
        @php
            // Divide os svgs desta página em linhas
            $rowChunks = $pageSvgs->chunk($cols);
        @endphp

        <table class="grid">
            @foreach ($rowChunks as $rowSvgs)
                <tr>
                    @foreach ($rowSvgs as $item)
                        <td>
                            <div class="badge-container" style="width: {{ $crachaLargura }}pt; height: {{ $crachaAltura }}pt; position: relative; overflow: hidden;">
                                <!-- Fundo do Crachá (renderizado como imagem para garantir alinhamento perfeito de fontes e matrizes de transformações) -->
                                <img src="data:image/svg+xml;base64,{{ base64_encode($item->svg) }}" style="width: 100%; height: 100%; display: block; border: none; padding: 0; margin: 0;" />
                                
                                <!-- Foto do Aluno sobreposta absolutamente -->
                                @if (isset($item->foto_bbox))
                                    <img src="{{ $item->foto_url }}" style="
                                        position: absolute;
                                        left: {{ $item->foto_bbox['x'] }}pt;
                                        top: {{ $item->foto_bbox['y'] }}pt;
                                        width: {{ $item->foto_bbox['width'] }}pt;
                                        height: {{ $item->foto_bbox['height'] }}pt;
                                        object-fit: cover;
                                        border-radius: {{ $item->foto_bbox['is_circle'] ? '50%' : '0' }};
                                        z-index: 10;
                                    " />
                                @endif
                            </div>
                        </td>
                    @endforeach

                    {{-- Preenche células vazias na última linha --}}
                    @for ($i = $rowSvgs->count(); $i < $cols; $i++)
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
