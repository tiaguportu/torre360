<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crachás</title>
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
        }
        .element-wrapper {
            position: absolute;
            box-sizing: border-box;
        }
        .text-element {
            word-wrap: break-word;
            overflow: hidden;
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
        // Área útil do A4 em pontos (595pt - 30pt margens = 565pt)
        $pageWidth = 565;
        $pageHeight = 812; // 842pt - 30pt margens

        // Quantos crachás cabem por linha e por coluna
        $cols = max(1, floor($pageWidth / ($crachaLargura + 8))); // +8 = padding
        $rows = max(1, floor($pageHeight / ($crachaAltura + 8)));
        $perPage = $cols * $rows;

        // Divide as pessoas em páginas
        $pages = $pessoas->chunk($perPage);
    @endphp

    @foreach ($pages as $pageIndex => $pagePessoas)
        @php
            // Divide as pessoas desta página em linhas
            $rowChunks = $pagePessoas->chunk($cols);
        @endphp

        <table class="grid">
            @foreach ($rowChunks as $rowPessoas)
                <tr>
                    @foreach ($rowPessoas as $pessoa)
                        <td>
                            <div class="badge-container" style="width: {{ $crachaLargura }}pt; height: {{ $crachaAltura }}pt;">
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
                                        {{-- Imagem editável --}}
                                        @php $imgSrc = $obj['src'] ?? ''; @endphp
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

                                    @elseif (in_array($obj['type'] ?? '', ['text', 'i-text', 'textbox']))
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

                                            // Para textbox, scaleX/scaleY são 1 (normalizados no editor)
                                            // Para i-text legado, ainda pode ter scale != 1
                                            $isTextbox = ($obj['type'] ?? '') === 'textbox';
                                            $fontSize = ($obj['fontSize'] ?? 16) * ($isTextbox ? 1 : $scaleY) * 0.75;
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
                                            overflow: hidden;
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
                        </td>
                    @endforeach

                    {{-- Preenche células vazias na última linha --}}
                    @for ($i = $rowPessoas->count(); $i < $cols; $i++)
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
