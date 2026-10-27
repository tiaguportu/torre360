<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Models\CenarioProximoAno;
use App\Models\PeriodoRematricula;
use App\Services\SimuladorProximoAnoService;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Projeção do próximo ano por série (Onda 76): a de hoje, com os parâmetros padrão, ou a fotografia de um cenário salvo.
 */
final class SimulacaoProximoAno implements FonteDeRelatorio
{
    use IndexaItens;

    public function chave(): string
    {
        return 'simulacao_proximo_ano';
    }

    public function titulo(): string
    {
        return 'Projeção do próximo ano letivo';
    }

    public function descricao(): string
    {
        return 'Alunos, turmas, vagas e ponto de equilíbrio por série de destino. É projeção, não compromisso: use para a reunião de planejamento.';
    }

    public function permissao(): string
    {
        return 'View:SimuladorProximoAno';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('serie', 'Série de destino'),
            new ColunaRelatorio('confirmadas', 'Confirmadas', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('em_andamento', 'Em andamento', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('espera', 'Lista de espera', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('projecao', 'Alunos projetados', TipoColunaRelatorio::Decimal),
            new ColunaRelatorio('turmas', 'Turmas', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('vagas', 'Vagas', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('alunos_por_turma', 'Alunos por turma', TipoColunaRelatorio::Decimal),
            new ColunaRelatorio('ponto_equilibrio', 'Ponto de equilíbrio (alunos por turma)', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('receita_mensal', 'Receita mensal prevista', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('situacao', 'Situação'),
        ]);
    }

    public function filtros(): array
    {
        $opcoes = ['' => 'Projeção de hoje, com os parâmetros padrão'];

        foreach (CenarioProximoAno::query()->orderByDesc('id')->limit(50)->get(['id', 'nome']) as $cenario) {
            $opcoes[(string) $cenario->id] = "Cenário salvo: {$cenario->nome}";
        }

        return $this->indexar([new FiltroRelatorio('cenario', 'Cenário', 'opcao', $opcoes)]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $rotulos = [
            'ok' => 'Dentro do esperado',
            'acima_da_capacidade' => 'Acima da capacidade',
            'sem_turma' => 'Demanda sem turma',
            'abaixo_do_equilibrio' => 'Abaixo do equilíbrio',
        ];

        $cenario = filled($filtros['cenario'] ?? null) ? CenarioProximoAno::query()->find((int) $filtros['cenario']) : null;
        $resultado = $cenario?->resultado;

        if ($resultado === null) {
            $periodo = app(SimuladorProximoAnoService::class)->periodoPadrao();

            if (! $periodo instanceof PeriodoRematricula) {
                return [];
            }

            $resultado = app(SimuladorProximoAnoService::class)->projetar($periodo);
        }

        return collect($resultado['linhas'] ?? [])->take($limite)->map(fn (array $l): array => [
            'serie' => $l['serie'],
            'confirmadas' => $l['confirmadas'],
            'em_andamento' => $l['em_andamento'],
            'espera' => $l['espera'],
            'projecao' => $l['projecao'],
            'turmas' => $l['turmas'],
            'vagas' => $l['vagas'],
            'alunos_por_turma' => $l['alunos_por_turma'],
            'ponto_equilibrio' => $l['ponto_equilibrio'],
            'receita_mensal' => $l['receita_mensal'],
            'situacao' => $rotulos[$l['situacao']] ?? $l['situacao'],
        ])->values()->all();
    }
}
