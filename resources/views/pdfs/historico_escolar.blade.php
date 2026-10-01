<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico Escolar - {{ $aluno?->nome ?? 'Estudante' }}</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
            size: a4 landscape;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5pt;
            line-height: 1.2;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border-bottom: 1.5px solid #2d3748;
            padding-bottom: 4px;
        }

        .header-logo {
            width: 70px;
            text-align: center;
            vertical-align: middle;
        }

        .header-logo img {
            max-height: 55px;
            max-width: 65px;
        }

        .header-title-box {
            text-align: center;
            vertical-align: middle;
        }

        .header-title-box h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            color: #1a202c;
            letter-spacing: 0.5px;
        }

        .header-title-box h2 {
            font-size: 10.5pt;
            font-weight: bold;
            margin: 0 0 3px 0;
            text-transform: uppercase;
            color: #2b6cb0;
        }

        .header-title-box p {
            font-size: 7pt;
            margin: 1px 0;
            color: #4a5568;
        }

        /* Quadro de Identificação do Aluno */
        .aluno-box {
            width: 100%;
            border: 1px solid #718096;
            border-collapse: collapse;
            margin-bottom: 8px;
            background-color: #f8fafc;
        }

        .aluno-box td {
            padding: 3px 5px;
            font-size: 7.5pt;
            border: 1px solid #cbd5e0;
        }

        .label {
            font-weight: bold;
            color: #2d3748;
            text-transform: uppercase;
            font-size: 6.8pt;
        }

        .value {
            color: #1a202c;
            font-size: 8pt;
        }

        /* Tabela Matricial de Desempenho Curricular */
        .matriz-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .matriz-table th,
        .matriz-table td {
            border: 1px solid #4a5568;
            padding: 2.5px 3px;
            text-align: center;
            font-size: 7.5pt;
        }

        .matriz-table th.col-disciplina {
            text-align: left;
            padding-left: 6px;
            background-color: #edf2f7;
            font-weight: bold;
            width: 22%;
        }

        .matriz-table th.col-ano {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: 7.8pt;
            color: #1a202c;
        }

        .matriz-table th.subcol {
            background-color: #f7fafc;
            font-size: 6.8pt;
            font-weight: bold;
            color: #4a5568;
        }

        .matriz-table td.disciplina-nome {
            text-align: left;
            padding-left: 10px;
            font-size: 7.5pt;
        }

        .matriz-table tr.area-header td {
            background-color: #e2e8f0;
            font-weight: bold;
            text-align: left;
            padding-left: 5px;
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #2c5282;
            letter-spacing: 0.3px;
        }

        .matriz-table tr.totais-row td {
            font-weight: bold;
            background-color: #edf2f7;
            font-size: 7.2pt;
        }

        .reprovado {
            color: #c53030;
            font-weight: bold;
        }

        /* Quadro de Estudos Realizados e Observações */
        .bottom-container {
            width: 100%;
            margin-top: 4px;
        }

        .bottom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bottom-table td {
            vertical-align: top;
            padding: 0 4px;
        }

        .section-box {
            border: 1px solid #718096;
            padding: 4px 6px;
            font-size: 7pt;
            min-height: 58px;
            background-color: #ffffff;
        }

        .section-box h3 {
            margin: 0 0 3px 0;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #2b6cb0;
            border-bottom: 1px solid #cbd5e0;
            padding-bottom: 2px;
        }

        .estudos-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.8pt;
        }

        .estudos-table th,
        .estudos-table td {
            border: 1px solid #cbd5e0;
            padding: 1.5px 3px;
            text-align: left;
        }

        .estudos-table th {
            background-color: #f7fafc;
            font-weight: bold;
        }

        /* Rodapé de Assinaturas e Autenticidade */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .footer-table td {
            text-align: center;
            vertical-align: bottom;
            font-size: 7pt;
        }

        .signature-line {
            border-top: 1px solid #2d3748;
            width: 80%;
            margin: 0 auto 3px auto;
            padding-top: 3px;
            font-weight: bold;
        }

        .qr-box {
            width: 110px;
            text-align: center;
            font-size: 6pt;
            color: #4a5568;
        }

        .qr-box img {
            width: 50px;
            height: 50px;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

    <!-- CABEÇALHO OFICIAL DA INSTITUIÇÃO -->
    <table class="header-table">
        <tr>
            <td class="header-logo">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo">
                @endif
            </td>
            <td class="header-title-box">
                <h1>{{ $instituicao?->nome ?? $unidade?->nome ?? config('app.name', 'TORRE DE MARFIM') }}</h1>
                <h2>
                    HISTÓRICO ESCOLAR - {{ mb_strtoupper($historico->curso?->nome_externo ?? $historico->curso?->nome_interno ?? 'EDUCAÇÃO BÁSICA') }}
                </h2>
                <p>
                    @if($unidade?->cnpj) <strong>CNPJ:</strong> {{ $unidade->cnpj }} &nbsp;|&nbsp; @endif
                    @if($unidade?->codigo_inep) <strong>Cód. INEP:</strong> {{ $unidade->codigo_inep }} &nbsp;|&nbsp; @endif
                    <strong>Endereço:</strong> {{ $unidade?->endereco?->logradouro }}, {{ $unidade?->endereco?->numero }} - {{ $unidade?->endereco?->bairro }} - {{ $unidade?->endereco?->cidade?->nome }}/{{ $unidade?->endereco?->cidade?->estado?->sigla }}
                </p>
                <p style="font-size: 6.8pt; color: #718096;">
                    Documento Oficial expedido em conformidade com a Lei Federal nº 9.394/1996 (LDB) e a Base Nacional Comum Curricular (BNCC).
                </p>
            </td>
            <td style="width: 70px; text-align: right; vertical-align: top; font-size: 6.5pt; color: #718096;">
                <strong>CÓD. AUTENTICIDADE:</strong><br>
                <span style="font-family: monospace; font-size: 7.5pt; color: #2d3748;">{{ $historico->codigo_autenticidade }}</span>
            </td>
        </tr>
    </table>

    <!-- DADOS DE IDENTIFICAÇÃO DO EDUCANDO -->
    <table class="aluno-box">
        <tr>
            <td colspan="3">
                <span class="label">Nome do(a) Aluno(a):</span><br>
                <span class="value" style="font-size: 9pt; font-weight: bold;">{{ $aluno?->nome ?? '-' }}</span>
            </td>
            <td>
                <span class="label">Data de Nascimento:</span><br>
                <span class="value">{{ $aluno?->data_nascimento ? \Carbon\Carbon::parse($aluno->data_nascimento)->format('d/m/Y') : '-' }}</span>
            </td>
            <td>
                <span class="label">Naturalidade / UF:</span><br>
                <span class="value">{{ $aluno?->naturalidade?->nome ?? '-' }} / {{ $aluno?->naturalidade?->estado?->sigla ?? '-' }}</span>
            </td>
            <td>
                <span class="label">Nacionalidade:</span><br>
                <span class="value">{{ $aluno?->nacionalidade?->nome ?? 'Brasileira' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Filiação (Mãe):</span><br>
                <span class="value">{{ $nomeMae }}</span>
            </td>
            <td colspan="2">
                <span class="label">Filiação (Pai):</span><br>
                <span class="value">{{ $nomePai }}</span>
            </td>
            <td>
                <span class="label">RG / Identidade:</span><br>
                <span class="value">{{ $aluno?->identidade ?? 'Não informado' }}</span>
            </td>
            <td>
                <span class="label">CPF:</span><br>
                <span class="value">{{ $aluno?->cpf ? (strlen($aluno->cpf) === 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $aluno->cpf) : $aluno->cpf) : 'Não informado' }}</span>
            </td>
        </tr>
    </table>

    <!-- TABELA MATRICIAL MULTI-ANO DE DESEMPENHO CURRICULAR -->
    @php
        $anos = $matriz['anos'];
        $areas = $matriz['areas'];
        $totaisAnos = $matriz['totais_anos'];
        $totalAnosCount = $anos->count();
    @endphp

    <table class="matriz-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-disciplina">ÁREAS DE CONHECIMENTO E COMPONENTES CURRICULARES</th>
                @foreach($anos as $ano)
                    <th colspan="2" class="col-ano">
                        {{ $ano->serie_nome }}<br>
                        <span style="font-weight: normal; font-size: 6.8pt;">({{ $ano->ano_letivo }})</span>
                    </th>
                @endforeach
            </tr>
            <tr>
                @foreach($anos as $ano)
                    <th class="subcol" style="width: 38px;">NOTA</th>
                    <th class="subcol" style="width: 32px;">C.H.</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($areas as $areaNome => $disciplinas)
                <tr class="area-header">
                    <td colspan="{{ 1 + ($totalAnosCount * 2) }}">{{ $areaNome }}</td>
                </tr>
                @foreach($disciplinas as $discNome => $valoresPorAno)
                    <tr>
                        <td class="disciplina-nome">{{ $discNome }}</td>
                        @foreach($anos as $ano)
                            @php
                                $dado = $valoresPorAno[$ano->id] ?? null;
                                $notaFormatada = $dado && $dado['nota'] !== null ? number_format($dado['nota'], 1, ',', '.') : ($dado['conceito'] ?? '-');
                                $chFormatada = $dado && $dado['ch'] ? $dado['ch'] : '-';
                                $isReprovado = $dado && $dado['nota'] !== null && $dado['nota'] < 6.0;
                            @endphp
                            <td class="{{ $isReprovado ? 'reprovado' : '' }}">{{ $notaFormatada }}</td>
                            <td>{{ $chFormatada }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ 1 + ($totalAnosCount * 2) }}" style="text-align: center; padding: 10px; color: #718096;">
                        Nenhum registro curricular lançado para este histórico escolar.
                    </td>
                </tr>
            @endforelse

            <!-- LINHAS DE TOTAIS E RESULTADO FINAL -->
            <tr class="totais-row">
                <td style="text-align: left; padding-left: 6px;">CARGA HORÁRIA TOTAL DO ANO</td>
                @foreach($anos as $ano)
                    @php $t = $totaisAnos[$ano->id] ?? null; @endphp
                    <td colspan="2">{{ $t && $t['ch_total'] ? $t['ch_total'] . ' h' : '-' }}</td>
                @endforeach
            </tr>
            <tr class="totais-row">
                <td style="text-align: left; padding-left: 6px;">DIAS LETIVOS</td>
                @foreach($anos as $ano)
                    @php $t = $totaisAnos[$ano->id] ?? null; @endphp
                    <td colspan="2">{{ $t && $t['dias'] ? $t['dias'] . ' dias' : '200 dias' }}</td>
                @endforeach
            </tr>
            <tr class="totais-row">
                <td style="text-align: left; padding-left: 6px;">FREQUÊNCIA GLOBAL DO ALUNO (%)</td>
                @foreach($anos as $ano)
                    @php $t = $totaisAnos[$ano->id] ?? null; @endphp
                    <td colspan="2">{{ $t && $t['freq'] ? number_format($t['freq'], 1, ',', '.') . '%' : '-' }}</td>
                @endforeach
            </tr>
            <tr class="totais-row" style="background-color: #e2e8f0; font-size: 7.6pt;">
                <td style="text-align: left; padding-left: 6px; color: #2c5282;">RESULTADO FINAL DO ANO LETIVO</td>
                @foreach($anos as $ano)
                    @php $t = $totaisAnos[$ano->id] ?? null; @endphp
                    <td colspan="2" style="color: {{ ($t['situacao'] ?? '') === 'Reprovado' ? '#c53030' : '#22543d' }};">
                        {{ mb_strtoupper($t['situacao'] ?? 'APROVADO') }}
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <!-- QUADRO INFERIOR: ESTUDOS REALIZADOS E OBSERVAÇÕES/CERTIFICAÇÃO -->
    <div class="bottom-container">
        <table class="bottom-table">
            <tr>
                <!-- ESTABELECIMENTOS DE ENSINO / ESTUDOS ANTERIORES -->
                <td style="width: 50%;">
                    <div class="section-box">
                        <h3>Estudos Realizados / Estabelecimentos de Ensino</h3>
                        <table class="estudos-table">
                            <thead>
                                <tr>
                                    <th style="width: 32px;">Ano</th>
                                    <th style="width: 55px;">Série/Ano</th>
                                    <th>Estabelecimento de Ensino</th>
                                    <th style="width: 80px;">Município / UF</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($matriz['estabelecimentos'] as $est)
                                    <tr>
                                        <td style="text-align: center;">{{ $est['ano'] }}</td>
                                        <td>{{ $est['serie'] }}</td>
                                        <td>{{ $est['escola'] }}</td>
                                        <td>{{ $est['cidade_uf'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #718096;">Nenhum registro externo adicionado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>

                <!-- CERTIFICAÇÃO E OBSERVAÇÕES LEGAIS -->
                <td style="width: 50%;">
                    <div class="section-box">
                        <h3>Certificação e Observações</h3>
                        @if($historico->situacao === 'concluido')
                            <p style="margin: 0 0 3px 0; font-weight: bold; color: #22543d;">
                                {{ $historico->titulo_certificacao ?: 'CERTIFICADO DE CONCLUSÃO' }}
                            </p>
                            <p style="margin: 0 0 4px 0; font-size: 6.8pt; text-align: justify;">
                                {{ $historico->texto_certificacao ?: 'Certificamos que o(a) aluno(a) acima qualificado(a) CONCLUIU com aproveitamento a referida etapa da Educação Básica, estando apto(a) a prosseguir seus estudos em nível subsequente, nos termos da Lei Federal nº 9.394/1996.' }}
                            </p>
                        @elseif($historico->situacao === 'transferido')
                            <p style="margin: 0 0 3px 0; font-weight: bold; color: #2b6cb0;">
                                GUIA DE TRANSFERÊNCIA / HISTÓRICO PARCIAL
                            </p>
                            <p style="margin: 0 0 4px 0; font-size: 6.8pt; text-align: justify;">
                                O(A) estudante encontra-se devidamente desvinculado(a) por motivo de transferência escolar, tendo cumprido a carga horária e avaliações parciais aqui consignadas.
                            </p>
                        @else
                            <p style="margin: 0 0 3px 0; font-weight: bold; color: #d69e2e;">
                                HISTÓRICO PARCIAL (EM CURSO)
                            </p>
                            <p style="margin: 0 0 4px 0; font-size: 6.8pt;">
                                O(A) aluno(a) encontra-se regularmente matriculado(a) e com frequência ativa no presente ano letivo.
                            </p>
                        @endif

                        @if($historico->observacoes)
                            <p style="margin: 3px 0 0 0; font-size: 6.5pt; color: #4a5568;">
                                <strong>Observações:</strong> {{ $historico->observacoes }}
                            </p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- RODAPÉ DE ASSINATURAS E AUTENTICAÇÃO QR CODE -->
    <table class="footer-table">
        <tr>
            <td class="qr-box">
                @if(!empty($qrCodeDataUri))
                    <img src="{{ $qrCodeDataUri }}" alt="QR Code Autenticidade"><br>
                    <span>Aponte a câmera para conferência de autenticidade</span>
                @endif
            </td>
            <td style="width: 30%;">
                <p style="margin: 0 0 25px 0;">
                    {{ $unidade?->endereco?->cidade?->nome ?? 'Localidade' }}, {{ $dataEmissaoFormatada }}
                </p>
            </td>
            <td style="width: 30%;">
                <div class="signature-line">{{ $secretarioNome }}</div>
                <span>Secretário(a) Escolar</span>
            </td>
            <td style="width: 30%;">
                <div class="signature-line">{{ $diretorNome }}</div>
                <span>Diretor(a) Escolar</span>
            </td>
        </tr>
    </table>

</body>
</html>
