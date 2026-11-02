<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Denuncia;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ouvidoria (Ondas 35 e 63). Ler exige a permissão e pertencer ao comitê da categoria (ou ter `VerTodas:Denuncia`).
 * Ninguém cria, edita nem apaga o relato pelo painel: ele só muda por movimentações registradas.
 */
class DenunciaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Denuncia');
    }

    public function view(AuthUser $authUser, Denuncia $denuncia): bool
    {
        return $authUser->can('View:Denuncia') && $denuncia->podeSerVistaPor($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Denuncia $denuncia): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, Denuncia $denuncia): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Denuncia $denuncia): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Denuncia $denuncia): bool
    {
        return false;
    }
}
