<?php

namespace App\Policies;

use App\Models\AcordoInadimplencia;
use App\Models\User;

class AcordoInadimplenciaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:AcordoInadimplencia');
    }

    public function view(User $user, AcordoInadimplencia $record): bool
    {
        return $user->can('View:AcordoInadimplencia');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:AcordoInadimplencia');
    }

    public function update(User $user, AcordoInadimplencia $record): bool
    {
        return $user->can('Update:AcordoInadimplencia');
    }

    public function delete(User $user, AcordoInadimplencia $record): bool
    {
        return $user->can('Delete:AcordoInadimplencia');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:AcordoInadimplencia');
    }
}
