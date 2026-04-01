<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TributacaoCurso;
use Illuminate\Auth\Access\HandlesAuthorization;

class TributacaoCursoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TributacaoCurso');
    }

    public function view(AuthUser $authUser, TributacaoCurso $tributacaoCurso): bool
    {
        return $authUser->can('View:TributacaoCurso');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TributacaoCurso');
    }

    public function update(AuthUser $authUser, TributacaoCurso $tributacaoCurso): bool
    {
        return $authUser->can('Update:TributacaoCurso');
    }

    public function delete(AuthUser $authUser, TributacaoCurso $tributacaoCurso): bool
    {
        return $authUser->can('Delete:TributacaoCurso');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TributacaoCurso');
    }

    public function restore(AuthUser $authUser, TributacaoCurso $tributacaoCurso): bool
    {
        return $authUser->can('Restore:TributacaoCurso');
    }

    public function forceDelete(AuthUser $authUser, TributacaoCurso $tributacaoCurso): bool
    {
        return $authUser->can('ForceDelete:TributacaoCurso');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TributacaoCurso');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TributacaoCurso');
    }

    public function replicate(AuthUser $authUser, TributacaoCurso $tributacaoCurso): bool
    {
        return $authUser->can('Replicate:TributacaoCurso');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TributacaoCurso');
    }

}