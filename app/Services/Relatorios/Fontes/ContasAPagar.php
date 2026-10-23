<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\StatusContaPagar;
use App\Enums\TipoColunaRelatorio;
use App\Models\ContaPagar;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltraPeriodo;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Contas a pagar por vencimento, com fornecedor, plano de contas e centro de custo.
 */
final class ContasAPagar implements FonteDeRelatorio
{
    use FiltraPeriodo;
    use IndexaItens;

    public function chave(): string
    {
        return 'contas_a_pagar';
    }

    public function titulo(): string
    {
        return 'Contas a pagar';
    }

    public function descricao(): string
    {
        return 'Contas a pagar por vencimento, com fornecedor, plano de contas e centro de custo.';
    }

    public function permissao(): string
    {
        return 'ViewAny:ContaPagar';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('conta', 'Conta', TipoColunaRelatorio::Inteiro, padrao: false),
            new ColunaRelatorio('descricao', 'Descrição'),
            new ColunaRelatorio('fornecedor', 'Fornecedor'),
            new ColunaRelatorio('vencimento', 'Vencimento', TipoColunaRelatorio::Data),
            new ColunaRelatorio('valor', 'Valor', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('status', 'Situação'),
            new ColunaRelatorio('pagamento', 'Pago em', TipoColunaRelatorio::Data, padrao: false),
            new ColunaRelatorio('plano_conta', 'Conta do plano', padrao: false),
            new ColunaRelatorio('centro_custo', 'Centro de custo', padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('vencimento', 'Vencimento', 'data'),
            new FiltroRelatorio('status', 'Situação', 'opcao', collect(StatusContaPagar::cases())->mapWithKeys(fn (StatusContaPagar $s): array => [$s->value => (string) $s->getLabel()])->all()),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = ContaPagar::query()
            ->with(['fornecedor', 'planoConta', 'centroCusto'])
            ->orderBy('vencimento')
            ->orderBy('id');

        $this->aplicarPeriodo($consulta, 'vencimento', $filtros, 'vencimento');

        if (filled($filtros['status'] ?? null) && StatusContaPagar::tryFrom((string) $filtros['status'])) {
            $consulta->where('status', (string) $filtros['status']);
        }

        return $consulta->limit($limite)->get()->map(fn (ContaPagar $conta): array => [
            'conta' => $conta->id,
            'descricao' => $conta->descricao,
            'fornecedor' => $conta->fornecedor?->razao_social,
            'vencimento' => $conta->vencimento,
            'valor' => round((float) $conta->valor, 2),
            'status' => $conta->status->getLabel(),
            'pagamento' => $conta->data_pagamento,
            'plano_conta' => $conta->planoConta ? "{$conta->planoConta->codigo} - {$conta->planoConta->nome}" : null,
            'centro_custo' => $conta->centroCusto?->nome,
        ])->all();
    }
}
