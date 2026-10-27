<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContaRecorrente;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Despesas recorrentes (Onda 78): cadastrar, editar e reajustar é do financeiro e da administração.
 */
class ContaRecorrentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContaRecorrente');
    }

    public function view(AuthUser $authUser, ContaRecorrente $modelo): bool
    {
        return $authUser->can('View:ContaRecorrente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContaRecorrente');
    }

    public function update(AuthUser $authUser, ContaRecorrente $modelo): bool
    {
        return $authUser->can('Update:ContaRecorrente');
    }

    public function delete(AuthUser $authUser, ContaRecorrente $modelo): bool
    {
        return $authUser->can('Delete:ContaRecorrente');
    }

    public function reajustar(AuthUser $authUser, ContaRecorrente $modelo): bool
    {
        return $modelo->is_active && $authUser->can('Update:ContaRecorrente');
    }
}
