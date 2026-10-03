<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dossiê Estratégico IA - {{ $interessado->pessoa?->nome ?? 'Lead' }}</title>
    <style>
        @page {
            margin: 35px 40px 45px 40px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        /* Cabeçalho Oficial */
        .header {
            width: 100%;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-title {
            font-size: 16px;
            font-weight: bold;
            color: #312e81;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .brand-subtitle {
            font-size: 11px;
            font-weight: 600;
            color: #6366f1;
            margin: 2px 0 0 0;
        }

        .header-meta {
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }

        /* Título do Documento */
        .doc-title-box {
            background-color: #f8fafc;
            border-left: 4px solid #4f46e5;
            padding: 8px 12px;
            margin-bottom: 16px;
        }

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e1b4b;
            margin: 0;
        }

        .doc-subtitle {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }

        /* Grade de Informações do Lead */
        .meta-card {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #ffffff;
            margin-bottom: 16px;
            border-collapse: collapse;
        }

        .meta-card th {
            width: 25%;
            background-color: #f1f5f9;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }

        .meta-card td {
            padding: 6px 10px;
            font-size: 10.5px;
            color: #1e293b;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }

        .meta-card tr:last-child th,
        .meta-card tr:last-child td {
            border-bottom: none;
        }

        .meta-card td:last-child {
            border-right: none;
        }

        /* Card de Destaque IA */
        .ia-card {
            background-color: #faf5ff;
            border: 1px solid #d8b4fe;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .badge-temp {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-quente {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }

        .badge-morno {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .badge-frio {
            background-color: #e0f2fe;
            color: #075985;
            border: 1px solid #7dd3fc;
        }

        .ia-highlight {
            background-color: #ede9fe;
            border-left: 3px solid #7c3aed;
            padding: 6px 10px;
            margin-top: 8px;
            border-radius: 4px;
            font-size: 10.5px;
            color: #4c1d95;
        }

        /* Conteúdo do Dossiê */
        .dossie-content {
            font-size: 11px;
            line-height: 1.6;
            color: #1e293b;
        }

        .dossie-content h2,
        .dossie-content h3 {
            color: #312e81;
            margin-top: 14px;
            margin-bottom: 6px;
            font-size: 12.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            page-break-after: avoid;
        }

        .dossie-content h4 {
            color: #4338ca;
            margin-top: 10px;
            margin-bottom: 4px;
            font-size: 11.5px;
            page-break-after: avoid;
        }

        .dossie-content p {
            margin: 6px 0;
            text-align: justify;
        }

        .dossie-content ul,
        .dossie-content ol {
            margin: 6px 0 10px 18px;
            padding: 0;
        }

        .dossie-content li {
            margin-bottom: 4px;
        }

        .dossie-content strong {
            color: #0f172a;
        }

        .dossie-content blockquote {
            background-color: #f8fafc;
            border-left: 3px solid #6366f1;
            margin: 8px 0;
            padding: 6px 12px;
            color: #334155;
            font-style: italic;
        }

        /* Rodapé Fixo */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 24px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 9px;
            color: #94a3b8;
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            text-align: left;
        }

        .footer-right {
            display: table-cell;
            text-align: right;
        }

        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    {{-- Rodapé --}}
    <div class="footer">
        <div class="footer-left">
            Torre360 • Inteligência Comercial e Vendas Educacionais | Confidencial
        </div>
        <div class="footer-right">
            Página <span class="page-number"></span>
        </div>
    </div>

    {{-- Cabeçalho --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="vertical-align: middle;">
                    <h1 class="brand-title">Torre360 • Gestão Escolar</h1>
                    <div class="brand-subtitle">Módulo de Inteligência Comercial e Captação</div>
                </td>
                <td class="header-meta" style="vertical-align: middle;">
                    <div><strong>Data de Emissão:</strong> {{ now()->format('d/m/Y \à\s H:i') }}</div>
                    <div><strong>Protocolo:</strong> CRM-IA-{{ $interessado->id }}-{{ now()->format('ymd') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Título do Documento --}}
    <div class="doc-title-box">
        <h2 class="doc-title">✨ Dossiê Estratégico do Lead (Análise com IA)</h2>
        <div class="doc-subtitle">
            Relatório consultivo de maturidade, perfil familiar, dores e roteiro de abordagem comercial.
        </div>
    </div>

    {{-- Dados Cadastrais do Lead --}}
    <table class="meta-card">
        <tr>
            <th>Responsável:</th>
            <td><strong>{{ $interessado->pessoa?->nome ?? 'Não informado' }}</strong></td>
            <th>Telefone:</th>
            <td>{{ $interessado->pessoa?->telefone ?? 'Não informado' }}</td>
        </tr>
        <tr>
            <th>E-mail:</th>
            <td>{{ $interessado->pessoa?->email ?? 'Não informado' }}</td>
            <th>Origem / Canal:</th>
            <td>{{ $interessado->origem?->nome ?? 'Direto' }} {{ $interessado->campanha ? '('.$interessado->campanha->nome.')' : '' }}</td>
        </tr>
        <tr>
            <th>Consultor Responsável:</th>
            <td>{{ $interessado->usuario?->name ?? 'Não atribuído' }}</td>
            <th>Etapa no Funil:</th>
            <td><strong>{{ $interessado->status?->nome ?? 'Novo' }}</strong></td>
        </tr>
        <tr>
            <th>Aluno(s) / Dependentes:</th>
            <td colspan="3">
                @if ($interessado->dependentes->isNotEmpty())
                    @foreach ($interessado->dependentes as $dep)
                        <span style="display: inline-block; margin-right: 12px;">
                            • <strong>{{ $dep->nome_crianca }}</strong>
                            {{ $dep->serie ? '— Pretende: '.$dep->serie->nome : '' }}
                            {{ filled($dep->data_nascimento) ? '('.(\Illuminate\Support\Carbon::parse($dep->data_nascimento)->format('d/m/Y')).')' : '' }}
                        </span>
                    @endforeach
                @else
                    Nenhum dependente cadastrado ainda.
                @endif
            </td>
        </tr>
    </table>

    {{-- Destaques Executivos da IA --}}
    <div class="ia-card">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
            <tr>
                <td style="font-size: 11px; font-weight: bold; color: #581c87; text-transform: uppercase;">
                    Termômetro Comercial IA
                </td>
                <td style="text-align: right;">
                    @php
                        $temp = $dossie['temperatura_sugerida'] ?? 'morno';
                    @endphp
                    @if ($temp === 'quente')
                        <span class="badge-temp badge-quente">🔥 Quente (Alta Probabilidade)</span>
                    @elseif ($temp === 'morno')
                        <span class="badge-temp badge-morno">🟡 Morno (Em Avaliação)</span>
                    @else
                        <span class="badge-temp badge-frio">🔵 Frio (Sondagem Inicial)</span>
                    @endif
                </td>
            </tr>
        </table>

        @if (!empty($dossie['resumo_executivo']))
            <p style="margin: 4px 0 6px 0; font-size: 11px; color: #1e1b4b;">
                <strong>💡 Síntese:</strong> {{ $dossie['resumo_executivo'] }}
            </p>
        @endif

        @if (!empty($dossie['proxima_acao_sugerida']))
            <div class="ia-highlight">
                <strong>🚀 Próxima Ação Recomendada:</strong> {{ $dossie['proxima_acao_sugerida'] }}
            </div>
        @endif
    </div>

    {{-- Corpo do Dossiê Estratégico (Convertido de Markdown para HTML) --}}
    <div class="dossie-content">
        {!! $dossieHtml !!}
    </div>
</body>
</html>
