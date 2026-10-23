<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Como a escola quer que o escritório de contabilidade enxergue o período (Onda 73). O regime é decisão do escritório: o
 * sistema só entrega os dados do jeito que cada um pede.
 */
enum RegimeContabil: string implements HasLabel
{
    case Caixa = 'caixa';
    case Competencia = 'competencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Caixa => 'Regime de caixa (o que entrou e saiu do banco)',
            self::Competencia => 'Regime de competência (faturas e contas a pagar do mês, mais a baixa no banco)',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Caixa => 'Um lançamento por movimento bancário: recebimento contra a conta de receita, pagamento contra a conta de despesa.',
            self::Competencia => 'Faturas com vencimento no período contra clientes e contas a pagar com vencimento no período contra fornecedores; o banco baixa clientes e fornecedores.',
        };
    }
}
