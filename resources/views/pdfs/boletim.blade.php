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

        @page {
            margin: 30px 30px 60px 30px;
        }

        .page-footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 20px;
            text-align: right;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 5px;
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

        .grades-table th,
        .grades-table td {
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

        .text-danger {
            color: #b91c1c;
        }

        .text-gray {
            color: #6b7280;
        }

        .line-through {
            text-decoration: line-through;
            opacity: 0.6;
        }
    </style>
</head>

<body>

    @php
        $instituicao = $matricula->turma?->serie?->curso?->unidade?->instituicaoEnsino;
        $logoBase64 = null;
        if ($instituicao?->logo) {
            try {
                $disk = config('filament.default_filesystem_disk', 'local');
                if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($instituicao->logo)) {
                    $logoContent = \Illuminate\Support\Facades\Storage::disk($disk)->get($instituicao->logo);
                    $mimeType = \Illuminate\Support\Facades\Storage::disk($disk)->mimeType($instituicao->logo);
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoContent);
                } else if (\Illuminate\Support\Facades\Storage::disk('public')->exists($instituicao->logo)) {
                    $logoContent = \Illuminate\Support\Facades\Storage::disk('public')->get($instituicao->logo);
                    $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($instituicao->logo);
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoContent);
                }
            } catch (\Exception $e) {
                // Erro ao carregar logo
            }
        }
    @endphp

    <div class="header">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 80px; border: none; vertical-align: middle;">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" style="width: 80px; height: auto;">
                    @else
                        <div
                            style="width: 80px; height: 80px; background-color: #eee; border-radius: 5px; text-align: center; line-height: 80px; color: #999;">
                            T360</div>
                    @endif
                </td>
                <td style="border: none; vertical-align: middle; padding-left: 20px; text-align: left;">
                    <h1 style="margin: 0; font-size: 20px;">
                        {{ $instituicao?->nome ?? $matricula->turma?->serie?->curso?->unidade?->nome ?? 'Torre360' }}
                    </h1>
                    <p style="margin: 3px 0; font-size: 11px;">Unidade:
                        {{ $matricula->turma?->serie?->curso?->unidade?->nome ?? '-' }}
                    </p>
                    <p style="margin: 0; font-size: 13px; font-weight: bold; color: #666;">Boletim Escolar</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="info-label">Aluno:</td>
                <td>{{ $matricula->pessoa?->nome ?? '-' }}</td>
                <td class="info-label">Matrícula:</td>
                <td>{{ $matricula->id }}</td>
            </tr>
            <tr>
                <td class="info-label">Curso:</td>
                <td colspan="3">{{ $matricula->turma?->serie?->curso?->nome_externo ?? $matricula->turma?->serie?->curso?->nome_interno ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Turma / Série:</td>
                <td>{{ $matricula->turma?->nome ?? '-' }} / {{ $matricula->turma?->serie?->nome ?? '-' }}</td>
                <td class="info-label">Período Letivo:</td>
                <td>{{ $matricula->periodoLetivo?->ano ?? $matricula->turma?->periodoLetivo?->ano ?? now()->year }}</td>
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
                        <th>Média da etapa</th>
                        <th>Média da turma</th>
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
                                    $cor = ($valor !== null && !$isIgnorada) ? ($valor >= 7 ? '' : 'text-danger') : 'text-gray';
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
                                $corMf = $mf >= 7 ? '' : 'text-danger';
                            @endphp
                            <td class="{{ $corMf }}" style="font-weight: bold;">
                                {{ $mf !== null ? number_format($mf, 1, ',', '.') : '—' }}
                            </td>

                            @php
                                $mt = $linha['media_turma'];
                            @endphp
                            <td class="text-gray">
                                {{ $mt !== null ? number_format($mt, 1, ',', '.') : '—' }}
                            </td>

                            @php
                                $freq = $linha['frequencia'];
                                $corFreq = 'text-gray';
                                if ($freq !== null) {
                                    if ($freq >= 75)
                                        $corFreq = '';
                                    else
                                        $corFreq = 'text-danger';
                                }
                            @endphp
                            <td class="{{ $corFreq }}">
                                {{ $freq !== null ? number_format($freq, 1, ',', '.') . '%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(!empty($item['faltas_datas']))
                <div style="margin-top: 5px; font-size: 10px;">
                    <strong>Dias com falta:</strong>
                    {{ implode(', ', $item['faltas_datas']) }}
                </div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        <p><strong>Legenda:</strong></p>
        @php
            $todasCategorias = collect($etapas)->pluck('categorias')->flatten()->unique('id')->sortBy('ordem_boletim');
        @endphp
        <ul>
            @forelse ($todasCategorias as $cat)
                <li>{{ $cat->nome }}{{ $cat->descricao ? ': ' . $cat->descricao : '' }}</li>
            @empty
                <li style="color: #999;">(Não há categorias de avaliação registradas)</li>
            @endforelse
            <li>Riscado: Avaliação substituída por outra de maior valor.</li>
            <li>(·): Não se aplica ou componente sem avaliações nesta categoria.</li>
            <li>(—): Nota não lançada.</li>
            <li>Média da Etapa = 35%TB + 35%PB + 30%AQI, sem a nota de recuperação. </li>
            <li>Frequência mínima exigida: 75%.</li>
        </ul>

        <div style="margin-top: 60px; width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
            <div>Declaro ter recebido o Boletim Escolar</div>
            <div
                style="margin-top: 60px; border-top: 1px solid #000; width: 100%; text-align: center; padding-top: 5px;">
                Responsável
            </div>
        </div>

        <div class="page-footer">
            Emissão em {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>

</body>

</html>