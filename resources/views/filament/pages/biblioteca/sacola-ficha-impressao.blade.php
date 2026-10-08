<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Sacola de Leitura - {{ $sacola->codigo }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
            background: #fff;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .title h1 {
            font-size: 20px;
            margin: 0 0 4px 0;
            color: #1e3a8a;
        }
        .title p {
            margin: 0;
            font-size: 13px;
            color: #4b5563;
        }
        .barcode-box {
            text-align: right;
        }
        .barcode-box svg {
            display: inline-block;
            max-width: 180px;
            height: auto;
        }
        .barcode-label {
            font-family: monospace;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 1px;
            color: #111827;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }
        .info-val {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            border-bottom: 2px solid #cbd5e1;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            vertical-align: middle;
        }
        .checkbox-box {
            width: 18px;
            height: 18px;
            border: 1.5px solid #94a3b8;
            border-radius: 4px;
            display: inline-block;
        }
        .book-cover {
            width: 32px;
            height: 44px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
        }
        .no-cover {
            width: 32px;
            height: 44px;
            background: #e2e8f0;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        .tag-pendente {
            background: #fef3c7;
            color: #92400e;
        }
        .tag-devolvido {
            background: #d1fae5;
            color: #065f46;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 40px;
            padding-top: 20px;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            text-align: center;
            padding-top: 6px;
            font-size: 11px;
            color: #475569;
        }
        .no-print-bar {
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: -20px -20px 20px -20px;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            .no-print-bar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <span>Ficha de Acompanhamento da Sacola de Leitura ({{ $sacola->codigo }})</span>
        <button class="btn-print" onclick="window.print()">Imprimir Ficha</button>
    </div>

    <div class="header">
        <div class="title">
            <h1>Torre360 • Biblioteca Escolar</h1>
            <p><strong>{{ $sacola->titulo }}</strong></p>
        </div>
        <div class="barcode-box">
            {!! $barcodeSvg !!}
            <div class="barcode-label">{{ $sacola->codigo }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Turma Destino</span>
            <span class="info-val">{{ $sacola->turma?->nome ?? 'Não informada' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Professor(a) / Resp.</span>
            <span class="info-val">{{ $sacola->responsavel?->nome ?? 'Não informado' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Data de Retirada</span>
            <span class="info-val">{{ $sacola->data_retirada?->format('d/m/Y') }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Devolução Prevista</span>
            <span class="info-val">{{ $sacola->data_prevista_devolucao?->format('d/m/Y') }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">Conf.</th>
                <th style="width: 45px; text-align: center;">Capa</th>
                <th>Título da Obra</th>
                <th>Autor</th>
                <th>Código / ISBN</th>
                <th style="width: 80px; text-align: center;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sacola->itens as $index => $item)
                <tr>
                    <td style="text-align: center;">
                        <div class="checkbox-box"></div>
                    </td>
                    <td style="text-align: center;">
                        @if($item->livro->capa)
                            <img src="{{ $item->livro->capa_url }}" class="book-cover" alt="Capa" />
                        @else
                            <div class="no-cover">Sem capa</div>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $item->livro->titulo }}</strong>
                    </td>
                    <td>{{ $item->livro->autor ?: '—' }}</td>
                    <td style="font-family: monospace;">{{ $item->livro->codigo ?: $item->livro->isbn }}</td>
                    <td style="text-align: center;">
                        @if($item->devolvido)
                            <span class="tag tag-devolvido">Devolvido</span>
                        @else
                            <span class="tag tag-pendente">Na Sacola</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #94a3b8; padding: 20px;">Nenhum livro vinculado a esta sacola.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div class="sig-line">
            Assinatura do(a) Professor(a) Regente
        </div>
        <div class="sig-line">
            Biblioteca / Atendimento Escolar
        </div>
    </div>
</body>
</html>
