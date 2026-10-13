<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Folha de Respostas OMR</title>
    <style>
        @page {
            size: 210mm 297mm;
            margin: 0;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 210mm;
            height: 297mm;
            font-family: 'Helvetica', 'Arial', sans-serif;
            background: #ffffff;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach($folhas as $item)
    <div style="width: 210mm; height: 297mm; position: relative;" @if(!$loop->last) class="page-break" @endif>
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 210 297"
             width="210mm"
             height="297mm"
             style="display: block;">

            <!-- Zona de silêncio e fundo branco -->
            <rect x="0" y="0" width="210" height="297" fill="#ffffff" />

            <!-- MARCADOR CANTO SUPERIOR ESQUERDO (TL - Vazado / Assimétrico) -->
            <rect x="12" y="12" width="10" height="10" fill="#000000" />
            <rect x="15" y="15" width="4" height="4" fill="#ffffff" />

            <!-- MARCADOR CANTO SUPERIOR DIREITO (TR - Sólido) -->
            <rect x="188" y="12" width="10" height="10" fill="#000000" />

            <!-- MARCADOR CANTO INFERIOR DIREITO (BR - Sólido) -->
            <rect x="188" y="275" width="10" height="10" fill="#000000" />

            <!-- MARCADOR CANTO INFERIOR ESQUERDO (BL - Sólido) -->
            <rect x="12" y="275" width="10" height="10" fill="#000000" />

            <!-- QR CODE COM IDENTIFICADOR OPACO (UUID) -->
            @if(!empty($item['qrDataUri']))
                <image href="{{ $item['qrDataUri'] }}" x="28" y="14" width="26" height="26" />
            @endif
            <text x="41" y="42" font-size="2.4" font-weight="bold" text-anchor="middle" fill="#555555">
                {{ substr($item['folha']->uuid, 0, 8) }}
            </text>

            <!-- CABEÇALHO E IDENTIFICAÇÃO (Separado da grade para privacidade/recorte) -->
            <g transform="translate(58, 14)">
                <!-- Borda da área de identificação -->
                <rect x="0" y="0" width="126" height="26" fill="#fcfcfc" stroke="#333333" stroke-width="0.3" rx="1" />

                <text x="4" y="5" font-size="3.6" font-weight="bold" fill="#111111">
                    {{ e($item['escola_nome'] ?? 'TORRE 360 - SISTEMA ESCOLAR') }}
                </text>
                <text x="4" y="9.5" font-size="3.0" fill="#222222">
                    <tspan font-weight="bold">Avaliação:</tspan> {{ e($item['avaliacao']->categoria?->nome ?? 'Avaliação') }} — {{ e($item['avaliacao']->disciplina?->nome ?? 'Disciplina') }}
                </text>
                <text x="4" y="13.5" font-size="2.8" fill="#333333">
                    <tspan font-weight="bold">Turma:</tspan> {{ e($item['avaliacao']->turma?->nome ?? '-') }} |
                    <tspan font-weight="bold">Etapa:</tspan> {{ e($item['avaliacao']->etapaAvaliativa?->nome ?? '-') }} |
                    <tspan font-weight="bold">Data:</tspan> {{ $item['avaliacao']->data_prevista ? $item['avaliacao']->data_prevista->format('d/m/Y') : date('d/m/Y') }}
                </text>

                <!-- Linha divisória interna -->
                <line x1="0" y1="15.5" x2="126" y2="15.5" stroke="#cccccc" stroke-width="0.2" />

                <!-- Identificação do Aluno -->
                <text x="4" y="20" font-size="3.2" font-weight="bold" fill="#000000">
                    ALUNO(A): {{ e($item['aluno_nome'] ?? 'FOLHA AVULSA / EM BRANCO') }}
                </text>
                <text x="4" y="24" font-size="2.6" fill="#444444">
                    <tspan font-weight="bold">Matrícula:</tspan> {{ e($item['matricula_codigo'] ?? 'N/A') }} |
                    <tspan font-weight="bold">Professor:</tspan> {{ e($item['avaliacao']->professor?->nome ?? 'Não atribuído') }}
                </text>
            </g>

            <!-- INSTRUÇÕES DE PREENCHIMENTO -->
            <g transform="translate(22, 45)">
                <rect x="0" y="0" width="166" height="7" fill="#f8f9fa" stroke="#dddddd" stroke-width="0.2" rx="0.5" />
                <text x="3" y="4.5" font-size="2.4" fill="#333333">
                    <tspan font-weight="bold">Instruções:</tspan> Use caneta preta ou azul. Preencha toda a bolha. Não dobre, não rasure e não use corretivo.
                </text>
                <!-- Exemplos visuais -->
                <text x="135" y="4.5" font-size="2.3" fill="#2e7d32">Certo: [●]</text>
                <text x="150" y="4.5" font-size="2.3" fill="#c62828">Errado: [✗] [✔] [◐]</text>
            </g>

            <!-- GRADE DE RESPOSTAS (BOLHAS) -->
            @php
                $layout = $item['layout'];
                $coordenadas = $layout->coordenadasBolhas();
                $qPorCol = $layout->questoesPorColuna();
                $cols = $layout->numeroColunas();
            @endphp

            @foreach($coordenadas as $numero => $alternativas)
                @php
                    $primeiraBolha = reset($alternativas);
                    $xNumero = $primeiraBolha['x'] - 6.0;
                    $yLinha = $primeiraBolha['y'];
                @endphp

                <!-- Rótulo do número da questão -->
                <text x="{{ $xNumero }}" y="{{ $yLinha + 1.0 }}"
                      font-size="3.2"
                      font-weight="bold"
                      fill="#111111"
                      text-anchor="end">
                    {{ str_pad((string) $numero, 2, '0', STR_PAD_LEFT) }}
                </text>

                <!-- Bolhas das alternativas -->
                @foreach($alternativas as $letra => $ponto)
                    <g>
                        <!-- Círculo da bolha com raio exato -->
                        <circle cx="{{ $ponto['x'] }}"
                                cy="{{ $ponto['y'] }}"
                                r="{{ $ponto['raio'] }}"
                                fill="#ffffff"
                                stroke="#111111"
                                stroke-width="0.35" />

                        <!-- Letra da alternativa centralizada -->
                        <text x="{{ $ponto['x'] }}"
                              y="{{ $ponto['y'] + 0.9 }}"
                              font-size="2.6"
                              font-weight="bold"
                              fill="#222222"
                              text-anchor="middle">
                            {{ $letra }}
                        </text>
                    </g>
                @endforeach
            @endforeach

            <!-- RODAPÉ DA FOLHA -->
            <text x="105" y="285" font-size="2.4" fill="#777777" text-anchor="middle">
                Torre360 OMR • Imprimir em tamanho real 100% (não ajustar à página) • Folha UUID: {{ $item['folha']->uuid }}
            </text>
        </svg>
    </div>
@endforeach
</body>
</html>
