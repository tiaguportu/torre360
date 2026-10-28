<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotificacaoConselhoTutelar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Notificações de faltas excessivas ao Conselho Tutelar (Onda 57): dado de criança e de situação de risco. Sem esta política o
 * Filament libera o recurso a qualquer conta ativa (inclusive a da família, que entra no painel admin); com ela vale a permissão.
 */
class NotificacaoConselhoTutelarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotificacaoConselhoTutelar');
    }

    public function view(AuthUser $authUser, NotificacaoConselhoTutelar $notificacao): bool
    {
        return $authUser->can('View:NotificacaoConselhoTutelar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotificacaoConselhoTutelar');
    }

    public function update(AuthUser $authUser, NotificacaoConselhoTutelar $notificacao): bool
    {
        return $authUser->can('Update:NotificacaoConselhoTutelar');
    }

    public function delete(AuthUser $authUser, NotificacaoConselhoTutelar $notificacao): bool
    {
        return $authUser->can('Delete:NotificacaoConselhoTutelar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NotificacaoConselhoTutelar');
    }
}
