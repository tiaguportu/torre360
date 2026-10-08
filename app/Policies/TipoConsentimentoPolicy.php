<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TipoConsentimento;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TipoConsentimentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TipoConsentimento');
    }

    public function view(AuthUser $authUser, TipoConsentimento $tipoConsentimento): bool
    {
        return $authUser->can('View:TipoConsentimento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TipoConsentimento');
    }

    public function update(AuthUser $authUser, TipoConsentimento $tipoConsentimento): bool
    {
        return $authUser->can('Update:TipoConsentimento');
    }

    public function delete(AuthUser $authUser, TipoConsentimento $tipoConsentimento): bool
    {
        return $authUser->can('Delete:TipoConsentimento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TipoConsentimento');
    }
}
