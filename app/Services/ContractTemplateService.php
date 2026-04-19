<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Unidade;
use Carbon\Carbon;

class ContractTemplateService
{
    public function process(Contrato $contrato, string $html): string
    {
        // Carrega relações necessárias caso não estejam presentes
        $contrato->loadMissing([
            'matriculas.pessoa',
            'matriculas.turma.serie.curso.unidade.representantesLegais',
            'responsaveisFinanceiros.pessoa.enderecos',
            'faturas',
        ]);

        $unidade = $contrato->matriculas->first()?->turma?->serie?->curso?->unidade;

        $macros = [
            '{{CONTRATO_ID}}' => $contrato->id,
            '{{CONTRATO_VALOR}}' => 'R$ '.number_format($contrato->valor_total, 2, ',', '.'),
            '{{CONTRATO_DATA}}' => Carbon::now()->translatedFormat('d \d\e F \d\e Y'),

            '{{UNIDADE_NOME}}' => $unidade?->nome ?? '',
            '{{UNIDADE_CNPJ}}' => $unidade?->cnpj ?? '',
            '{{UNIDADE_REPRESENTANTES}}' => $this->generateRepresentantesUnidade($unidade),

            '{{ALUNOS_TABELA}}' => $this->generateAlunosTable($contrato),
            '{{RESPONSAVEIS_INFO}}' => $this->generateResponsaveisInfo($contrato),
            '{{FATURAS_TABELA}}' => $this->generateFaturasTable($contrato),
        ];

        return str_replace(array_keys($macros), array_values($macros), $html);
    }

    protected function generateRepresentantesUnidade(?Unidade $unidade): string
    {
        if (! $unidade || $unidade->representantesLegais->isEmpty()) {
            return '_______';
        }

        $representantes = [];
        foreach ($unidade->representantesLegais as $rep) {
            $cargo = $rep->pivot->cargo ?? 'Representante Legal';
            $representantes[] = "seu {$cargo}, {$rep->nome}";
        }

        // Formatação gramatical (item1, item2 e item3)
        if (count($representantes) === 1) {
            return $representantes[0];
        }

        $ultimo = array_pop($representantes);

        return implode(', ', $representantes).' e '.$ultimo;
    }

    protected function generateAlunosTable(Contrato $contrato): string
    {
        $html = '<table style="width: 100%; border-collapse: collapse; border: 1px solid black;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Nome do Aluno</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Turma</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Série/Ano</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($contrato->matriculas as $mat) {
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid black; padding: 5px;">'.($mat->pessoa?->nome ?? '-').'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px;">'.($mat->turma?->nome ?? '-').'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px;">'.($mat->turma?->serie?->nome ?? '-').'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    protected function generateResponsaveisInfo(Contrato $contrato): string
    {
        $info = [];
        foreach ($contrato->responsaveisFinanceiros as $rf) {
            $p = $rf->pessoa;
            if (! $p) {
                continue;
            }

            $end = $p->enderecos->first();
            $enderecoStr = $end ? "{$end->logradouro}, {$end->numero} - {$end->bairro}, {$end->cidade?->nome}/{$end->cidade?->estado?->sigla}" : '_______';

            $info[] = "<strong>{$p->nome}</strong>, CPF: {$p->cpf}, residente em {$enderecoStr}.";
        }

        return implode('<br>', $info);
    }

    protected function generateFaturasTable(Contrato $contrato): string
    {
        $faturas = $contrato->faturas->sortBy('data_vencimento');

        if ($faturas->isEmpty()) {
            return 'Nenhuma fatura encontrada.';
        }

        $html = '<table style="width: 100%; border-collapse: collapse; border: 1px solid black;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Parcela</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Vencimento</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Valor</th>';
        $html .= '</tr></thead><tbody>';

        $i = 1;
        foreach ($faturas as $fatura) {
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: center;">'.$i++.'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: center;">'.Carbon::parse($fatura->data_vencimento)->format('d/m/Y').'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor_total, 2, ',', '.').'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
