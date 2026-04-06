<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmailLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmailLog');
    }

    public function view(AuthUser $authUser, EmailLog $emailLog): bool
    {
        return $authUser->can('View:EmailLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return false; // Logs are read-only
    }

    public function update(AuthUser $authUser, EmailLog $emailLog): bool
    {
        return false; // Logs are read-only
    }

    public function delete(AuthUser $authUser, EmailLog $emailLog): bool
    {
        return $authUser->can('Delete:EmailLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmailLog');
    }

    public function restore(AuthUser $authUser, EmailLog $emailLog): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, EmailLog $emailLog): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, EmailLog $emailLog): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
