<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReservaEspaco;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Todos os papéis com acesso veem todas as reservas (é preciso saber o que já está ocupado antes de pedir), mas
 * só o solicitante altera ou cancela a própria reserva. Quem pode aprovar (`Confirmar:ReservaEspaco`) gerencia
 * as de qualquer pessoa.
 */
class ReservaEspacoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReservaEspaco');
    }

    public function view(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        return $authUser->can('View:ReservaEspaco');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReservaEspaco');
    }

    public function update(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        return $authUser->can('Update:ReservaEspaco')
            && ! $reserva->estaCancelada()
            && $this->ehDonoOuAprovador($authUser, $reserva);
    }

    public function delete(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        return $authUser->can('Delete:ReservaEspaco');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReservaEspaco');
    }

    public function confirmar(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        return $authUser->can('Confirmar:ReservaEspaco') && $reserva->aguardaAprovacao();
    }

    public function cancelar(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        if ($reserva->estaCancelada()) {
            return false;
        }

        if ($authUser->can('Confirmar:ReservaEspaco')) {
            return true;
        }

        return $authUser->can('Update:ReservaEspaco') && $this->ehDono($authUser, $reserva);
    }

    private function ehDono(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        return $reserva->solicitante_user_id !== null && (int) $reserva->solicitante_user_id === (int) $authUser->getAuthIdentifier();
    }

    private function ehDonoOuAprovador(AuthUser $authUser, ReservaEspaco $reserva): bool
    {
        return $this->ehDono($authUser, $reserva) || $authUser->can('Confirmar:ReservaEspaco');
    }
}
