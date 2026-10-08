<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventarioAcervo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InventarioAcervoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventarioAcervo');
    }

    public function view(AuthUser $authUser, InventarioAcervo $inventario): bool
    {
        return $authUser->can('View:InventarioAcervo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventarioAcervo');
    }

    public function update(AuthUser $authUser, InventarioAcervo $inventario): bool
    {
        return $authUser->can('Update:InventarioAcervo');
    }

    public function delete(AuthUser $authUser, InventarioAcervo $inventario): bool
    {
        return $authUser->can('Delete:InventarioAcervo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InventarioAcervo');
    }
}
