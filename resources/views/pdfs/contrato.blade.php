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

    @if(isset($conteudo_template) && $conteudo_template)
        <div class="template-content">
            {!! $conteudo_template !!}
        </div>
    @endif

    <div class="center" style="margin-top: 30px;">
        Rio de Janeiro, RJ, {{ date('d') }} de {{ \Carbon\Carbon::now()->translatedFormat('F') }} de {{ date('Y') }}.
    </div>

    @php
        $assinaturas = [];

        // 1. Pai
        $assinaturas[] = [
            'titulo' => 'PAI / CONTRATANTE' . ($pai && $principalRF && $pai->id === $principalRF->id ? '<br>E RESPONSÁVEL FINANCEIRO' : ''),
            'nome' => $pai?->nome ?? '_____________',
            'documento' => 'CPF: ' . ($pai?->cpf ?? '_____________'),
        ];

        // 2. Mãe
        $assinaturas[] = [
            'titulo' => 'MÃE / CONTRATANTE' . ($mae && $principalRF && $mae->id === $principalRF->id ? '<br>E RESPONSÁVEL FINANCEIRO' : ''),
            'nome' => $mae?->nome ?? '_____________',
            'documento' => 'CPF: ' . ($mae?->cpf ?? '_____________'),
        ];

        // 3. Responsáveis Financeiros (Se houver Terceiros)
        foreach ($contrato->responsaveisFinanceiros as $rf) {
            $rfPessoa = $rf->pessoa;
            if ($rfPessoa && (!$pai || $rfPessoa->id !== $pai->id) && (!$mae || $rfPessoa->id !== $mae->id)) {
                $assinaturas[] = [
                    'titulo' => 'RESPONSÁVEL FINANCEIRO (Terceiro)',
                    'nome' => $rfPessoa->nome,
                    'documento' => 'CPF: ' . $rfPessoa->cpf,
                ];
            }
        }

        // 4. Representantes Legais da Unidade
        if ($unidade && $unidade->representantesLegais->isNotEmpty()) {
            foreach ($unidade->representantesLegais as $rep) {
                $assinaturas[] = [
                    'titulo' => 'ESCOLA TORRE DE MARFIM',
                    'nome' => $rep->nome,
                    'documento' => $rep->pivot->cargo ?? 'Representante Legal',
                ];
            }
        } else {
            // Fallback se não houver representante cadastrado
            $assinaturas[] = [
                'titulo' => 'ESCOLA TORRE DE MARFIM',
                'nome' => '_____________',
                'documento' => '_____________',
            ];
        }

        // Dividir as assinaturas em pares para a tabela
        $chunks = array_chunk($assinaturas, 2);
    @endphp

    <table class="signature-table">
        @foreach($chunks as $chunk)
            <tr>
                @foreach($chunk as $assinatura)
                    <td>
                        <div class="line"></div>
                        <div class="bold">{!! $assinatura['titulo'] !!}</div>
                        <div>{{ $assinatura['nome'] }}</div>
                        <div>{{ $assinatura['documento'] }}</div>
                    </td>
                @endforeach
                {{-- Preencher com célula vazia se o par estiver incompleto --}}
                @if(count($chunk) === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>

    <div class="footer">
        Documento gerado em {{ date('d/m/Y H:i:s') }} - ID: {{ $contrato->id }}
    </div>

</body>

</html> 
