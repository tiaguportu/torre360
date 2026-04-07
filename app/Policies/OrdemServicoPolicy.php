<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OrdemServico;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrdemServicoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_ordem::servico');
    }

    public function view(AuthUser $authUser, OrdemServico $ordemServico): bool
    {
        return $authUser->can('view_ordem::servico');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_ordem::servico');
    }

    public function update(AuthUser $authUser, OrdemServico $ordemServico): bool
    {
        return $authUser->can('update_ordem::servico');
    }

    public function delete(AuthUser $authUser, OrdemServico $ordemServico): bool
    {
        return $authUser->can('delete_ordem::servico');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_ordem::servico');
    }

    public function restore(AuthUser $authUser, OrdemServico $ordemServico): bool
    {
        return $authUser->can('Restore:OrdemServico');
    }

    public function forceDelete(AuthUser $authUser, OrdemServico $ordemServico): bool
    {
        return $authUser->can('ForceDelete:OrdemServico');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrdemServico');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrdemServico');
    }

    public function replicate(AuthUser $authUser, OrdemServico $ordemServico): bool
    {
        return $authUser->can('Replicate:OrdemServico');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrdemServico');
    }

}