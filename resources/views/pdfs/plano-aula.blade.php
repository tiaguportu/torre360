<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Plano de Aula - {{ e($planoAula->disciplina?->nome ?? 'Componente Curricular') }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        @page {
            margin: 35px 40px 45px 40px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin: 0;
        }
        .header-sub {
            font-size: 10px;
            color: #4b5563;
            margin-top: 2px;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 15px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 4px;
            font-size: 10.5px;
        }
        .meta-label {
            font-weight: bold;
            color: #374151;
            width: 18%;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e40af;
            background-color: #eff6ff;
            border-left: 3px solid #2563eb;
            padding: 4px 8px;
            margin: 12px 0 6px 0;
            text-transform: uppercase;
        }
        .table-cronograma {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10px;
        }
        .table-cronograma th, .table-cronograma td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }
        .table-cronograma th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #334155;
        }
        .list-item {
            margin-bottom: 4px;
        }
        .badge-habilidade {
            display: inline-block;
            background-color: #e0e7ff;
            color: #3730a3;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 9.5px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">{{ e($escolaNome ?? 'Torre360 - Gestão Escolar Integrada') }}</div>
        <div class="header-sub">Documento Pedagógico Oficial — Plano de Sequência Didática</div>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Componente:</td>
                <td>{{ e($planoAula->disciplina?->nome ?? '-') }}</td>
                <td class="meta-label">Turma / Série:</td>
                <td>{{ e($planoAula->turma?->nome ?? '-') }}</td>
            </tr>
            <tr>
                <td class="meta-label">Professor(a):</td>
                <td>{{ e($planoAula->professor?->nome ?? 'Não informado') }}</td>
                <td class="meta-label">Data Prevista:</td>
                <td>{{ $planoAula->data_prevista ? $planoAula->data_prevista->format('d/m/Y') : 'A definir' }}</td>
            </tr>
        </table>
    </div>

    <!-- 1. HABILIDADES DA BNCC -->
    <div class="section-title">1. Habilidades Alinhadas à BNCC</div>
    @if($planoAula->habilidades->isNotEmpty())
        @foreach($planoAula->habilidades as $hab)
            <div class="list-item">
                <span class="badge-habilidade">{{ e($hab->codigo) }}</span>
                <strong>{{ e($hab->nome) }}</strong> — {{ e($hab->descricao) }}
            </div>
        @endforeach
    @else
        <p style="color: #6b7280; font-style: italic;">Nenhuma habilidade formalmente vinculada.</p>
    @endif

    <!-- 2. OBJETIVOS DE APRENDIZAGEM -->
    <div class="section-title">2. Objetivos de Aprendizagem (Taxonomia de Bloom)</div>
    <div style="white-space: pre-line;">{{ e($planoAula->objetivos ?? 'Não especificado.') }}</div>

    <!-- 3. METODOLOGIA E CRONOGRAMA -->
    <div class="section-title">3. Metodologia de Ensino e Cronograma da Aula</div>
    <div style="white-space: pre-line;">{{ e($planoAula->metodologia ?? 'Não especificado.') }}</div>

    <!-- 4. RECURSOS DIDÁTICOS -->
    <div class="section-title">4. Recursos Didáticos e Tecnológicos</div>
    <div style="white-space: pre-line;">{{ e($planoAula->recursos ?? 'Não especificado.') }}</div>

    <!-- 5. AVALIAÇÃO FORMATIVA -->
    <div class="section-title">5. Avaliação da Aprendizagem</div>
    <div style="white-space: pre-line;">{{ e($planoAula->avaliacao ?? 'Não especificado.') }}</div>

    <div class="footer">
        Gerado pelo Torre360 em {{ date('d/m/Y \à\s H:i') }} | Planejamento Curricular Alinhado à BNCC
    </div>
</body>
</html>
