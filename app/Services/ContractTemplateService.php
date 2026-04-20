<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ContractTemplateService
{
    public function process(Contrato $contrato, string $html): string
    {
        // Carrega relações necessárias caso não estejam presentes
        $contrato->loadMissing([
            'matriculas.pessoa.responsaveis',
            'matriculas.turma.serie.curso.unidade.representantesLegais',
            'responsaveisFinanceiros.pessoa.enderecos',
            'faturas',
        ]);

        $unidade = $contrato->matriculas->first()?->turma?->serie?->curso?->unidade;
        $aluno = $contrato->matriculas->first()?->pessoa;

        // Mapa de nomes de vínculos para busca rápida no pivô
        $tiposVinculo = TipoVinculo::all()->pluck('nome', 'id');

        $macros = [
            '{{CONTRATO.ID}}' => $contrato->id,
            '{{CONTRATO.VALOR}}' => 'R$ '.number_format($contrato->valor_total, 2, ',', '.'),
            '{{CONTRATO.DATA}}' => Carbon::now()->translatedFormat('d \d\e F \d\e Y'),

            '{{UNIDADE.NOME}}' => $unidade?->nome ?? '',
            '{{UNIDADE.CNPJ}}' => $unidade?->cnpj ?? '',
            '{{UNIDADE.REPRESENTANTES}}' => $this->generateRepresentantesUnidade($unidade),

            '{{ALUNOS.TABELA}}' => $this->generateAlunosTable($contrato),
            '{{RESPONSAVEIS.INFO}}' => $this->generateResponsaveisInfo($contrato),
            '{{FATURAS.TABELA}}' => $this->generateFaturasTable($contrato),

            '{{ASSINATURA.REPRESENTANTES}}' => $this->generateAssinaturasUnidade($unidade),
            '{{ASSINATURA.RESPONSAVEIS}}' => $this->generateAssinaturasResponsaveis($contrato),
            '{{ASSINATURA.PAI}}' => $this->generateAssinaturaParente($aluno, 'Pai', $tiposVinculo),
            '{{ASSINATURA.MAE}}' => $this->generateAssinaturaParente($aluno, 'Mãe', $tiposVinculo),
        ];

        return str_replace(array_keys($macros), array_values($macros), $html);
    }

    protected function generateAssinaturaBlock(string $titulo, ?string $extra = null, ?string $cpf = null): string
    {
        $extraFormatado = $extra ? " ({$extra})" : '';
        $cpfValor = $cpf ?: '___________________________';

        return '<div style="margin-top: 50px; margin-bottom: 30px;">'
            .'_______________________________________________<br>'
            .$titulo.$extraFormatado.'<br><br>'
            ."CPF nº {$cpfValor}"
            .'</div>';
    }

    protected function generateAssinaturasUnidade(?Unidade $unidade): string
    {
        if (! $unidade || $unidade->representantesLegais->isEmpty()) {
            return $this->generateAssinaturaBlock('CONTRATADA', 'Escola Torre de Marfim');
        }

        $html = '';
        foreach ($unidade->representantesLegais as $rep) {
            $cargo = $rep->pivot->cargo ?? 'Representante Legal';
            $html .= $this->generateAssinaturaBlock('CONTRATADA', "{$rep->nome} - {$cargo}", $rep->cpf);
        }

        return $html;
    }

    protected function generateAssinaturasResponsaveis(Contrato $contrato): string
    {
        $html = '';
        foreach ($contrato->responsaveisFinanceiros as $rf) {
            if ($rf->pessoa) {
                $html .= $this->generateAssinaturaBlock('CONTRATANTE-ADERENTE', $rf->pessoa->nome, $rf->pessoa->cpf);
            }
        }

        return $html;
    }

    protected function generateAssinaturaParente(?Pessoa $aluno, string $vinculoNome, Collection $tiposVinculo): string
    {
        if (! $aluno) {
            return $this->generateAssinaturaBlock('CONTRATANTE-ADERENTE', $vinculoNome);
        }

        $parente = $aluno->responsaveis->first(function ($resp) use ($vinculoNome, $tiposVinculo) {
            return $tiposVinculo->get($resp->pivot->tipo_vinculo_id) === $vinculoNome;
        });

        return $this->generateAssinaturaBlock(
            'CONTRATANTE-ADERENTE',
            $parente ? "{$parente->nome} - {$vinculoNome}" : $vinculoNome,
            $parente?->cpf
        );
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
        $faturas = $contrato->faturas->sortBy('vencimento');

        if ($faturas->isEmpty()) {
            return 'Nenhuma fatura encontrada.';
        }

        $html = '<table style="width: 100%; border-collapse: collapse; border: 1px solid black;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Parcela</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Vencimento</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Valor Original</th>';
        $html .= '<th style="border: 1px solid black; padding: 5px;">Valor com Desconto</th>';
        $html .= '</tr></thead><tbody>';

        $i = 1;
        foreach ($faturas as $fatura) {
            $html .= '<tr>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: center;">'.$i++.'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: center;">'.Carbon::parse($fatura->vencimento)->format('d/m/Y').'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor_bruto, 2, ',', '.').'</td>';
            $html .= '<td style="border: 1px solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor, 2, ',', '.').'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
