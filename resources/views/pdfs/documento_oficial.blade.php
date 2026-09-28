<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>{{ $template->nome }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        @page {
            margin: 40px 45px 50px 45px;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 15px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-logo {
            width: 90px;
            vertical-align: middle;
            text-align: left;
        }

        .header-info {
            vertical-align: middle;
            text-align: center;
            padding-right: 45px;
        }

        .header-title {
            font-size: 17px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
            text-transform: uppercase;
        }

        .header-subtitle {
            font-size: 11px;
            color: #4b5563;
            margin: 3px 0 0 0;
        }

        .doc-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #111827;
            margin: 30px 0 25px 0;
            text-decoration: underline;
        }

        .doc-body {
            text-align: justify;
            font-size: 13px;
            line-height: 1.8;
            margin-bottom: 35px;
        }

        .doc-date {
            text-align: right;
            margin-top: 30px;
            margin-bottom: 40px;
            font-size: 13px;
        }

        .signatures {
            margin-top: 40px;
            width: 100%;
            text-align: center;
        }

        .signature-box {
            display: inline-block;
            width: 320px;
            border-top: 1px solid #374151;
            padding-top: 6px;
            font-size: 12px;
            font-weight: bold;
            color: #111827;
        }

        .signature-sub {
            font-size: 10px;
            font-weight: normal;
            color: #6b7280;
        }

        /* Caixa de Autenticidade Digital com QR Code */
        .auth-box {
            margin-top: 50px;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            background-color: #f9fafb;
            padding: 10px 15px;
            page-break-inside: avoid;
        }

        .auth-table {
            width: 100%;
            border-collapse: collapse;
        }

        .auth-qr {
            width: 80px;
            vertical-align: middle;
            text-align: center;
        }

        .auth-info {
            vertical-align: middle;
            padding-left: 15px;
            font-size: 10px;
            color: #374151;
            line-height: 1.4;
        }

        .auth-badge {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: bold;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .auth-code {
            font-family: monospace;
            font-size: 11px;
            font-weight: bold;
            color: #111827;
        }

        .page-footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 20px;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
    </style>
</head>

<body>

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" style="max-width: 85px; max-height: 85px;">
                    @else
                        <div style="width: 75px; height: 75px; background-color: #e5e7eb; border-radius: 6px; text-align: center; line-height: 75px; font-weight: bold; color: #4b5563;">
                            TORRE360
                        </div>
                    @endif
                </td>
                <td class="header-info">
                    <div class="header-title">{{ $unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? 'TORRE360 EDUCAÇÃO' }}</div>
                    <div class="header-subtitle">
                        Unidade: {{ $unidade?->nome ?? '-' }} 
                        @if ($unidade?->cnpj) | CNPJ: {{ $unidade->cnpj }} @endif
                    </div>
                    @if ($unidade?->endereco)
                        <div class="header-subtitle">
                            {{ $unidade->endereco->logradouro ?? '' }}, {{ $unidade->endereco->numero ?? '' }} - {{ $unidade->endereco->bairro ?? '' }} - {{ $unidade->endereco->cidade?->nome ?? '' }}/{{ $unidade->endereco->cidade?->estado?->sigla ?? '' }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">
        {{ $template->nome }}
    </div>

    <div class="doc-body">
        {!! $conteudo !!}
    </div>

    <div class="doc-date">
        {{ $cidade }}, {{ now()->translatedFormat('d \d\e F \d\e Y') }}.
    </div>

    <div class="signatures">
        <div class="signature-box">
            Secretaria Escolar
            <div class="signature-sub">{{ $unidade?->nome ?? 'Instituição de Ensino' }}</div>
        </div>
    </div>

    <!-- Carimbo de Autenticidade Digital -->
    <div class="auth-box">
        <table class="auth-table">
            <tr>
                <td class="auth-qr">
                    @if ($qrCodeDataUri)
                        <img src="{{ $qrCodeDataUri }}" style="width: 75px; height: 75px;" alt="QR Code">
                    @endif
                </td>
                <td class="auth-info">
                    <span class="auth-badge">Documento Autenticado Digitalmente</span><br>
                    <strong>Protocolo:</strong> {{ $solicitacao->protocolo }} | 
                    <strong>Código de Verificação:</strong> <span class="auth-code">{{ $solicitacao->codigo_verificacao }}</span><br>
                    <strong>Data de Emissão:</strong> {{ $solicitacao->data_emissao?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s') }}
                    @if ($solicitacao->data_validade)
                        | <strong>Válido até:</strong> {{ $solicitacao->data_validade->format('d/m/Y') }}
                    @endif
                    <br>
                    <span style="color: #6b7280;">Para conferir a autenticidade e integridade deste documento, aponte a câmera do celular para o QR Code acima ou acesse o endereço <u>{{ url('/validar-documento') }}</u> e digite o código de verificação.</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="page-footer">
        {{ $unidade?->nome ?? 'Torre360' }} — Documento emitido eletronicamente via Sistema Torre360 — Protocolo: {{ $solicitacao->protocolo }}
    </div>

</body>

</html>
