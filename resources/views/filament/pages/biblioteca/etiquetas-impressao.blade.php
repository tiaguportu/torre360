<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressão de Etiquetas - Biblioteca Torre360</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background-color: #f3f4f6;
            color: #111827;
            padding: 20px;
        }
        .no-print {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px 24px;
            max-width: 900px;
            margin: 0 auto 24px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .btn {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #4b5563;
        }
        .btn-secondary:hover {
            background-color: #374151;
        }
        .grid-etiquetas {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8mm;
            max-width: 900px;
            margin: 0 auto;
        }
        .etiqueta {
            background: #ffffff;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 52mm;
            page-break-inside: avoid;
            text-align: center;
        }
        .etiqueta-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .escola-nome {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 700;
            color: #6b7280;
            letter-spacing: 0.5px;
        }
        .titulo-livro {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
            max-height: 2.4em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .autor-livro {
            font-size: 10px;
            color: #4b5563;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .barcode-container {
            margin: 6px 0;
            padding: 2px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .codigo-texto {
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            font-weight: 700;
            color: #111827;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .etiqueta-footer {
            border-top: 1px dotted #e5e7eb;
            padding-top: 4px;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #6b7280;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .grid-etiquetas {
                max-width: 100%;
                gap: 5mm;
            }
            .etiqueta {
                border: 1px solid #d1d5db;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div>
            <h2 style="font-size: 16px; font-weight: 700; margin-bottom: 4px;">Impressão de Etiquetas da Biblioteca</h2>
            <p style="font-size: 13px; color: #6b7280;">Total de etiquetas prontas para impressão: <strong>{{ count($livros) }}</strong></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Imprimir Agora
            </button>
            <button onclick="window.close()" class="btn btn-secondary">Fechar</button>
        </div>
    </div>

    <div class="grid-etiquetas">
        @foreach($livros as $item)
            @for($i = 0; $i < ($item['copias'] ?? 1); $i++)
                <div class="etiqueta">
                    <div class="etiqueta-header">
                        <div class="escola-nome">Torre360 • Biblioteca Escolar</div>
                        <div class="titulo-livro" title="{{ $item['livro']->titulo }}">{{ $item['livro']->titulo }}</div>
                        <div class="autor-livro">{{ $item['livro']->autor }}</div>
                    </div>

                    <div>
                        <div class="barcode-container">
                            {!! $item['barcodeSvg'] !!}
                        </div>
                        <div class="codigo-texto">{{ $item['livro']->codigo ?: ('LIV-' . str_pad($item['livro']->id, 5, '0', STR_PAD_LEFT)) }}</div>
                    </div>

                    <div class="etiqueta-footer">
                        <span>ISBN: {{ $item['livro']->isbn ?: 'N/A' }}</span>
                        <span>{{ $item['livro']->faixa_etaria ?: ($item['livro']->categoria ?: 'Acervo Geral') }}</span>
                    </div>
                </div>
            @endfor
        @endforeach
    </div>

    <script>
        // Opção para abrir impressão automaticamente se solicitado
        if (window.location.search.includes('autoprint=1')) {
            window.addEventListener('load', () => setTimeout(() => window.print(), 500));
        }
    </script>
</body>
</html>
