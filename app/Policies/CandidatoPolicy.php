<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Candidato;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CandidatoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Candidato');
    }

    public function view(AuthUser $authUser, Candidato $candidato): bool
    {
        return $authUser->can('View:Candidato');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Candidato');
    }

    public function update(AuthUser $authUser, Candidato $candidato): bool
    {
        return $authUser->can('Update:Candidato');
    }

    public function delete(AuthUser $authUser, Candidato $candidato): bool
    {
        return $authUser->can('Delete:Candidato');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Candidato');
    }
}
