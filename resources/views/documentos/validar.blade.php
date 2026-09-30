<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Documento Oficial — Torre360</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 650px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #243468 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .header p {
            font-size: 13px;
            color: #cbd5e1;
        }

        .content {
            padding: 30px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            text-transform: uppercase;
        }

        .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-search {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-search:hover {
            background-color: #1d4ed8;
        }

        .status-card {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .status-card.success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .status-card.danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
        }

        .status-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .status-card.success .status-icon {
            background-color: #22c55e;
            color: #ffffff;
        }

        .status-card.danger .status-icon {
            background-color: #ef4444;
            color: #ffffff;
        }

        .status-text h2 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .status-card.success .status-text h2 {
            color: #15803d;
        }

        .status-card.danger .status-text h2 {
            color: #b91c1c;
        }

        .status-text p {
            font-size: 13px;
            color: #475569;
            line-height: 1.5;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .details-table td {
            padding: 12px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        .details-table tr:last-child td {
            border-bottom: none;
        }

        .details-label {
            width: 35%;
            font-weight: 600;
            color: #64748b;
        }

        .details-value {
            color: #0f172a;
            font-weight: 500;
        }

        .badge-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background-color: #e2e8f0;
            color: #334155;
        }

        .badge-tag.valid {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-tag.expired {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <h1>Portal de Validação de Documentos</h1>
            <p>Conferência oficial de autenticidade e validade documental</p>
        </div>

        <div class="content">
            <form action="{{ url('/validar-documento') }}" method="GET" class="search-form">
                <input type="text" name="codigo" class="search-input" placeholder="Digite o código verificador (ex: TR36-XXXX-XXXX-XXXX)" value="{{ $codigoBusca ?? '' }}" required>
                <button type="submit" class="btn-search">Verificar</button>
            </form>

            @if ($buscou)
                @if ($documento && $documento->isValido())
                    @php
                        $aluno = $documento->matricula?->pessoa;
                        $turma = $documento->matricula?->turma;
                        $unidade = $turma?->serie?->curso?->unidade;
                    @endphp

                    <div class="status-card success">
                        <div class="status-icon">✓</div>
                        <div class="status-text">
                            <h2>Documento Autêntico e Válido</h2>
                            <p>O documento consultado foi emitido oficialmente pela instituição e possui registro ativo de autenticidade no sistema Torre360.</p>
                        </div>
                    </div>

                    <table class="details-table">
                        <tr>
                            <td class="details-label">Tipo de Documento:</td>
                            <td class="details-value"><strong>{{ $documento->templateDocumento?->nome ?? 'Declaração Escolar' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="details-label">Estudante:</td>
                            <td class="details-value">
                                <strong>{{ $nomeAlunoMascarado ?? '-' }}</strong>
                                <span style="display: block; color: #64748b; font-size: 11px; margin-top: 2px;">
                                    (Identificação protegida em conformidade com a LGPD)
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="details-label">Instituição / Unidade:</td>
                            <td class="details-value">{{ $unidade?->nome ?? 'Torre360' }}</td>
                        </tr>
                        <tr>
                            <td class="details-label">Turma / Série:</td>
                            <td class="details-value">{{ $turma?->nome ?? '-' }} / {{ $turma?->serie?->nome ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="details-label">Protocolo de Emissão:</td>
                            <td class="details-value"><code>{{ $documento->protocolo }}</code></td>
                        </tr>
                        <tr>
                            <td class="details-label">Código de Autenticidade:</td>
                            <td class="details-value"><code>{{ $documento->codigo_verificacao }}</code></td>
                        </tr>
                        <tr>
                            <td class="details-label">Data de Emissão:</td>
                            <td class="details-value">{{ $documento->data_emissao?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="details-label">Validade:</td>
                            <td class="details-value">
                                @if ($documento->data_validade)
                                    {{ $documento->data_validade->format('d/m/Y') }}
                                    <span class="badge-tag valid">Regular</span>
                                @else
                                    <span class="badge-tag">Sem data limite</span>
                                @endif
                            </td>
                        </tr>
                    </table>

                @elseif ($documento && ! $documento->isValido())
                    <div class="status-card danger">
                        <div class="status-icon">!</div>
                        <div class="status-text">
                            <h2>Documento Expirado ou Não Disponível</h2>
                            <p>O documento foi localizado, porém seu prazo de validade expirou em <strong>{{ $documento->data_validade?->format('d/m/Y') }}</strong> ou a solicitação se encontra com status <strong>{{ $documento->status?->getLabel() ?? $documento->status }}</strong>.</p>
                        </div>
                    </div>

                @else
                    <div class="status-card danger">
                        <div class="status-icon">✕</div>
                        <div class="status-text">
                            <h2>Documento Não Encontrado</h2>
                            <p>Não foi localizado nenhum documento oficial com o código informado (<strong>{{ $codigoBusca }}</strong>). Verifique a digitação do código verificador ou aponte a câmera para o QR Code impresso no documento original.</p>
                        </div>
                    </div>
                @endif
            @else
                <div style="text-align: center; padding: 20px 0; color: #64748b; font-size: 14px;">
                    Digite o código de verificação impresso no carimbo digital do documento ou aponte a câmera para o QR Code para atestar sua veracidade.
                </div>
            @endif
        </div>

        <div class="footer">
            Torre360 Gestão Escolar &bull; Validação de Autenticidade Digital
        </div>
    </div>

</body>

</html>
