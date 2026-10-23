<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Fontes;

use App\Contracts\FonteDeRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Models\TransacaoBancaria;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltraPeriodo;
use App\Support\Relatorios\FiltroRelatorio;
use App\Support\Relatorios\IndexaItens;
use Illuminate\Support\Carbon;

/**
 * Entradas e saídas dos bancos, a mesma base do Fluxo de Caixa e da exportação para a contabilidade.
 */
final class MovimentoBancario implements FonteDeRelatorio
{
    use FiltraPeriodo;
    use IndexaItens;

    public function chave(): string
    {
        return 'movimento_bancario';
    }

    public function titulo(): string
    {
        return 'Movimento bancário';
    }

    public function descricao(): string
    {
        return 'Entradas e saídas dos bancos, com conta do plano e centro de custo.';
    }

    public function permissao(): string
    {
        return 'View:RelatorioFluxoCaixa';
    }

    public function colunas(): array
    {
        return $this->indexar([
            new ColunaRelatorio('data', 'Data', TipoColunaRelatorio::Data),
            new ColunaRelatorio('banco', 'Banco'),
            new ColunaRelatorio('tipo', 'Tipo'),
            new ColunaRelatorio('valor', 'Valor', TipoColunaRelatorio::Moeda),
            new ColunaRelatorio('descricao', 'Descrição'),
            new ColunaRelatorio('plano_conta', 'Conta do plano', padrao: false),
            new ColunaRelatorio('centro_custo', 'Centro de custo', padrao: false),
            new ColunaRelatorio('conciliado', 'Conciliado', padrao: false),
        ]);
    }

    public function filtros(): array
    {
        return $this->indexar([
            new FiltroRelatorio('data', 'Data do movimento', 'data'),
            new FiltroRelatorio('tipo', 'Tipo', 'opcao', ['entrada' => 'Entrada', 'saida' => 'Saída']),
        ]);
    }

    public function linhas(array $filtros, int $limite): array
    {
        $consulta = TransacaoBancaria::query()
            ->with(['banco', 'planoConta', 'centroCusto'])
            ->orderBy('data_transacao')
            ->orderBy('id');

        $this->aplicarPeriodo($consulta, 'data_transacao', $filtros, 'data');

        if (in_array($filtros['tipo'] ?? null, ['entrada', 'saida'], true)) {
            $consulta->where('tipo', $filtros['tipo']);
        }

        return $consulta->limit($limite)->get()->map(fn (TransacaoBancaria $transacao): array => [
            'data' => Carbon::parse($transacao->data_transacao),
            'banco' => $transacao->banco?->nome,
            'tipo' => $transacao->tipo === 'entrada' ? 'Entrada' : 'Saída',
            'valor' => round((float) $transacao->valor, 2),
            'descricao' => $transacao->descricao,
            'plano_conta' => $transacao->planoConta ? "{$transacao->planoConta->codigo} - {$transacao->planoConta->nome}" : null,
            'centro_custo' => $transacao->centroCusto?->nome,
            'conciliado' => $transacao->conciliado ? 'Sim' : 'Não',
        ])->all();
    }
}
