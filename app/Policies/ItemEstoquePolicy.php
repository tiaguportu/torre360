<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ItemEstoque;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Cadastrar e consultar itens é de quem cuida do estoque; mexer no saldo (entrada, saída, ajuste) é uma permissão à parte,
 * `Movimentar`, porque é ela que altera o inventário.
 */
class ItemEstoquePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ItemEstoque');
    }

    public function view(AuthUser $authUser, ItemEstoque $item): bool
    {
        return $authUser->can('View:ItemEstoque');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ItemEstoque');
    }

    public function update(AuthUser $authUser, ItemEstoque $item): bool
    {
        return $authUser->can('Update:ItemEstoque');
    }

    public function delete(AuthUser $authUser, ItemEstoque $item): bool
    {
        return $authUser->can('Delete:ItemEstoque') && ! $item->movimentacoes()->exists();
    }

    public function movimentar(AuthUser $authUser, ItemEstoque $item): bool
    {
        return $authUser->can('Movimentar:ItemEstoque') && $item->is_active;
    }
}
