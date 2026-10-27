<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Models\RequisicaoCompra;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requisições enviadas pelos setores que esperam as propostas dos fornecedores (Onda 38). É a fila de quem compra. Fica atrasada
 * a urgente e a que espera cotação há mais dias que o prazo.
 */
final class ComprasParaCotar implements FontePendencia
{
    public function chave(): string
    {
        return 'compras_para_cotar';
    }

    public function titulo(): string
    {
        return 'Compras para cotar';
    }

    public function area(): string
    {
        return 'Suprimentos';
    }

    public function icone(): string
    {
        return 'heroicon-o-shopping-cart';
    }

    public function orientacao(): string
    {
        return 'Peça propostas aos fornecedores e registre-as na requisição; depois escolha a cotação para abrir o pedido.';
    }

    public function permissao(): string
    {
        return 'Cotar:RequisicaoCompra';
    }

    public function url(): string
    {
        return RequisicaoCompraResource::getUrl('index', ['filters[status][values][0]' => StatusRequisicaoCompra::EmCotacao->value]);
    }

    /**
     * @return Builder<RequisicaoCompra>
     */
    private function consulta(): Builder
    {
        return RequisicaoCompra::query()->where('status', StatusRequisicaoCompra::EmCotacao->value);
    }

    private function atrasada(RequisicaoCompra $requisicao): bool
    {
        $prazo = max(1, (int) config('compras.mesa.cotacao_atrasada_dias', 3));

        return $requisicao->urgente() || ($requisicao->enviada_em !== null && $requisicao->enviada_em->lt(now()->subDays($prazo)));
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        $prazo = max(1, (int) config('compras.mesa.cotacao_atrasada_dias', 3));

        return $this->consulta()->where(fn (Builder $q) => $q->where('urgencia', 'urgente')->orWhere('enviada_em', '<', now()->subDays($prazo)))->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->withCount('cotacoes')
            ->orderByRaw("case when urgencia = 'urgente' then 0 else 1 end")
            ->orderBy('enviada_em')
            ->limit($limite)
            ->get()
            ->map(fn (RequisicaoCompra $requisicao): ItemPendencia => new ItemPendencia(
                titulo: $requisicao->numero.' · '.$requisicao->titulo,
                detalhe: ($requisicao->urgente() ? 'Urgente · ' : '').$requisicao->cotacoes_count.' cotação(ões) registrada(s)',
                desde: $requisicao->enviada_em,
                url: RequisicaoCompraResource::getUrl('view', ['record' => $requisicao]),
                atrasado: $this->atrasada($requisicao),
            ))
            ->all();
    }
}
