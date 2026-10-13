<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>PEI - Adaptação Curricular Individual</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10.5px;
            line-height: 1.5;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        @page {
            margin: 35px 40px 45px 40px;
        }
        .header {
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-title {
            font-size: 15px;
            font-weight: bold;
            color: #5b21b6;
            text-transform: uppercase;
            margin: 0;
        }
        .header-sub {
            font-size: 9.5px;
            color: #4b5563;
            margin-top: 2px;
        }
        .confidencial-badge {
            background-color: #fee2e2;
            border: 1px solid #f87171;
            color: #991b1b;
            font-weight: bold;
            font-size: 9px;
            padding: 3px 8px;
            border-radius: 3px;
            display: inline-block;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .status-tarja {
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 12px;
            text-align: center;
        }
        .status-rascunho {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
        }
        .status-aprovado {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 4px;
            font-size: 10px;
        }
        .meta-label {
            font-weight: bold;
            color: #374151;
            width: 20%;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #5b21b6;
            background-color: #f5f3ff;
            border-left: 3px solid #7c3aed;
            padding: 3px 6px;
            margin: 10px 0 5px 0;
            text-transform: uppercase;
        }
        .content-box {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .sub-item-title {
            font-weight: bold;
            color: #374151;
            margin-top: 4px;
        }
        .aviso-legal {
            background-color: #f3f4f6;
            border: 1px dashed #9ca3af;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 8.5px;
            color: #4b5563;
            margin-top: 15px;
            line-height: 1.4;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #64748b;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">{{ e($escolaNome ?? 'Torre360 - Gestão Escolar') }}</div>
        <div class="header-sub">Coordenação Pedagógica & Núcleo de Educação Inclusiva (AEE)</div>
    </div>

    <div>
        <span class="confidencial-badge">DOCUMENTO PEDAGÓGICO CONFIDENCIAL — LGPD ART. 11 E LEI Nº 13.146/2015</span>
    </div>

    @if($adaptacao->status->value !== 'aprovado')
        <div class="status-tarja status-rascunho">
            RASCUNHO GERADO POR IA — PENDENTE DE VALIDAÇÃO PELO PROFESSOR REGENTE E COORDENAÇÃO PEDAGÓGICA
        </div>
    @else
        <div class="status-tarja status-aprovado">
            PLANO DE ADAPTAÇÃO INDIVIDUAL HOMOLOGADO E APROVADO
        </div>
    @endif

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Estudante:</td>
                <td><strong>{{ e($adaptacao->matricula?->pessoa?->nome ?? 'Estudante') }}</strong> (Matrícula: {{ e($adaptacao->matricula?->numero_matricula ?? '-') }})</td>
                <td class="meta-label">Turma:</td>
                <td>{{ e($adaptacao->planoAula?->turma?->nome ?? '-') }}</td>
            </tr>
            <tr>
                <td class="meta-label">Componente:</td>
                <td>{{ e($adaptacao->planoAula?->disciplina?->nome ?? '-') }}</td>
                <td class="meta-label">Data da Aula:</td>
                <td>{{ $adaptacao->planoAula?->data_prevista ? $adaptacao->planoAula->data_prevista->format('d/m/Y') : '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Status:</td>
                <td>{{ e($adaptacao->status->getLabel()) }}</td>
                <td class="meta-label">Revisão Sugerida:</td>
                <td>{{ e($conteudo['prazo_revisao_dias'] ?? 15) }} dias</td>
            </tr>
        </table>
    </div>

    <!-- 1. BARREIRAS DE APRENDIZAGEM -->
    <div class="section-title">1. Barreiras Identificadas para a Atividade</div>
    <div class="content-box">
        {{ e($conteudo['barreiras'] ?? 'Não informada.') }}
    </div>

    <!-- 2. ADAPTAÇÕES PRÁTICAS NA ATIVIDADE (DUA) -->
    <div class="section-title">2. Adaptações Práticas Curriculares (DUA)</div>
    <div class="content-box">
        @php $praticas = $conteudo['adaptacoes_praticas'] ?? []; @endphp
        <div class="sub-item-title">a) Instruções e Passo a Passo:</div>
        <div>{{ e($praticas['instrucoes_passo_a_passo'] ?? '-') }}</div>

        <div class="sub-item-title">b) Apoio Visual e Multissensorial:</div>
        <div>{{ e($praticas['apoio_visual'] ?? '-') }}</div>

        <div class="sub-item-title">c) Flexibilização Temporal (Tempo Estendido):</div>
        <div>{{ e($praticas['tempo_estendido'] ?? '-') }}</div>

        <div class="sub-item-title">d) Tecnologia Assistiva e Recursos de Acessibilidade:</div>
        <div>{{ e($praticas['recursos_acessibilidade'] ?? '-') }}</div>

        <div class="sub-item-title">e) Organização do Ambiente e Sala de Aula:</div>
        <div>{{ e($praticas['organizacao_ambiente'] ?? '-') }}</div>
    </div>

    <!-- 3. ADAPTAÇÃO DA AVALIAÇÃO -->
    <div class="section-title">3. Adaptação do Instrumento de Avaliação</div>
    <div class="content-box">
        {{ e($conteudo['adaptacao_avaliacao'] ?? 'Avaliação no mesmo formato regular com suporte visual.') }}
    </div>

    <!-- 4. ESTRATÉGIAS PARA O PROFESSOR -->
    <div class="section-title">4. Estratégias Mediadoras para o Professor Regente</div>
    <div class="content-box">
        {{ e($conteudo['estrategias_professor'] ?? '-') }}
    </div>

    <!-- 5. HABILIDADE ALVO E ACOMPANHAMENTO -->
    <div class="section-title">5. Habilidade Curricular Alvo e Indicadores de Sucesso</div>
    <div class="content-box">
        <div><strong>Habilidade Alvo da BNCC:</strong> {{ e($conteudo['habilidade_bncc_alvo'] ?? 'Mantida integralmente.') }}</div>
        @if(!empty($conteudo['profundidade_requer_validacao_aee']))
            <div style="color: #b45309; margin-top: 4px;">
                <strong>Atenção AEE:</strong> {{ e($conteudo['profundidade_requer_validacao_aee']) }}
            </div>
        @endif
        <div style="margin-top: 4px;"><strong>Indicadores de Evolução:</strong> {{ e($conteudo['indicadores_acompanhamento'] ?? '-') }}</div>
    </div>

    <!-- AVISO LEGAL ESTATUTÁRIO -->
    <div class="aviso-legal">
        <strong>Aviso Legal e Estatutário:</strong> {{ e($conteudo['aviso_legal'] ?? 'Documento elaborado em consonância com a Lei Brasileira de Inclusão (Lei nº 13.146/2015), princípios do Desenho Universal para a Aprendizagem (DUA) e Política Nacional de Educação Especial na Perspectiva da Educação Inclusiva (PNEEPEI/2008). Este documento constitui suporte metodológico de acessibilidade e NÃO substitui laudo médico, parecer multidisciplinar ou avaliação diagnóstica clínica.') }}
    </div>

    <div class="footer">
        Gerado pelo Torre360 em {{ date('d/m/Y \à\s H:i') }} | Gerado por: {{ e($adaptacao->geradoPor?->name ?? 'Sistema') }} | Status: {{ e($adaptacao->status->getLabel()) }}
    </div>
</body>
</html>
