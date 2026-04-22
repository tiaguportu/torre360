<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\QuestionarioResposta;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionarioRespostaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QuestionarioResposta');
    }

    public function view(AuthUser $authUser, QuestionarioResposta $questionarioResposta): bool
    {
        return $authUser->can('View:QuestionarioResposta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QuestionarioResposta');
    }

    public function update(AuthUser $authUser, QuestionarioResposta $questionarioResposta): bool
    {
        return $authUser->can('Update:QuestionarioResposta');
    }

    public function delete(AuthUser $authUser, QuestionarioResposta $questionarioResposta): bool
    {
        return $authUser->can('Delete:QuestionarioResposta');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:QuestionarioResposta');
    }

    public function restore(AuthUser $authUser, QuestionarioResposta $questionarioResposta): bool
    {
        return $authUser->can('Restore:QuestionarioResposta');
    }

    public function forceDelete(AuthUser $authUser, QuestionarioResposta $questionarioResposta): bool
    {
        return $authUser->can('ForceDelete:QuestionarioResposta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QuestionarioResposta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QuestionarioResposta');
    }

    public function replicate(AuthUser $authUser, QuestionarioResposta $questionarioResposta): bool
    {
        return $authUser->can('Replicate:QuestionarioResposta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QuestionarioResposta');
    }

}