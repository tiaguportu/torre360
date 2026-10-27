<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Services\FluxoProjetadoService;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;

/**
 * Fluxo de caixa projetado, semana a semana (Onda 78): o mesmo cálculo da tela, para levar à reunião da direção ou agendar o envio.
 */
final class FluxoProjetadoSemanal implements FonteDeRelatorio
{
    use IndexaItens;

    public function chave(): string
    {
        return 'fluxo_projetado';
    }

    public function titulo(): string
    {
        return 'Fluxo de caixa projetado';
    }

    public function descricao(): string
    {
        return 'Entradas, saídas e saldo previstos semana a semana, no cenário e no horizonte escolhidos. É estimativa.';
    }

    public function permissao(): string
    {
        return 'View:FluxoProjetado';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('semana', 'Semana'),
            new ColunaRelatorio('inicio', 'De', TipoColunaRelatorio::Data),
            new ColunaRelatorio('fim', 'Até', TipoColunaRelatorio::Data),
            new ColunaRelatorio('entradas', 'Entradas previstas', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('saidas', 'Saídas previstas', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('saldo', 'Saldo no fim', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('situacao', 'Situação'),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('cenario', 'Cenário de recebimento', 'opcao', FluxoProjetadoService::CENARIOS),
            new FiltroRelatorio('dias', 'Horizonte', 'opcao', ['30' => '30 dias', '60' => '60 dias', '90' => '90 dias', '180' => '180 dias']),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $projecao = app(FluxoProjetadoService::class)->projetar(null, (int) ($filtros['dias'] ?? 90), (string) ($filtros['cenario'] ?? 'esperado'));

        return collect($projecao['semanas'])->take($limite)->map(fn (array $semana, int $indice): array => [
            'semana' => 'Semana '.($indice + 1),
            'inicio' => $semana['inicio'],
            'fim' => $semana['fim'],
            'entradas' => $semana['entradas'],
            'saidas' => $semana['saidas'],
            'saldo' => $semana['saldo_final'],
            'situacao' => $semana['negativa'] ? 'Saldo negativo' : 'Positivo',
        ])->values()->all();
    }
}
