<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Pages\RelatorioInadimplencia;
use App\Models\Fatura;
use App\Support\Painel\ContextoPainel;

final class InadimplenciaEmAberto extends IndicadorBase
{
    public function chave(): string
    {
        return 'inadimplencia_em_aberto';
    }

    public function titulo(): string
    {
        return 'Inadimplência em aberto';
    }

    public function grupo(): string
    {
        return 'Financeiro';
    }

    public function formato(): string
    {
        return 'dinheiro';
    }

    public function melhor(): string
    {
        return 'menor';
    }

    public function permissao(): string
    {
        return 'View:RelatorioInadimplencia';
    }

    public function descricao(): string
    {
        return 'Saldo devedor das faturas em atraso (o mesmo "total devido" do relatório de Inadimplência, sem multa e juros).';
    }

    public function url(): ?string
    {
        return RelatorioInadimplencia::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        return round((float) Fatura::query()->emAtraso($contexto->hoje)->with(['itens', 'transacoes'])->get()->sum('valor_restante'), 2);
    }
}
