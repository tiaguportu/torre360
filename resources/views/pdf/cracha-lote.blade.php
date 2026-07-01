<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crachás</title>
    <style>
        @page {
            margin: 20pt 20pt 20pt 20pt;
            size: A4 portrait;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
        }
        .a4-page {
            width: 555pt; /* A4 = 595pt - 40pt margens */
            page-break-after: always;
        }
        .a4-page:last-child {
            page-break-after: avoid;
        }
        .badges-row {
            overflow: hidden;
        }
        .badge-cell {
            float: left;
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }
        .badge-inner {
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }
        .element-wrapper {
            position: absolute;
            box-sizing: border-box;
        }
        .text-element {
            word-wrap: break-word;
        }
        .photo-element {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
    </style>
</head>
<body>
    @php
        // Dimensões da área útil do A4 em pontos (595 - 40 de margem)
        $pageWidth = 555;
        $pageHeight = 802; // 842pt - 40pt margens

        // Quantos crachás cabem por linha e por coluna
        $cols = max(1, floor($pageWidth / $crachaLargura));
        $rows = max(1, floor($pageHeight / $crachaAltura));
        $perPage = $cols * $rows;

        // Espaçamento horizontal e vertical para centralizar
        $hGap = ($pageWidth - ($cols * $crachaLargura)) / ($cols + 1);
        $vGap = ($pageHeight - ($rows * $crachaAltura)) / ($rows + 1);

        // Divide as pessoas em páginas
        $pages = $pessoas->chunk($perPage);
    @endphp

    @foreach ($pages as $pageIndex => $pagePessoas)
        <div class="a4-page">
            @foreach ($pagePessoas as $badgeIndex => $pessoa)
                @php
                    $col = $badgeIndex % $cols;
                    $row = floor($badgeIndex / $cols);

                    $cellLeft = $hGap + $col * ($crachaLargura + $hGap);
                    $cellTop = $vGap + $row * ($crachaAltura + $vGap);
                @endphp

                <div class="badge-cell" style="
                    position: absolute;
                    left: {{ $cellLeft }}pt;
                    top: {{ $cellTop }}pt;
                    width: {{ $crachaLargura }}pt;
                    height: {{ $crachaAltura }}pt;
                ">
                    <div class="badge-inner" style="
                        width: {{ $crachaLargura }}pt;
                        height: {{ $crachaAltura }}pt;
                        background-color: #ffffff;
                        border: 0.5pt solid #cccccc;
                    ">
                        @if ($backgroundImage)
                            <img class="background-image" src="{{ $backgroundImage }}" />
                        @endif

                        @foreach ($objects as $objIndex => $obj)
                            @php
                                $zIndex = 2 + $objIndex;
                                $scaleX = $obj['scaleX'] ?? 1;
                                $scaleY = $obj['scaleY'] ?? 1;

                                $elLeft = ($obj['left'] ?? 0) * 0.75;
                                $elTop = ($obj['top'] ?? 0) * 0.75;
                                $elWidth = ($obj['width'] ?? 200) * $scaleX * 0.75;
                                $elHeight = ($obj['height'] ?? 30) * $scaleY * 0.75;
                            @endphp

                            @if (isset($obj['id']) && str_starts_with($obj['id'], 'foto'))
                                {{-- Foto da pessoa --}}
                                @php
                                    $fotoUrl = null;
                                    if ($pessoa->foto && \Illuminate\Support\Facades\Storage::exists($pessoa->foto)) {
                                        try {
                                            $mimeType = \Illuminate\Support\Facades\Storage::mimeType($pessoa->foto) ?? 'image/jpeg';
                                            $fileContent = \Illuminate\Support\Facades\Storage::get($pessoa->foto);
                                            $fotoUrl = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
                                        } catch (\Exception $e) {
                                            // Fallback
                                        }
                                    }

                                    if (!$fotoUrl) {
                                        $fotoUrl = 'https://ui-avatars.com/api/?name=' . urlencode($pessoa->nome) . '&color=7F9CF5&background=EBF4FF';
                                    }
                                @endphp
                                <div class="element-wrapper" style="
                                    left: {{ $elLeft }}pt;
                                    top: {{ $elTop }}pt;
                                    width: {{ $elWidth }}pt;
                                    height: {{ $elHeight }}pt;
                                    z-index: {{ $zIndex }};
                                ">
                                    <img class="photo-element" src="{{ $fotoUrl }}" />
                                </div>

                            @elseif (($obj['type'] ?? '') === 'image' && !str_starts_with($obj['id'] ?? '', 'foto'))
                                {{-- Imagem editável inserida pelo usuário --}}
                                @php
                                    $imgSrc = $obj['src'] ?? '';
                                @endphp
                                @if ($imgSrc)
                                <div class="element-wrapper" style="
                                    left: {{ $elLeft }}pt;
                                    top: {{ $elTop }}pt;
                                    width: {{ $elWidth }}pt;
                                    height: {{ $elHeight }}pt;
                                    z-index: {{ $zIndex }};
                                ">
                                    <img class="photo-element" src="{{ $imgSrc }}" />
                                </div>
                                @endif

                            @elseif (($obj['type'] ?? '') === 'text' || ($obj['type'] ?? '') === 'i-text')
                                {{-- Texto --}}
                                @php
                                    $textoSubstituido = str_replace([
                                        '{nome}',
                                        '{cpf}',
                                        '{email}',
                                        '{telefone}',
                                        '{profissao}',
                                        '{identidade}',
                                        '{data_nascimento}',
                                        '{sexo}',
                                        '{cor_raca}',
                                    ], [
                                        $pessoa->nome ?? '',
                                        $pessoa->cpf ?? '',
                                        $pessoa->email ?? '',
                                        $pessoa->telefone ?? '',
                                        $pessoa->profissao ?? '',
                                        $pessoa->identidade ?? '',
                                        $pessoa->data_nascimento ? \Carbon\Carbon::parse($pessoa->data_nascimento)->format('d/m/Y') : '',
                                        $pessoa->sexo?->value ?? $pessoa->sexo ?? '',
                                        $pessoa->cor_raca?->value ?? $pessoa->cor_raca ?? '',
                                    ], $obj['text'] ?? '');

                                    $fontSize = ($obj['fontSize'] ?? 16) * $scaleY * 0.75;
                                    $color = $obj['fill'] ?? '#000000';
                                    $fontWeight = ($obj['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';
                                    $fontStyle = ($obj['fontStyle'] ?? 'normal') === 'italic' ? 'italic' : 'normal';
                                    $textAlign = $obj['textAlign'] ?? 'left';
                                    $lineHeight = $obj['lineHeight'] ?? 1.16;
                                    $fontFamily = $obj['fontFamily'] ?? 'sans-serif';
                                @endphp
                                <div class="element-wrapper text-element" style="
                                    left: {{ $elLeft }}pt;
                                    top: {{ $elTop }}pt;
                                    width: {{ $elWidth }}pt;
                                    height: {{ $elHeight }}pt;
                                    font-size: {{ $fontSize }}pt;
                                    color: {{ $color }};
                                    font-weight: {{ $fontWeight }};
                                    font-style: {{ $fontStyle }};
                                    font-family: {{ $fontFamily }};
                                    text-align: {{ $textAlign }};
                                    line-height: {{ $lineHeight }};
                                    z-index: {{ $zIndex }};
                                ">
                                    {{ $textoSubstituido }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
