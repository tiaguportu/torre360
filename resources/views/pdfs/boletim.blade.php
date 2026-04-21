<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Boletim Escolar</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .info-section {
            margin-bottom: 20px;
            width: 100%;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 0;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        .etapa-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .etapa-title {
            background-color: #f2f2f2;
            padding: 8px;
            font-size: 14px;
            font-weight: bold;
            border: 1px solid #ccc;
            margin-bottom: 10px;
            text-align: center;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .grades-table th, .grades-table td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
        }
        .grades-table th {
            background-color: #f9f9f9;
        }
        .disciplina-col {
            text-align: left !important;
            font-weight: bold;
            width: 30%;
        }
        .footer {
            margin-top: 50px;
            width: 100%;
        }
        .signatures {
            width: 100%;
            margin-top: 60px;
        }
        .signature-box {
            width: 45%;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            display: inline-block;
        }
        .spacer {
            width: 10%;
            display: inline-block;
        }
        .text-success { color: #15803d; }
        .text-danger { color: #b91c1c; }
        .text-gray { color: #6b7280; }
        .line-through { text-decoration: line-through; opacity: 0.6; }
    </style>
</head>
<body>

<div class="header">
    <h1>Torre360 - Sistema de Gestão Escolar</h1>
    <p>Boletim Escolar Oficial</p>
</div>

<div class="info-section">
    <table class="info-table">
        <tr>
            <td class="info-label">Aluno:</td>
            <td>{{ $matricula->pessoa->nome }}</td>
            <td class="info-label">Matrícula:</td>
            <td>{{ $matricula->id }}</td>
        </tr>
        <tr>
            <td class="info-label">Curso:</td>
            <td>{{ $matricula->turma->serie->curso->nome }}</td>
            <td class="info-label">Série:</td>
            <td>{{ $matricula->turma->serie->nome }}</td>
        </tr>
        <tr>
            <td class="info-label">Turma:</td>
            <td>{{ $matricula->turma->nome }}</td>
            <td class="info-label">Período Letivo:</td>
            <td>{{ $matricula->turma->periodoLetivo->ano }}</td>
        </tr>
        <tr>
            <td class="info-label">Data de Emissão:</td>
            <td colspan="3">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

@foreach ($etapas as $item)
    <div class="etapa-section">
        <div class="etapa-title">{{ $item['etapa']->nome }}</div>
        
        <table class="grades-table">
            <thead>
                <tr>
                    <th class="disciplina-col">Componente Curricular</th>
                    @foreach ($item['categorias'] as $cat)
                        <th>{{ $cat->nome }}</th>
                    @endforeach
                    <th>Média Etapa</th>
                    <th>Frequência</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($item['linhas'] as $linha)
                    <tr>
                        <td class="disciplina-col">{{ $linha['disciplina']->nome }}</td>
                        @foreach ($item['categorias'] as $cat)
                            @php
                                $d = $linha['categorias'][$cat->id];
                                $valor = $d['valor'];
                                $isIgnorada = $d['is_ignorada'];
                                $ausente = $d['ausente'];
                                $cor = ($valor !== null && !$isIgnorada) ? ($valor >= 7 ? 'text-success' : 'text-danger') : 'text-gray';
                            @endphp
                            <td class="{{ $cor }} {{ $isIgnorada ? 'line-through' : '' }}">
                                @if ($valor !== null)
                                    {{ number_format($valor, 1, ',', '.') }}
                                @else
                                    {{ $ausente ? '·' : '—' }}
                                @endif
                            </td>
                        @endforeach
                        
                        @php
                            $mf = $linha['media_final'];
                            $corMf = $mf >= 7 ? 'text-success' : 'text-danger';
                        @endphp
                        <td class="{{ $corMf }}" style="font-weight: bold;">
                            {{ $mf !== null ? number_format($mf, 1, ',', '.') : '—' }}
                        </td>
                        
                        @php
                            $freq = $linha['frequencia'];
                            $corFreq = 'text-gray';
                            if ($freq !== null) {
                                if ($freq >= 75) $corFreq = 'text-success';
                                elseif ($freq >= 50) $corFreq = 'warning';
                                else $corFreq = 'text-danger';
                            }
                        @endphp
                        <td class="{{ $corFreq }}">
                            {{ $freq !== null ? number_format($freq, 1, ',', '.') . '%' : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

<div class="footer">
    <p><strong>Legenda:</strong></p>
    <ul>
        <li>Riscado: Avaliação substituída por outra de maior valor.</li>
        <li>(·): Não se aplica ou componente sem avaliações nesta categoria.</li>
        <li>(—): Nota não lançada.</li>
        <li>Frequência mínima exigida: 75%.</li>
    </ul>

    <div class="signatures">
        <div class="signature-box">
            Secretaria Escolar
        </div>
        <div class="spacer"></div>
        <div class="signature-box">
            Direção / Coordenação
        </div>
    </div>
</div>

</body>
</html>
