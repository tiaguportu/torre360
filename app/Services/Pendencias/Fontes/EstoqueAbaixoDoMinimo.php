<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\ItensEstoque\ItemEstoqueResource;
use App\Models\ItemEstoque;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;

/**
 * Itens de estoque no mínimo ou abaixo dele (Onda 16, fase 2): é a hora de abrir uma requisição de compra. Item zerado é atrasado.
 * A consulta é a do filtro "Abaixo do mínimo" da lista de estoque.
 */
final class EstoqueAbaixoDoMinimo implements FontePendencia
{
    public function chave(): string
    {
        return 'estoque_abaixo_do_minimo';
    }

    public function titulo(): string
    {
        return 'Estoque abaixo do mínimo';
    }

    public function area(): string
    {
        return 'Suprimentos';
    }

    public function icone(): string
    {
        return 'heroicon-o-archive-box';
    }

    public function orientacao(): string
    {
        return 'Abra uma requisição de compra para repor, ou ajuste o mínimo do item se ele mudou de uso.';
    }

    public function permissao(): string
    {
        return 'ViewAny:ItemEstoque';
    }

    public function url(): string
    {
        return ItemEstoqueResource::getUrl('index', ['filters[abaixo_do_minimo][isActive]' => 'true']);
    }

    public function contar(User $usuario): int
    {
        return ItemEstoque::query()->abaixoDoMinimo()->count();
    }

    public function atrasados(User $usuario): int
    {
        return ItemEstoque::query()->abaixoDoMinimo()->where('quantidade_atual', '<=', 0)->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return ItemEstoque::query()
            ->abaixoDoMinimo()
            ->orderBy('quantidade_atual')
            ->orderBy('nome')
            ->limit($limite)
            ->get()
            ->map(fn (ItemEstoque $item): ItemPendencia => new ItemPendencia(
                titulo: $item->nome,
                detalhe: 'Saldo '.rtrim(rtrim(number_format((float) $item->quantidade_atual, 3, ',', ''), '0'), ',').' '.$item->unidade_medida.' · mínimo '.rtrim(rtrim(number_format((float) $item->estoque_minimo, 3, ',', ''), '0'), ',').' '.$item->unidade_medida,
                url: ItemEstoqueResource::getUrl('edit', ['record' => $item]),
                atrasado: (float) $item->quantidade_atual <= 0,
            ))
            ->all();
    }
}
