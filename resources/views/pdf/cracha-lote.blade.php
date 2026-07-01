<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Crachás</title>
    <style>
        @page {
            margin: 0;
            size: {{ $largura }}pt {{ $altura }}pt;
        }
        body {
            margin: 0;
            padding: 0;
            width: {{ $largura }}pt;
            height: {{ $altura }}pt;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
        }
        .page {
            position: relative;
            width: {{ $largura }}pt;
            height: {{ $altura }}pt;
            page-break-after: always;
            overflow: hidden;
            box-sizing: border-box;
        }
        .page:last-child {
            page-break-after: avoid;
        }
        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $largura }}pt;
            height: {{ $altura }}pt;
            z-index: 1;
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
    </style>
</head>
<body>
    @foreach ($pessoas as $pessoa)
        <div class="page">
            @if ($backgroundImage)
                <img class="background-image" src="{{ $backgroundImage }}" />
            @endif
            
            @foreach ($objects as $index => $obj)
                @php
                    // Z-index calculation (background is 1, so elements start at 2 + index)
                    $zIndex = 2 + $index;
                    
                    // Conversion of pixels (Fabric.js) to points (DomPDF)
                    $scaleX = $obj['scaleX'] ?? 1;
                    $scaleY = $obj['scaleY'] ?? 1;
                    
                    $left = ($obj['left'] ?? 0) * 0.75;
                    $top = ($obj['top'] ?? 0) * 0.75;
                    $width = ($obj['width'] ?? 200) * $scaleX * 0.75;
                    $height = ($obj['height'] ?? 30) * $scaleY * 0.75;
                @endphp
                
                @if (isset($obj['id']) && str_starts_with($obj['id'], 'foto'))
                    {{-- É um elemento de foto --}}
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
                        left: {{ $left }}pt;
                        top: {{ $top }}pt;
                        width: {{ $width }}pt;
                        height: {{ $height }}pt;
                        z-index: {{ $zIndex }};
                    ">
                        <img class="photo-element" src="{{ $fotoUrl }}" />
                    </div>
                
                @elseif (($obj['type'] ?? '') === 'text' || ($obj['type'] ?? '') === 'i-text')
                    {{-- É um elemento de texto --}}
                    @php
                        // Substitui as variáveis dinamicamente na string
                        $textoSubstituido = str_replace([
                            '{nome}',
                            '{cpf}',
                            '{email}',
                            '{telefone}',
                            '{profissao}',
                            '{identidade}',
                            '{data_nascimento}',
                            '{sexo}',
                            '{cor_raca}'
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
                        left: {{ $left }}pt;
                        top: {{ $top }}pt;
                        width: {{ $width }}pt;
                        height: {{ $height }}pt;
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
    @endforeach
</body>
</html>
