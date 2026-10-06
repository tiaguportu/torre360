<?php

namespace App\Services;

use App\Models\Matricula;
use App\Models\PlanilhaLeiMensalidade;
use App\Models\Turma;

class PlanilhaLeiMensalidadeService
{
    /**
     * Calcula todos os índices e valores projetados conforme a fórmula da Lei 9.870/99.
     */
    public function calcularIndices(array $dados): array
    {
        $alunosBase = max(0, (int) ($dados['alunos_base'] ?? 0));
        $mensalidadeMediaBase = (float) ($dados['mensalidade_media_base'] ?? 0);
        $receitaAnualBase = $alunosBase > 0 ? ($mensalidadeMediaBase * 12 * $alunosBase) : 0;

        $custoPessoalBase = (float) ($dados['custo_pessoal_base'] ?? 0);
        $custoCusteioBase = (float) ($dados['custo_custeio_base'] ?? 0);
        $custoInvestimentoBase = (float) ($dados['custo_investimento_base'] ?? 0);
        $custoTotalBase = $custoPessoalBase + $custoCusteioBase + $custoInvestimentoBase;

        // Variação de Pessoal (Dissídio Coletivo + Encargos)
        $pctDissidioPessoal = (float) ($dados['percentual_dissidio_pessoal'] ?? 0);
        $variacaoPessoalValor = $custoPessoalBase * ($pctDissidioPessoal / 100);
        $custoPessoalProjetado = $custoPessoalBase + $variacaoPessoalValor;

        // Variação de Custeio (Inflação de insumos, terceirizados, água/luz)
        $pctInflacaoCusteio = (float) ($dados['percentual_inflacao_custeio'] ?? 0);
        $variacaoCusteioValor = $custoCusteioBase * ($pctInflacaoCusteio / 100);
        $custoCusteioProjetado = $custoCusteioBase + $variacaoCusteioValor;

        // Novos Investimentos Pedagógicos / Tecnológicos / Infraestrutura
        $valorNovosInvestimentos = (float) ($dados['valor_novos_investimentos'] ?? 0);
        $custoInvestimentoProjetado = $custoInvestimentoBase + $valorNovosInvestimentos;

        $custoTotalProjetado = $custoPessoalProjetado + $custoCusteioProjetado + $custoInvestimentoProjetado;

        // Índice de Variação de Custo Total da Lei 9.870/99
        $variacaoCustoTotalPercentual = $custoTotalBase > 0
            ? round((($custoTotalProjetado - $custoTotalBase) / $custoTotalBase) * 100, 2)
            : 0;

        $reajusteSugerido = max(0, $variacaoCustoTotalPercentual);
        $reajusteAdotado = isset($dados['percentual_reajuste_adotado']) && $dados['percentual_reajuste_adotado'] !== ''
            ? (float) $dados['percentual_reajuste_adotado']
            : $reajusteSugerido;

        $mensalidadeProjetada = round($mensalidadeMediaBase * (1 + ($reajusteAdotado / 100)), 2);
        $anuidadeProjetada = round($mensalidadeProjetada * 12, 2);

        $metaAlunosProjetada = isset($dados['meta_alunos_projetada']) && (int) $dados['meta_alunos_projetada'] > 0
            ? (int) $dados['meta_alunos_projetada']
            : $alunosBase;

        return [
            'alunos_base' => $alunosBase,
            'mensalidade_media_base' => $mensalidadeMediaBase,
            'receita_anual_base' => round($receitaAnualBase, 2),
            'custo_pessoal_base' => $custoPessoalBase,
            'custo_custeio_base' => $custoCusteioBase,
            'custo_investimento_base' => $custoInvestimentoBase,
            'custo_total_base' => round($custoTotalBase, 2),
            'percentual_dissidio_pessoal' => $pctDissidioPessoal,
            'variacao_pessoal_valor' => round($variacaoPessoalValor, 2),
            'custo_pessoal_projetado' => round($custoPessoalProjetado, 2),
            'percentual_inflacao_custeio' => $pctInflacaoCusteio,
            'variacao_custeio_valor' => round($variacaoCusteioValor, 2),
            'custo_custeio_projetado' => round($custoCusteioProjetado, 2),
            'valor_novos_investimentos' => $valorNovosInvestimentos,
            'custo_investimento_projetado' => round($custoInvestimentoProjetado, 2),
            'custo_total_projetado' => round($custoTotalProjetado, 2),
            'meta_alunos_projetada' => $metaAlunosProjetada,
            'variacao_custo_total_percentual' => $variacaoCustoTotalPercentual,
            'percentual_reajuste_sugerido' => $reajusteSugerido,
            'percentual_reajuste_adotado' => $reajusteAdotado,
            'mensalidade_projetada' => $mensalidadeProjetada,
            'anuidade_projetada' => $anuidadeProjetada,
        ];
    }

    /**
     * Coleta dados reais cadastrados no ERP para pré-preenchimento do ano base.
     */
    public function importarHistoricoSistema(int $anoBase, ?int $unidadeId = null, ?int $cursoId = null): array
    {
        $queryMatriculas = Matricula::query()->where('situacao', 'ativa');
        $queryTurmas = Turma::query();

        if ($unidadeId) {
            $queryTurmas->whereHas('serie.curso', fn ($q) => $q->where('unidade_id', $unidadeId));
        }
        if ($cursoId) {
            $queryTurmas->whereHas('serie', fn ($q) => $q->where('curso_id', $cursoId));
        }

        $alunosContagem = $queryMatriculas->count();
        $turmas = $queryTurmas->get();

        $mensalidadeMedia = (float) ($turmas->avg('mensalidade_base') ?? 0);
        if ($mensalidadeMedia <= 0) {
            $mensalidadeMedia = 850.00; // Padrão de referência caso turmas não tenham preenchido
        }

        // Soma custos docentes e operacionais anuais das turmas (12 meses)
        $custoPessoalAnual = (float) $turmas->sum(fn ($t) => ((float) $t->custo_docente_mensal) * 12);
        $custoCusteioAnual = (float) $turmas->sum(fn ($t) => ((float) $t->custo_operacional_rateado) * 12);

        if ($custoPessoalAnual <= 0 && $alunosContagem > 0) {
            $custoPessoalAnual = ($mensalidadeMedia * $alunosContagem * 12) * 0.55; // Estimativa média K-12 de 55% folha
        }
        if ($custoCusteioAnual <= 0 && $alunosContagem > 0) {
            $custoCusteioAnual = ($mensalidadeMedia * $alunosContagem * 12) * 0.25; // Estimativa média K-12 de 25% custeio
        }

        return [
            'alunos_base' => $alunosContagem,
            'mensalidade_media_base' => round($mensalidadeMedia, 2),
            'custo_pessoal_base' => round($custoPessoalAnual, 2),
            'custo_custeio_base' => round($custoCusteioAnual, 2),
            'custo_investimento_base' => round($custoCusteioAnual * 0.10, 2),
            'percentual_dissidio_pessoal' => 5.50,
            'percentual_inflacao_custeio' => 4.50,
            'valor_novos_investimentos' => 50000.00,
        ];
    }

    /**
     * Gera o espelho executivo oficial timbrado da Planilha da Lei 9.870/1999 em HTML.
     */
    public function gerarEspelhoOficialHtml(PlanilhaLeiMensalidade $planilha): string
    {
        $unidadeNome = $planilha->unidade?->nome ?? 'Todas as Unidades';
        $cursoNome = $planilha->curso?->nome ?? 'Educação Básica Geral';
        $dataAfixacaoFmt = $planilha->data_afixacao ? $planilha->data_afixacao->format('d/m/Y') : now()->format('d/m/Y');

        return view('filament.components.planilha-lei-mensalidade-modal', [
            'planilha' => $planilha,
            'unidadeNome' => $unidadeNome,
            'cursoNome' => $cursoNome,
            'dataAfixacaoFmt' => $dataAfixacaoFmt,
        ])->render();
    }
}
