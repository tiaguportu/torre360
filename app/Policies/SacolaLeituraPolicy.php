<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SacolaLeitura;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SacolaLeituraPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SacolaLeitura');
    }

    public function view(AuthUser $authUser, SacolaLeitura $sacola): bool
    {
        return $authUser->can('View:SacolaLeitura');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SacolaLeitura');
    }

    public function update(AuthUser $authUser, SacolaLeitura $sacola): bool
    {
        return $authUser->can('Update:SacolaLeitura');
    }

    public function delete(AuthUser $authUser, SacolaLeitura $sacola): bool
    {
        return $authUser->can('Delete:SacolaLeitura');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SacolaLeitura');
    }
}
