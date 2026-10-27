<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Services\RentabilidadeTurmaService;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;
use Carbon\CarbonImmutable;

/**
 * Resultado realizado por turma no mês (Onda 77), o mesmo cálculo da tela, com o critério de rateio escolhido.
 */
final class RentabilidadeTurmas implements FonteDeRelatorio
{
    use IndexaItens;

    public function chave(): string
    {
        return 'rentabilidade_turmas';
    }

    public function titulo(): string
    {
        return 'Resultado realizado por turma';
    }

    public function descricao(): string
    {
        return 'Faturado, custos (docente, direto e indireto rateado) e margem de cada turma em um mês. Contabilidade gerencial: o critério de rateio muda a margem.';
    }

    public function permissao(): string
    {
        return 'View:RentabilidadeRealizada';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('turma', 'Turma'),
            new ColunaRelatorio('serie', 'Série'),
            new ColunaRelatorio('curso', 'Curso'),
            new ColunaRelatorio('unidade', 'Unidade', padrao: false),
            new ColunaRelatorio('alunos', 'Alunos', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('faturado', 'Faturado', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('recebido', 'Recebido', TipoColunaRelatorio::Moeda, padrao: false),
            new ColunaRelatorio('em_atraso', 'Em atraso', TipoColunaRelatorio::Moeda, padrao: false),
            new ColunaRelatorio('custo_docente', 'Custo docente', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('custo_direto', 'Custo direto', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('custo_indireto', 'Custo indireto (rateado)', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('resultado', 'Resultado', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('margem', 'Margem (%)', TipoColunaRelatorio::Decimal),
            new ColunaRelatorio('custo_por_aluno', 'Custo por aluno', TipoColunaRelatorio::Moeda, padrao: false),
        ]);
    }

    public function filtros(): array
    {
        $meses = [];
        $mes = CarbonImmutable::today()->startOfMonth()->subMonthNoOverflow();

        for ($i = 0; $i < 18; $i++) {
            $meses[$mes->format('Y-m')] = RentabilidadeTurmaService::rotuloDoMes($mes);
            $mes = $mes->subMonthNoOverflow();
        }

        return $this->indexar([
            new FiltroRelatorio('mes', 'Mês', 'opcao', $meses),
            new FiltroRelatorio('criterio', 'Rateio do custo indireto', 'opcao', RentabilidadeTurmaService::CRITERIOS),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $mes = preg_match('/^(\d{4})-(\d{2})$/', (string) ($filtros['mes'] ?? ''), $partes)
            ? CarbonImmutable::create((int) $partes[1], max(1, min(12, (int) $partes[2])), 1)
            : CarbonImmutable::today()->startOfMonth()->subMonthNoOverflow();

        $resultado = app(RentabilidadeTurmaService::class)->calcular($mes->year, $mes->month, (string) ($filtros['criterio'] ?? 'aluno'));

        return collect($resultado['linhas'])->take($limite)->map(fn (array $l): array => [
            'turma' => $l['turma'],
            'serie' => $l['serie'],
            'curso' => $l['curso'],
            'unidade' => $l['unidade'],
            'alunos' => $l['alunos'],
            'faturado' => $l['faturado'],
            'recebido' => $l['recebido'],
            'em_atraso' => $l['em_atraso'],
            'custo_docente' => $l['custo_docente'],
            'custo_direto' => $l['custo_direto'],
            'custo_indireto' => $l['custo_indireto'],
            'resultado' => $l['resultado'],
            'margem' => $l['margem'],
            'custo_por_aluno' => $l['custo_por_aluno'],
        ])->values()->all();
    }
}
