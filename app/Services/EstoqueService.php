<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\CentroCusto;
use App\Models\ItemEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Estoque de consumíveis (Onda 16, fase 2): entrada, saída e ajuste de inventário. Todo movimento trava a linha do item, ajusta o
 * saldo e o custo médio e grava a movimentação com o saldo depois dela. Saldo negativo não existe: a saída recusa o que falta.
 *
 * O custo médio é móvel: cada entrada com custo informado recalcula a média ponderada com o saldo que havia. Saída e ajuste não
 * mudam o custo unitário.
 */
class EstoqueService
{
    /**
     * @throws \DomainException
     */
    public function entrada(ItemEstoque $item, float $quantidade, ?float $custoUnitario = null, ?Model $origem = null, ?User $por = null, ?string $motivo = null): MovimentacaoEstoque
    {
        $this->exigirPositivo($quantidade);

        if ($custoUnitario !== null && $custoUnitario < 0) {
            throw new \DomainException('O custo unitário não pode ser negativo.');
        }

        return DB::transaction(function () use ($item, $quantidade, $custoUnitario, $origem, $por, $motivo): MovimentacaoEstoque {
            $travado = $this->travar($item);
            $saldoAnterior = (float) $travado->quantidade_atual;
            $novoSaldo = round($saldoAnterior + $quantidade, 3);

            $atualizacao = ['quantidade_atual' => $novoSaldo];

            if ($custoUnitario !== null) {
                $atualizacao['custo_medio'] = $saldoAnterior <= 0
                    ? round($custoUnitario, 4)
                    : round((($saldoAnterior * (float) $travado->custo_medio) + ($quantidade * $custoUnitario)) / $novoSaldo, 4);
            }

            $travado->update($atualizacao);

            return $this->registrar($travado, TipoMovimentacaoEstoque::Entrada, $quantidade, $novoSaldo, $custoUnitario, null, $motivo, $origem, $por);
        });
    }

    /**
     * @throws \DomainException
     */
    public function saida(ItemEstoque $item, float $quantidade, ?CentroCusto $centroCusto = null, ?string $motivo = null, ?User $por = null): MovimentacaoEstoque
    {
        $this->exigirPositivo($quantidade);

        return DB::transaction(function () use ($item, $quantidade, $centroCusto, $motivo, $por): MovimentacaoEstoque {
            $travado = $this->travar($item);
            $saldoAnterior = (float) $travado->quantidade_atual;

            if ($quantidade > $saldoAnterior) {
                $disponivel = rtrim(rtrim(number_format($saldoAnterior, 3, ',', ''), '0'), ',');

                throw new \DomainException("Saldo insuficiente de \"{$travado->nome}\": há {$disponivel} {$travado->unidade_medida} e a saída pede mais.");
            }

            $novoSaldo = round($saldoAnterior - $quantidade, 3);
            $travado->update(['quantidade_atual' => $novoSaldo]);

            return $this->registrar($travado, TipoMovimentacaoEstoque::Saida, -$quantidade, $novoSaldo, (float) $travado->custo_medio, $centroCusto, $motivo, null, $por);
        });
    }

    /**
     * Acerta o saldo ao que foi contado. O motivo é obrigatório: o ajuste é a única forma de mudar o saldo sem documento.
     *
     * @throws \DomainException
     */
    public function ajustar(ItemEstoque $item, float $contado, string $motivo, ?User $por = null): MovimentacaoEstoque
    {
        if ($contado < 0) {
            throw new \DomainException('A contagem não pode ser negativa.');
        }

        if (trim($motivo) === '') {
            throw new \DomainException('Informe o motivo do ajuste (contagem de inventário, perda, quebra...).');
        }

        return DB::transaction(function () use ($item, $contado, $motivo, $por): MovimentacaoEstoque {
            $travado = $this->travar($item);
            $diferenca = round($contado - (float) $travado->quantidade_atual, 3);

            if ($diferenca === 0.0) {
                throw new \DomainException('A contagem é igual ao saldo do sistema: não há o que ajustar.');
            }

            $travado->update(['quantidade_atual' => round($contado, 3)]);

            return $this->registrar($travado, TipoMovimentacaoEstoque::Ajuste, $diferenca, round($contado, 3), null, null, $motivo, null, $por);
        });
    }

    /**
     * Valor total em estoque (saldo × custo médio de cada item ativo).
     */
    public function valorTotal(): float
    {
        return round((float) ItemEstoque::query()->where('is_active', true)->selectRaw('COALESCE(SUM(quantidade_atual * custo_medio), 0) as total')->value('total'), 2);
    }

    private function exigirPositivo(float $quantidade): void
    {
        if ($quantidade <= 0) {
            throw new \DomainException('A quantidade precisa ser maior que zero.');
        }
    }

    private function travar(ItemEstoque $item): ItemEstoque
    {
        /** @var ItemEstoque $travado */
        $travado = ItemEstoque::query()->lockForUpdate()->findOrFail($item->getKey());

        if (! $travado->is_active) {
            throw new \DomainException("O item \"{$travado->nome}\" está inativo: reative-o para movimentar.");
        }

        return $travado;
    }

    private function registrar(ItemEstoque $item, TipoMovimentacaoEstoque $tipo, float $quantidade, float $saldoApos, ?float $custoUnitario, ?CentroCusto $centro, ?string $motivo, ?Model $origem, ?User $por): MovimentacaoEstoque
    {
        $movimentacao = MovimentacaoEstoque::create([
            'item_estoque_id' => $item->id,
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'saldo_apos' => $saldoApos,
            'custo_unitario' => $custoUnitario,
            'centro_custo_id' => $centro?->id,
            'motivo' => $motivo !== null && trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 160) : null,
            'origem_type' => $origem ? $origem::class : null,
            'origem_id' => $origem?->getKey(),
            'user_id' => $por?->id,
        ]);

        $item->refresh();

        return $movimentacao;
    }
}
