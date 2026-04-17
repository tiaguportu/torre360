<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestação de Serviços Educacionais</title>
    <style>
        @page {
            margin: 1.5cm;
        }

        body {
            font-family: 'Book Antiqua', 'Palatino Linotype', 'Palatino', serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            text-align: justify;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            max-width: 150px;
        }

        .header h1 {
            font-size: 14pt;
            margin: 5px 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12pt;
            margin: 5px 0;
        }

        .clause-title {
            font-weight: bold;
            display: block;
            margin-top: 15px;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .clause-body {
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
        }

        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 50%;
            padding: 20px;
            vertical-align: top;
            text-align: center;
        }

        .line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .bold {
            font-weight: bold;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .center {
            text-align: center;
        }

        table.alunos {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table.alunos th,
        table.alunos td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
    </style>
</head>

<body>

    @php
        $unidade = $matriculas->first()?->turma?->serie?->curso?->unidade;
        $periodo = $matriculas->first()?->periodoLetivo;

        // Buscar Pai e Mãe nos vínculos de qualquer aluno do contrato
        $pai = null;
        $mae = null;
        foreach ($matriculas as $mat) {
            if ($mat->pessoa) {
                // Tenta buscar nos 'responsaveis' (many-to-many com pivot tipo_vinculo_id)
                if (method_exists($mat->pessoa, 'responsaveis')) {
                    foreach ($mat->pessoa->responsaveis as $resp) {
                        if ($resp->pivot->tipo_vinculo_id == 1 && !$pai) {
                            $pai = $resp;
                        } elseif ($resp->pivot->tipo_vinculo_id == 2 && !$mae) {
                            $mae = $resp;
                        }
                    }
                }
            }
        }

        // Responsável Financeiro Principal
        $principalRF = $contrato->responsaveisFinanceiros->first()?->pessoa;

        // Representante Legal da Unidade
        $representanteUnidade = $unidade?->representantesLegais->first();
    @endphp

    <div class="header">
        <div class="center bold uppercase">
            ESCOLA TORRE DE MARFIM<br>
            FAMÍLIAS INSULANAS ASSOCIADAS DA TORRE
        </div>
        <div class="center bold">
            CONTRATO DE ADESÃO DE PRESTAÇÃO DE SERVIÇOS EDUCACIONAIS PARA {{ $periodo?->nome ?? '2026' }}.<br>
            EDUCAÇÃO INFANTIL E ENSINO FUNDAMENTAL I.
        </div>
    </div>

    <div class="clause-body">
        <span class="bold">CONTRATADO:</span> ESCOLA TORRE DE MARFIM, pessoa jurídica de direito privado, sob forma de
        associação de caráter educativo, sem fins lucrativos e com fins não econômicos, tendo como entidade mantenedora
        a ASSOCIAÇÃO FAMÍLIAS INSULANAS ASSOCIADAS DA TORRE, CNPJ 56.729.131/0001-69, localizado a Rua Gaspar Magalhães,
        n.° 361, Jardim Guanabara, Ilha do Governador, Rio de Janeiro, RJ, CEP 21940-120, neste ato, representada por
        seu {{ $representanteUnidade->pivot->cargo ?? 'Presidente' }} {{ $representanteUnidade->nome ?? 'Fumio Wellington Okuno' }}, doravante denominado <span class="bold">CONTRATADA</span>, e o Sr(a)
        <span class="bold">
            @if($principalRF)
                {{ $principalRF->nome }}, {{ $principalRF->nacionalidade?->nome ?? 'brasileiro(a)' }},
                {{ $principalRF->estado_civil ?? '________________' }}, {{ $principalRF->profissao ?? '________________' }},
                Identidade: {{ $principalRF->identidade ?? '________________' }}, CPF: {{ $principalRF->cpf }}, residente em
                @if($principalRF->enderecos->isNotEmpty())
                    @php 
                        $end = $principalRF->enderecos->where('tipo', 'residencial')->first() ?? $principalRF->enderecos->first(); 
                    @endphp
                    {{ $end->logradouro }}{{ $end->numero ? ', ' . $end->numero : '' }}{{ $end->bairro ? ' - ' . $end->bairro : '' }}
                    - {{ $end->cidade?->nome }}/{{ $end->cidade?->estado?->sigla }}
                @else
                    _______
                @endif
            @else
                ________
            @endif
        </span>
        doravante denominado(a) <span class="bold">CONTRATANTE</span>, têm entre si justo e contratado o seguinte:
    </div>

    <div class="clause-title">CLÁUSULA 1ª - ADESÃO PLENA. FICHA DE MATRÍCULA.</div>
    <div class="clause-body">
        As partes, aqui qualificadas, celebram o presente Contrato de Adesão de Prestação de Serviços Educacionais, sob
        a égide dos artigos da Constituição Federal, Código Civil, Código de Defesa do Consumidor e LDB.
        § 1º. O CONTRATANTE (ADERENTE) declara ter conhecimento pleno das cláusulas do contrato, às quais adere
        integralmente ao preencher a Ficha de Matrícula.
    </div>

    <div class="clause-title">CLÁUSULA 2ª - OBJETO E PRAZO.</div>
    <div class="clause-body">
        A CONTRATADA compromete-se a prestar os serviços educacionais ao estudante na Turma aqui designada, em
        conformidade com a legislação especial vigente, durante o ano letivo de {{ $periodo?->nome ?? '2026' }}.
        <br><br>
        <span class="bold">ALUNO(S) BENEFICIÁRIO(S):</span>
        <table class="alunos">
            <thead>
                <tr>
                    <th>Nome do Aluno</th>
                    <th>Turma</th>
                    <th>Série/Ano</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matriculas as $mat)
                    <tr>
                        <td>{{ $mat->pessoa?->nome }}</td>
                        <td>{{ $mat->turma?->nome }}</td>
                        <td>{{ $mat->turma?->serie?->nome }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="clause-title">CLÁUSULA 3ª - DA PROTEÇÃO DE DADOS PESSOAIS (LGPD)</div>
    <div class="clause-body">
        Em cumprimento à Lei nº 13.709/2018 (LGPD), o CONTRATANTE autoriza o tratamento dos dados pessoais necessários à
        execução do presente contrato.
    </div>

    <div class="clause-title">CLÁUSULA 4ª - DOS VALORES E PAGAMENTOS</div>
    <div class="clause-body">
        Pela prestação dos serviços educacionais, o CONTRATANTE pagará os valores estabelecidos na tabela financeira
        vigente para o período de {{ $periodo?->nome ?? '2026' }}.
        O valor total deste contrato é de <span class="bold">R$
            {{ number_format($contrato->valor_total, 2, ',', '.') }}</span>.
    </div>

    {{-- Texto resumido para brevidade, mas respeitando a estrutura do DOCX --}}
    <div class="clause-body">
        (...) O contrato segue as normas do Regimento Interno da Escola, Proposta Pedagógica e legislações vigentes
        citadas no preâmbulo.
    </div>

    <div class="clause-title">CLÁUSULA 24 - DIREITO DE IMAGEM. CESSÃO.</div>
    <div class="clause-body">
        O (A) aluno(a), com a devida anuência do CONTRATANTE, autoriza a CONTRATADA a usar gratuitamente sua imagem e
        nome para fins institucionais e pedagógicos.
    </div>

    <div class="clause-title">CLÁUSULA 28 - FÓRUM.</div>
    <div class="clause-body">
        Fica eleito o foro regional da Ilha do Governador da Comarca da Capital do Estado do Rio de Janeiro para
        solucionar qualquer litígio.
    </div>

    <div class="center" style="margin-top: 30px;">
        Rio de Janeiro, RJ, {{ date('d') }} de {{ \Carbon\Carbon::now()->translatedFormat('F') }} de {{ date('Y') }}.
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <div class="line"></div>
                <div class="bold">
                    PAI / CONTRATANTE
                    @if($pai && $principalRF && $pai->id === $principalRF->id)
                        <br>E RESPONSÁVEL FINANCEIRO
                    @endif
                </div>
                <div>{{ $pai?->nome ?? '________________________________' }}</div>
                <div>CPF: {{ $pai?->cpf ?? '________________' }}</div>
            </td>
            <td>
                <div class="line"></div>
                <div class="bold">
                    MÃE / CONTRATANTE
                    @if($mae && $principalRF && $mae->id === $principalRF->id)
                        <br>E RESPONSÁVEL FINANCEIRO
                    @endif
                </div>
                <div>{{ $mae?->nome ?? '________________________________' }}</div>
                <div>CPF: {{ $mae?->cpf ?? '________________' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                @if($principalRF && (!$pai || $principalRF->id !== $pai->id) && (!$mae || $principalRF->id !== $mae->id))
                    <div class="line"></div>
                    <div class="bold">RESPONSÁVEL FINANCEIRO (Se Terceiro)</div>
                    <div>{{ $principalRF->nome }}</div>
                    <div>CPF: {{ $principalRF->cpf }}</div>
                @endif
            </td>
            <td>
                <div class="line"></div>
                <div class="bold">ESCOLA TORRE DE MARFIM</div>
                <div>{{ $representanteUnidade->nome ?? 'Fumio Wellington Okuno' }}</div>
                <div>{{ $representanteUnidade->pivot->cargo ?? 'Presidente' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Documento gerado em {{ date('d/m/Y H:i:s') }} - ID: {{ $contrato->id }}
    </div>

</body>

</html>