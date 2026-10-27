<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Models\PedidoCompra;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pedidos aprovados que esperam a entrega ou a conferência (Onda 38). Fica atrasado o pedido cuja entrega prometida na cotação já
 * passou (ou, sem prazo na cotação, o aprovado há mais dias que o limite). Com a segregação de funções ligada, quem aprovou o pedido
 * não o recebe, então ele não aparece na fila dessa pessoa.
 */
final class PedidosParaReceber implements FontePendencia
{
    public function chave(): string
    {
        return 'pedidos_para_receber';
    }

    public function titulo(): string
    {
        return 'Compras para receber';
    }

    public function area(): string
    {
        return 'Suprimentos';
    }

    public function icone(): string
    {
        return 'heroicon-o-inbox-arrow-down';
    }

    public function orientacao(): string
    {
        return 'Confira a entrega, registre o que chegou com a nota fiscal e, se algo veio errado, marque a divergência.';
    }

    public function permissao(): string
    {
        return 'Receber:PedidoCompra';
    }

    public function url(): string
    {
        return RequisicaoCompraResource::getUrl('index', ['filters[status][values][0]' => StatusRequisicaoCompra::Aprovada->value, 'filters[status][values][1]' => StatusRequisicaoCompra::RecebidaParcial->value]);
    }

    /**
     * @return Builder<PedidoCompra>
     */
    private function consulta(User $usuario): Builder
    {
        return PedidoCompra::query()
            ->whereHas('requisicao', fn (Builder $q) => $q->whereIn('status', [StatusRequisicaoCompra::Aprovada->value, StatusRequisicaoCompra::RecebidaParcial->value]))
            ->when(config('compras.segregar_funcoes', true), fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('aprovado_por_user_id')->orWhere('aprovado_por_user_id', '!=', $usuario->id)));
    }

    private function atrasado(PedidoCompra $pedido): bool
    {
        if ($pedido->aprovado_em === null) {
            return false;
        }

        $prazo = $pedido->cotacao?->prazo_entrega_dias ?? max(1, (int) config('compras.mesa.entrega_atrasada_dias', 15));

        return $pedido->aprovado_em->copy()->addDays((int) $prazo)->lt(now()->startOfDay());
    }

    /**
     * @return Collection<int, PedidoCompra>
     */
    private function pedidos(User $usuario): Collection
    {
        return $this->consulta($usuario)->with(['requisicao', 'fornecedor', 'cotacao'])->orderBy('aprovado_em')->get();
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->pedidos($usuario)->filter(fn (PedidoCompra $p): bool => $this->atrasado($p))->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->pedidos($usuario)
            ->sortByDesc(fn (PedidoCompra $p): int => $this->atrasado($p) ? 1 : 0)
            ->take($limite)
            ->map(fn (PedidoCompra $pedido): ItemPendencia => new ItemPendencia(
                titulo: $pedido->numero.' · '.$pedido->requisicao->titulo,
                detalhe: ($pedido->fornecedor?->razao_social ?? 'Fornecedor').' · R$ '.number_format((float) $pedido->valor_total, 2, ',', '.').($pedido->requisicao->status === StatusRequisicaoCompra::RecebidaParcial ? ' · entrega parcial' : ''),
                desde: $pedido->aprovado_em,
                url: RequisicaoCompraResource::getUrl('view', ['record' => $pedido->requisicao]),
                atrasado: $this->atrasado($pedido),
            ))
            ->values()
            ->all();
    }
}
