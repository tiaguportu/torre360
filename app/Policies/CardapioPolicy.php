<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cardapio;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CardapioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Cardapio');
    }

    public function view(AuthUser $authUser, Cardapio $cardapio): bool
    {
        return $authUser->can('View:Cardapio');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Cardapio');
    }

    public function update(AuthUser $authUser, Cardapio $cardapio): bool
    {
        return $authUser->can('Update:Cardapio');
    }

    public function delete(AuthUser $authUser, Cardapio $cardapio): bool
    {
        return $authUser->can('Delete:Cardapio');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Cardapio');
    }

    public function publicar(AuthUser $authUser, Cardapio $cardapio): bool
    {
        return $authUser->can('Publicar:Cardapio');
    }

    /**
     * Ver quais alunos têm restrição alimentar (dado de saúde) é uma permissão à parte da gestão do cardápio.
     */
    public function verAlergicos(AuthUser $authUser): bool
    {
        return $authUser->can('VerAlergicos:Cardapio');
    }
}
