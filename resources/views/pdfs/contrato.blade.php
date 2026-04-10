<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestação de Serviços Educacionais</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #333;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 16pt;
            text-transform: uppercase;
            margin: 0;
        }
        .section {
            margin-bottom: 20px;
            text-align: justify;
        }
        .section-title {
            font-weight: bold;
            text-decoration: underline;
            display: block;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
        }
        .signature-box {
            margin-top: 100px;
            width: 100%;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin: 0 auto;
            text-align: center;
            padding-top: 5px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .table-data th {
            background: #f0f0f0;
            font-weight: bold;
            padding: 6px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .table-data td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>CONTRATO DE PRESTAÇÃO DE SERVIÇOS EDUCACIONAIS</h1>
        <p>Torre360 - Gestão Escolar Inteligente</p>
    </div>

    <div class="section">
        <span class="section-title">1. DAS PARTES</span>
        <p>
            <strong>CONTRATADA:</strong> TORRE360 GESTAO ESCOLAR, inscrita no CNPJ sob o nº XX.XXX.XXX/XXXX-XX, com sede na Rua Exemplo, nº 123.
        </p>

        {{-- Responsáveis Financeiros --}}
        @if(isset($responsaveisFinanceiros) && $responsaveisFinanceiros->isNotEmpty())
            @foreach($responsaveisFinanceiros as $resp)
                @php $p = $resp->pessoa; @endphp
                <p>
                    <strong>CONTRATANTE{{ $responsaveisFinanceiros->count() > 1 ? ' ' . ($loop->iteration) : '' }}:</strong>
                    {{ $p?->nome ?? '________________________________' }},
                    CPF: {{ $p?->cpf ? \Illuminate\Support\Str::mask($p->cpf, '*', 3, 6) : '________________' }}.
                </p>
            @endforeach
        @elseif(isset($responsavel))
            <p>
                <strong>CONTRATANTE:</strong> {{ $responsavel->nome ?? '________________________________' }},
                CPF: {{ $responsavel->cpf ?? '________________' }}.
            </p>
        @endif

        {{-- Alunos --}}
        <p><strong>ALUNO(S):</strong></p>
        <table class="table-data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Turma</th>
                </tr>
            </thead>
            <tbody>
                @if(isset($matriculas) && $matriculas->isNotEmpty())
                    @foreach($matriculas as $mat)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $mat->pessoa?->nome ?? '—' }}</td>
                            <td>{{ $mat->pessoa?->cpf ?? '—' }}</td>
                            <td>{{ $mat->turma?->nome ?? '—' }}</td>
                        </tr>
                    @endforeach
                @elseif(isset($aluno))
                    <tr>
                        <td>1</td>
                        <td>{{ $aluno->nome ?? '—' }}</td>
                        <td>{{ $aluno->cpf ?? '—' }}</td>
                        <td>{{ $matricula?->turma?->nome ?? '—' }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="section">
        <span class="section-title">2. DO OBJETO</span>
        <p>
            O presente contrato tem por objeto a prestação de serviços educacionais para a série <strong>{{ $serie?->nome ?? '________' }}</strong>
            no curso de <strong>{{ $curso?->nome ?? '________' }}</strong>, referente ao período letivo de {{ $periodoLetivo?->nome ?? '____' }}.
        </p>
    </div>

    <div class="section">
        <span class="section-title">3. DO VALOR E FORMA DE PAGAMENTO</span>
        <p>
            Pelo serviço objeto deste contrato, o CONTRATANTE pagará o valor total de
            <strong>R$ {{ number_format($contrato->valor_total ?? 0, 2, ',', '.') }}</strong>,
            @if($contrato->quantidade_parcelas)
                dividido em <strong>{{ $contrato->quantidade_parcelas }} {{ $contrato->quantidade_parcelas == 1 ? 'parcela' : 'parcelas' }}</strong>,
                com valor estimado por parcela de
                <strong>R$ {{ number_format(($contrato->valor_total ?? 0) / $contrato->quantidade_parcelas, 2, ',', '.') }}</strong>,
            @endif
            conforme parcelas discriminadas no sistema financeiro da instituição.
        </p>
    </div>

    <div class="section">
        <span class="section-title">4. DAS DISPOSIÇÕES GERAIS</span>
        <p>
            Este contrato é regido pelas leis vigentes e as partes elegem o foro da comarca local para dirimir quaisquer dúvidas.
            O aceite digital deste documento via plataforma Assinafy possui validade jurídica plena nos termos da MP 2.200-2/2001.
        </p>
    </div>

    <div class="footer">
        <p>Documento gerado em {{ date('d/m/Y H:i:s') }}</p>
    </div>

</body>
</html>
