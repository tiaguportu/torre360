<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ComunicacaoEmMassa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ComunicacaoEmMassaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ComunicacaoEmMassa');
    }

    public function view(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('View:ComunicacaoEmMassa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ComunicacaoEmMassa');
    }

    public function update(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('Update:ComunicacaoEmMassa');
    }

    public function delete(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('Delete:ComunicacaoEmMassa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ComunicacaoEmMassa');
    }

    public function restore(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('Restore:ComunicacaoEmMassa');
    }

    public function forceDelete(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('ForceDelete:ComunicacaoEmMassa');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ComunicacaoEmMassa');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ComunicacaoEmMassa');
    }

    public function replicate(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('Replicate:ComunicacaoEmMassa');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ComunicacaoEmMassa');
    }

    public function enviar(AuthUser $authUser, ComunicacaoEmMassa $comunicacaoEmMassa): bool
    {
        return $authUser->can('Enviar:ComunicacaoEmMassa');
    }
}
