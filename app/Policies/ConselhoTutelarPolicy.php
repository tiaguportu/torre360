<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConselhoTutelar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConselhoTutelarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConselhoTutelar');
    }

    public function view(AuthUser $authUser, ConselhoTutelar $conselho): bool
    {
        return $authUser->can('View:ConselhoTutelar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConselhoTutelar');
    }

    public function update(AuthUser $authUser, ConselhoTutelar $conselho): bool
    {
        return $authUser->can('Update:ConselhoTutelar');
    }

    public function delete(AuthUser $authUser, ConselhoTutelar $conselho): bool
    {
        return $authUser->can('Delete:ConselhoTutelar');
    }
}
