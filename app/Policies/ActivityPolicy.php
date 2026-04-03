<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_activity_log');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('view_activity_log');
    }

    public function create(User $user): bool
    {
        return $user->can('create_activity_log');
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can('update_activity_log');
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->can('delete_activity_log');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_activity_log');
    }

    public function restore(User $user, Activity $activity): bool
    {
        return $user->can('restore_activity_log');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_activity_log');
    }

    public function replicate(User $user, Activity $activity): bool
    {
        return $user->can('replicate_activity_log');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_activity_log');
    }

    public function forceDelete(User $user, Activity $activity): bool
    {
        return $user->can('force_delete_activity_log');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_activity_log');
    }
}
