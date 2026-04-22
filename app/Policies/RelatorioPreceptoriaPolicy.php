<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RelatorioPreceptoria;
use Illuminate\Auth\Access\HandlesAuthorization;

class RelatorioPreceptoriaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RelatorioPreceptoria');
    }

    public function view(AuthUser $authUser, RelatorioPreceptoria $relatorioPreceptoria): bool
    {
        return $authUser->can('View:RelatorioPreceptoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RelatorioPreceptoria');
    }

    public function update(AuthUser $authUser, RelatorioPreceptoria $relatorioPreceptoria): bool
    {
        return $authUser->can('Update:RelatorioPreceptoria');
    }

    public function delete(AuthUser $authUser, RelatorioPreceptoria $relatorioPreceptoria): bool
    {
        return $authUser->can('Delete:RelatorioPreceptoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RelatorioPreceptoria');
    }

    public function restore(AuthUser $authUser, RelatorioPreceptoria $relatorioPreceptoria): bool
    {
        return $authUser->can('Restore:RelatorioPreceptoria');
    }

    public function forceDelete(AuthUser $authUser, RelatorioPreceptoria $relatorioPreceptoria): bool
    {
        return $authUser->can('ForceDelete:RelatorioPreceptoria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RelatorioPreceptoria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RelatorioPreceptoria');
    }

    public function replicate(AuthUser $authUser, RelatorioPreceptoria $relatorioPreceptoria): bool
    {
        return $authUser->can('Replicate:RelatorioPreceptoria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RelatorioPreceptoria');
    }

}