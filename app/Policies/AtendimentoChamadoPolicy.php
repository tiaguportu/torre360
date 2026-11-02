<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AtendimentoChamado;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AtendimentoChamadoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AtendimentoChamado');
    }

    public function view(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return $authUser->can('View:AtendimentoChamado') && $this->podeVerRestrito($authUser, $atendimentoChamado);
    }

    /**
     * O pedido do titular da LGPD (restrito) é lido só por quem tem a permissão própria, mesmo que veja os outros chamados.
     */
    private function podeVerRestrito(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return ! $atendimentoChamado->restrito || $authUser->can('VerRestritos:AtendimentoChamado');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AtendimentoChamado');
    }

    public function update(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return $authUser->can('Update:AtendimentoChamado') && $this->podeVerRestrito($authUser, $atendimentoChamado);
    }

    public function delete(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return $authUser->can('Delete:AtendimentoChamado');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AtendimentoChamado');
    }

    public function restore(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return $authUser->can('Restore:AtendimentoChamado');
    }

    public function forceDelete(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return $authUser->can('ForceDelete:AtendimentoChamado');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AtendimentoChamado');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AtendimentoChamado');
    }

    public function replicate(AuthUser $authUser, AtendimentoChamado $atendimentoChamado): bool
    {
        return $authUser->can('Replicate:AtendimentoChamado');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AtendimentoChamado');
    }
}
