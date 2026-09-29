<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GradeHorario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GradeHorarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GradeHorario');
    }

    public function view(AuthUser $authUser, GradeHorario $gradeHorario): bool
    {
        return $authUser->can('View:GradeHorario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GradeHorario');
    }

    public function update(AuthUser $authUser, GradeHorario $gradeHorario): bool
    {
        return $authUser->can('Update:GradeHorario');
    }

    public function delete(AuthUser $authUser, GradeHorario $gradeHorario): bool
    {
        return $authUser->can('Delete:GradeHorario');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GradeHorario');
    }

    public function restore(AuthUser $authUser, GradeHorario $gradeHorario): bool
    {
        return $authUser->can('Restore:GradeHorario');
    }

    public function forceDelete(AuthUser $authUser, GradeHorario $gradeHorario): bool
    {
        return $authUser->can('ForceDelete:GradeHorario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GradeHorario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GradeHorario');
    }

    public function replicate(AuthUser $authUser, GradeHorario $gradeHorario): bool
    {
        return $authUser->can('Replicate:GradeHorario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GradeHorario');
    }
}
