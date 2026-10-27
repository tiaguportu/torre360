<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Pages\RentabilidadeRealizada;
use App\Services\RentabilidadeTurmaService;
use App\Support\Painel\ContextoPainel;

final class MargemDasTurmas extends IndicadorBase
{
    public function chave(): string
    {
        return 'margem_das_turmas';
    }

    public function titulo(): string
    {
        return 'Margem das turmas (mês anterior)';
    }

    public function grupo(): string
    {
        return 'Financeiro';
    }

    public function formato(): string
    {
        return 'percentual';
    }

    public function melhor(): string
    {
        return 'maior';
    }

    public function permissao(): string
    {
        return 'View:RentabilidadeRealizada';
    }

    public function descricao(): string
    {
        return 'Resultado sobre o faturado de todas as turmas no último mês completo, com o rateio padrão da escola (contabilidade gerencial).';
    }

    public function suportaUnidade(): bool
    {
        return true;
    }

    public function url(): ?string
    {
        return RentabilidadeRealizada::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $mes = $contexto->hoje->startOfMonth()->subMonthNoOverflow();
        $resultado = app(RentabilidadeTurmaService::class)->calcular($mes->year, $mes->month, (string) config('financeiro.rentabilidade.criterio_padrao', 'aluno'), $contexto->daUnidade());

        return $resultado['totais']['margem'];
    }
}
