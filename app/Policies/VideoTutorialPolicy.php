<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VideoTutorial;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VideoTutorialPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VideoTutorial');
    }

    public function view(AuthUser $authUser, VideoTutorial $videoTutorial): bool
    {
        return $authUser->can('View:VideoTutorial');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VideoTutorial');
    }

    public function update(AuthUser $authUser, VideoTutorial $videoTutorial): bool
    {
        return $authUser->can('Update:VideoTutorial');
    }

    public function delete(AuthUser $authUser, VideoTutorial $videoTutorial): bool
    {
        return $authUser->can('Delete:VideoTutorial');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VideoTutorial');
    }

    public function restore(AuthUser $authUser, VideoTutorial $videoTutorial): bool
    {
        return $authUser->can('Restore:VideoTutorial');
    }

    public function forceDelete(AuthUser $authUser, VideoTutorial $videoTutorial): bool
    {
        return $authUser->can('ForceDelete:VideoTutorial');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VideoTutorial');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VideoTutorial');
    }

    public function replicate(AuthUser $authUser, VideoTutorial $videoTutorial): bool
    {
        return $authUser->can('Replicate:VideoTutorial');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VideoTutorial');
    }
}
