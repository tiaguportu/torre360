<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Models\Fatura;
use App\Models\Turma;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Faturas em atraso (a mesma regra do Relatório de Inadimplência): vencidas e não pagas, com o saldo devedor.
 */
final class Inadimplencia implements FonteDeRelatorio
{
    use IndexaItens;

    public function chave(): string
    {
        return 'inadimplencia';
    }

    public function titulo(): string
    {
        return 'Inadimplência';
    }

    public function descricao(): string
    {
        return 'Faturas vencidas e ainda não pagas, com o saldo devedor e os dias de atraso.';
    }

    public function permissao(): string
    {
        return 'View:RelatorioInadimplencia';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('fatura', 'Fatura', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('aluno', 'Aluno', sensivel: true),
            new ColunaRelatorio('turma', 'Turma'),
            new ColunaRelatorio('responsaveis', 'Responsáveis financeiros', sensivel: true),
            new ColunaRelatorio('vencimento', 'Vencimento', TipoColunaRelatorio::Data),
            new ColunaRelatorio('dias_atraso', 'Dias em atraso', TipoColunaRelatorio::Inteiro),
            new ColunaRelatorio('valor', 'Valor', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('valor_pago', 'Já pago', TipoColunaRelatorio::Moeda, padrao: false),
            new ColunaRelatorio('saldo', 'Saldo devedor', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('status', 'Situação', padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('turma', 'Turma', 'opcao', Turma::query()->orderBy('nome')->limit(300)->pluck('nome', 'id')->map(fn ($nome): string => (string) $nome)->all()),
            new FiltroRelatorio('atraso_minimo', 'Atraso mínimo (dias)', 'numero'),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = Fatura::query()
            ->emAtraso()
            ->with(['itens', 'transacoes', 'contrato.matricula.pessoa', 'contrato.matricula.turma', 'contrato.responsaveisFinanceiros.pessoa'])
            ->orderBy('vencimento')
            ->orderBy('id');

        if (filled($filtros['turma'] ?? null)) {
            $consulta->whereHas('contrato.matricula', fn ($q) => $q->where('turma_id', (int) $filtros['turma']));
        }

        $minimo = max(0, (int) ($filtros['atraso_minimo'] ?? 0));

        if ($minimo > 0) {
            $consulta->whereDate('vencimento', '<=', today()->subDays($minimo)->toDateString());
        }

        return $consulta->limit($limite)->get()->map(function (Fatura $fatura): array {
            $matricula = $fatura->contrato?->matricula;

            return [
                'fatura' => $fatura->id,
                'aluno' => $matricula?->pessoa?->nome,
                'turma' => $matricula?->turma?->nome,
                'responsaveis' => $fatura->contrato?->responsaveisFinanceiros->map(fn ($r) => $r->pessoa?->nome)->filter()->implode(', ') ?: null,
                'vencimento' => $fatura->vencimento,
                'dias_atraso' => max(0, (int) $fatura->vencimento->diffInDays(today(), false)),
                'valor' => round((float) $fatura->valor, 2),
                'valor_pago' => round((float) $fatura->valor_pago, 2),
                'saldo' => round((float) $fatura->valor_restante, 2),
                'status' => $fatura->status->getLabel(),
            ];
        })->all();
    }
}
