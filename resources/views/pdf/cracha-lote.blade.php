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
        .text-element {
            position: absolute;
            z-index: 2;
            box-sizing: border-box;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    @foreach ($pessoas as $pessoa)
        <div class="page">
            @if ($backgroundImage)
                <img class="background-image" src="{{ $backgroundImage }}" />
            @endif
            
            @foreach ($objects as $obj)
                @if (($obj['type'] ?? '') === 'text' || ($obj['type'] ?? '') === 'i-text')
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
                        
                        // Conversão de pixels (Fabric.js) para pontos (DomPDF)
                        $scaleX = $obj['scaleX'] ?? 1;
                        $scaleY = $obj['scaleY'] ?? 1;
                        
                        $left = ($obj['left'] ?? 0) * 0.75;
                        $top = ($obj['top'] ?? 0) * 0.75;
                        $width = ($obj['width'] ?? 200) * $scaleX * 0.75;
                        $height = ($obj['height'] ?? 30) * $scaleY * 0.75;
                        $fontSize = ($obj['fontSize'] ?? 16) * $scaleY * 0.75;
                        
                        $color = $obj['fill'] ?? '#000000';
                        $fontWeight = ($obj['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';
                        $fontStyle = ($obj['fontStyle'] ?? 'normal') === 'italic' ? 'italic' : 'normal';
                        $textAlign = $obj['textAlign'] ?? 'left';
                        $lineHeight = $obj['lineHeight'] ?? 1.16;
                    @endphp
                    <div class="text-element" style="
                        left: {{ $left }}pt;
                        top: {{ $top }}pt;
                        width: {{ $width }}pt;
                        height: {{ $height }}pt;
                        font-size: {{ $fontSize }}pt;
                        color: {{ $color }};
                        font-weight: {{ $fontWeight }};
                        font-style: {{ $fontStyle }};
                        text-align: {{ $textAlign }};
                        line-height: {{ $lineHeight }};
                    ">
                        {{ $textoSubstituido }}
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
</body>
</html>
