<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Models\PedidoCompra;
use App\Models\User;
use App\Services\ComprasService;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pedidos de compra que esperam a decisão de quem tem a alçada do valor (Onda 38). Cada pessoa vê só o que pode aprovar: o nível do
 * pedido não passa do dela e, com a segregação de funções ligada, os pedidos que ela mesma solicitou ficam de fora.
 */
final class PedidosParaAprovar implements FontePendencia
{
    public function chave(): string
    {
        return 'pedidos_para_aprovar';
    }

    public function titulo(): string
    {
        return 'Pedidos para aprovar';
    }

    public function area(): string
    {
        return 'Suprimentos';
    }

    public function icone(): string
    {
        return 'heroicon-o-check-badge';
    }

    public function orientacao(): string
    {
        return 'Abra o pedido, confira o mapa de cotações e o saldo do orçamento da conta e aprove ou recuse.';
    }

    public function permissao(): string
    {
        return 'ViewAny:RequisicaoCompra';
    }

    public function url(): string
    {
        return RequisicaoCompraResource::getUrl('index', ['filters[status][values][0]' => StatusRequisicaoCompra::AguardandoAprovacao->value]);
    }

    /**
     * @return Builder<PedidoCompra>
     */
    private function consulta(User $usuario): Builder
    {
        $nivel = app(ComprasService::class)->nivelDeAlcada($usuario);

        return PedidoCompra::query()
            ->whereHas('requisicao', function (Builder $q) use ($usuario): void {
                $q->where('status', StatusRequisicaoCompra::AguardandoAprovacao->value);

                if (config('compras.segregar_funcoes', true)) {
                    $q->where('solicitante_user_id', '!=', $usuario->id);
                }
            })
            ->where('nivel_alcada', '<=', $nivel);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        $prazo = max(1, (int) config('compras.mesa.aprovacao_atrasada_dias', 2));

        return $this->consulta($usuario)->where('created_at', '<', now()->subDays($prazo))->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $prazo = max(1, (int) config('compras.mesa.aprovacao_atrasada_dias', 2));

        return $this->consulta($usuario)
            ->with(['requisicao', 'fornecedor'])
            ->orderBy('created_at')
            ->limit($limite)
            ->get()
            ->map(fn (PedidoCompra $pedido): ItemPendencia => new ItemPendencia(
                titulo: $pedido->numero.' · '.$pedido->requisicao->titulo,
                detalhe: ($pedido->fornecedor?->razao_social ?? 'Fornecedor').' · R$ '.number_format((float) $pedido->valor_total, 2, ',', '.'),
                desde: $pedido->created_at,
                url: RequisicaoCompraResource::getUrl('view', ['record' => $pedido->requisicao]),
                atrasado: $pedido->created_at !== null && $pedido->created_at->lt(now()->subDays($prazo)),
            ))
            ->all();
    }
}
