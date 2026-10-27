<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reuniao;
use App\Models\ReuniaoEncaminhamento;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * O encaminhamento de uma reunião. Quem vê a reunião vê os encaminhamentos; o responsável também, mesmo sem ter participado. Concluir
 * é do responsável ou de quem gerencia a reunião; criar e cancelar, de quem gerencia.
 */
class ReuniaoEncaminhamentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Reuniao');
    }

    public function view(AuthUser $authUser, ReuniaoEncaminhamento $encaminhamento): bool
    {
        return $this->ehResponsavel($authUser, $encaminhamento) || $this->daReuniao($authUser, $encaminhamento, 'view');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Reuniao');
    }

    public function update(AuthUser $authUser, ReuniaoEncaminhamento $encaminhamento): bool
    {
        return $encaminhamento->estaAberto()
            && ($this->ehResponsavel($authUser, $encaminhamento) || $this->daReuniao($authUser, $encaminhamento, 'gerenciar'));
    }

    public function delete(AuthUser $authUser, ReuniaoEncaminhamento $encaminhamento): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    private function ehResponsavel(AuthUser $authUser, ReuniaoEncaminhamento $encaminhamento): bool
    {
        return $encaminhamento->responsavel_user_id !== null && (int) $encaminhamento->responsavel_user_id === (int) $authUser->getAuthIdentifier();
    }

    private function daReuniao(AuthUser $authUser, ReuniaoEncaminhamento $encaminhamento, string $habilidade): bool
    {
        $reuniao = Reuniao::query()->find($encaminhamento->reuniao_id);

        return $reuniao !== null && $authUser->can($habilidade, $reuniao);
    }
}
