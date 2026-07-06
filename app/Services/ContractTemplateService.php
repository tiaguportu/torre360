<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Contrato;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;

class ContractTemplateService
{
    public function process(Contrato $contrato, string $html): string
    {
        // Carrega relações necessárias caso não estejam presentes
        $contrato->loadMissing([
            'matricula.pessoa.responsaveis',
            'matricula.turma.serie.curso.unidade.representantesLegais',
            'responsaveisFinanceiros.pessoa.enderecos',
            'faturas',
        ]);

        $unidade = $contrato->matricula?->turma?->serie?->curso?->unidade;
        $aluno = $contrato->matricula?->pessoa;

        // Mapa de nomes de vínculos para busca rápida no pivô
        $tiposVinculo = TipoVinculo::all()->pluck('nome', 'id');

        // Pré-processa o template: resolve escapes do editor e compila as macros customizadas {{!! variavel !!}}
        $html = $this->preprocessBlade($html, $contrato, $aluno, $unidade, $tiposVinculo);

        // Gera as variáveis de fallback clássicas para manter compatibilidade retroativa com templates antigos
        $tabelaFaturas = $this->generateFaturasTableFallback($contrato);
        $tabelaAluno = $this->generateAlunoTableFallback($contrato);
        $infoResponsaveis = $this->generateResponsaveisInfo($contrato);
        $assinaturasRepresentantes = $this->generateAssinaturasUnidade($unidade);
        $assinaturasResponsaveis = $this->generateAssinaturasResponsaveis($contrato);
        $assinaturaPai = $this->generateAssinaturaParente($aluno, 'Pai', $tiposVinculo);
        $assinaturaMae = $this->generateAssinaturaParente($aluno, 'Mãe', $tiposVinculo);

        try {
            return Blade::render($html, [
                'contrato' => $contrato,
                'unidade' => $unidade,
                'aluno' => $aluno,
                'responsaveis' => $contrato->responsaveisFinanceiros,
                'faturas' => $contrato->faturas,
                // Variáveis do Blade para templates antigos
                'tabelaFaturas' => $tabelaFaturas,
                'tabelaAluno' => $tabelaAluno,
                'infoResponsaveis' => $infoResponsaveis,
                'assinaturasRepresentantes' => $assinaturasRepresentantes,
                'assinaturasResponsaveis' => $assinaturasResponsaveis,
                'assinaturaPai' => $assinaturaPai,
                'assinaturaMae' => $assinaturaMae,
            ]);
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            logger()->error('Erro ao renderizar template de contrato com Blade: '.$e->getMessage());

            return $html;
        }
    }

    protected function preprocessBlade(string $content, Contrato $contrato, ?Pessoa $aluno, ?Unidade $unidade, Collection $tiposVinculo): string
    {
        // Remove macros legadas do tipo {{MACRO}} ou {{MACRO.SUB}}
        $content = preg_replace('/\{\{[A-Z_]+(?:\.[A-Z_]+)*\}\}/', '', $content);

        // Decodifica entidades HTML em todo o template duas vezes (resolve escapes do TinyMCE de forma global)
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Substitui non-breaking spaces por espaço comum
        $content = str_replace(chr(194).chr(160), ' ', $content);
        $content = str_replace('&nbsp;', ' ', $content);

        // Processa as macros dinâmicas no formato {{!! variavel !!}}
        $content = preg_replace_callback('/\{\{!!\s*(\w+)\s*!!\}\}/', function ($matches) use ($contrato, $aluno, $unidade, $tiposVinculo) {
            $variableName = $matches[1];
            $configKey = 'template_contrato_'.$variableName;

            // Busca a configuração correspondente no banco de dados
            $config = Configuracao::where('campo', $configKey)->first();

            if ($config && ! empty($config->valor)) {
                try {
                    // Renderiza o template da macro customizada usando Blade
                    return Blade::render($config->valor, [
                        'contrato' => $contrato,
                        'aluno' => $aluno,
                        'unidade' => $unidade,
                        'responsaveis' => $contrato->responsaveisFinanceiros,
                        'faturas' => $contrato->faturas,
                    ]);
                } catch (\Throwable $e) {
                    logger()->error("Erro ao renderizar macro customizada {$configKey}: ".$e->getMessage());

                    return "<!-- Erro ao renderizar macro {$variableName} -->";
                }
            }

            // Fallback para as variáveis padrões do sistema
            return $this->getFallbackHtmlForVariable($variableName, $contrato, $aluno, $unidade, $tiposVinculo);
        }, $content);

        return $content;
    }

    protected function getFallbackHtmlForVariable(string $variableName, Contrato $contrato, ?Pessoa $aluno, ?Unidade $unidade, Collection $tiposVinculo): string
    {
        switch ($variableName) {
            case 'tabela_fatura':
                return $this->generateFaturasTableFallback($contrato);
            case 'tabela_aluno':
                return $this->generateAlunoTableFallback($contrato);
            case 'info_responsaveis':
                return $this->generateResponsaveisInfo($contrato);
            case 'assinaturas_representantes':
                return $this->generateAssinaturasUnidade($unidade);
            case 'assinaturas_responsaveis':
                return $this->generateAssinaturasResponsaveis($contrato);
            case 'assinatura_pai':
                return $this->generateAssinaturaParente($aluno, 'Pai', $tiposVinculo);
            case 'assinatura_mae':
                return $this->generateAssinaturaParente($aluno, 'Mãe', $tiposVinculo);
            default:
                return '';
        }
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

    protected function generateAlunoTableFallback(Contrato $contrato): string
    {
        $mat = $contrato->matricula;
        if (! $mat) {
            return 'Nenhum aluno vinculado a este contrato.';
        }

        $aluno = $mat->pessoa;
        $nome = $aluno?->nome ?? '-';
        $nascimento = $aluno?->data_nascimento ? Carbon::parse($aluno->data_nascimento)->format('d/m/Y') : '-';
        $cpf = $aluno?->cpf ?? '-';
        $turma = $mat->turma?->nome ?? '-';
        $serie = $mat->turma?->serie?->nome ?? '-';

        $html = '<table style="width: 100%; border-collapse: collapse; border: 1pt solid black; margin: 10px 0;">';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2; width: 30%;">Nome Completo</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$nome.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">Data de Nascimento</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$nascimento.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">CPF</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$cpf.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">Turma</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$turma.'</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="border: 1pt solid black; padding: 5px; font-weight: bold; background-color: #f2f2f2;">Série/Ano</td>';
        $html .= '<td style="border: 1pt solid black; padding: 5px;">'.$serie.'</td>';
        $html .= '</tr>';
        $html .= '</table>';

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

    protected function generateFaturasTableFallback(Contrato $contrato): string
    {
        $faturas = $contrato->faturas->sortBy('vencimento');

        if ($faturas->isEmpty()) {
            return 'Nenhuma fatura encontrada.';
        }

        $html = '<table style="width: 100%; border-collapse: collapse; border: 1pt solid black;">';
        $html .= '<thead><tr style="background-color: #f2f2f2;">';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Parcela</th>';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Vencimento</th>';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Valor Original</th>';
        $html .= '<th style="border: 1pt solid black; padding: 5px;">Valor com Desconto</th>';
        $html .= '</tr></thead><tbody>';

        $i = 1;
        foreach ($faturas as $fatura) {
            $html .= '<tr>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: center;">'.$i++.'</td>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: center;">'.Carbon::parse($fatura->vencimento)->format('d/m/Y').'</td>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor_bruto, 2, ',', '.').'</td>';
            $html .= '<td style="border: 1pt solid black; padding: 5px; text-align: right;">R$ '.number_format($fatura->valor, 2, ',', '.').'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
