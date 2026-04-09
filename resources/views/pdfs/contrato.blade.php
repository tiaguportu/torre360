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
            <strong>CONTRATADA:</strong> TORRE360 GESTAO ESCOLAR, inscrita no CNPJ sob o nº XX.XXX.XXX/XXXX-XX, com sede na Rua Exemplo, nº 123.<br>
            <strong>CONTRATANTE:</strong> {{ $responsavel->nome ?? '________________________________' }}, 
            CPF: {{ $responsavel->cpf ?? '________________' }}, 
            residente em {{ $responsavel->endereco ?? '________________________________' }}.<br>
            <strong>ALUNO(A):</strong> {{ $aluno->nome ?? '________________________________' }}, 
            Matrícula: {{ $matricula->id ?? '____' }}.
        </p>
    </div>

    <div class="section">
        <span class="section-title">2. DO OBJETO</span>
        <p>
            O presente contrato tem por objeto a prestação de serviços educacionais para a série <strong>{{ $serie->nome ?? '________' }}</strong> 
            no curso de <strong>{{ $curso->nome ?? '________' }}</strong>, referente ao período letivo de {{ $periodoLetivo->nome ?? '____' }}.
        </p>
    </div>

    <div class="section">
        <span class="section-title">3. DO VALOR E FORMA DE PAGAMENTO</span>
        <p>
            Pelo serviço objeto deste contrato, o CONTRATANTE pagará o valor total de <strong>R$ {{ number_format($contrato->valor_total ?? 0, 2, ',', '.') }}</strong>, 
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
