<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Livro;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LivroPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Livro');
    }

    public function view(AuthUser $authUser, Livro $livro): bool
    {
        return $authUser->can('View:Livro');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Livro');
    }

    public function update(AuthUser $authUser, Livro $livro): bool
    {
        return $authUser->can('Update:Livro');
    }

    public function delete(AuthUser $authUser, Livro $livro): bool
    {
        return $authUser->can('Delete:Livro');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Livro');
    }
}
