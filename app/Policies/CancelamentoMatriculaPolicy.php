<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CancelamentoMatricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A secretaria registra, decide e conclui; a coordenação consulta. Sem edição nem exclusão: o pedido tem histórico e
 * mexe em matrícula e faturas. As habilidades `aprovar` e `concluir` carregam o estado do pedido porque o super admin
 * passa por qualquer permissão (Gate::before) e não pode, por exemplo, concluir um pedido que nem foi aprovado.
 */
class CancelamentoMatriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CancelamentoMatricula');
    }

    public function view(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return $authUser->can('View:CancelamentoMatricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CancelamentoMatricula');
    }

    public function update(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return false;
    }

    public function aprovar(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return $authUser->can('Aprovar:CancelamentoMatricula') && $cancelamento->estaSolicitado();
    }

    public function anexarDistrato(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return $authUser->can('Aprovar:CancelamentoMatricula') && $cancelamento->estaAprovado();
    }

    public function encerrar(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return $authUser->can('Aprovar:CancelamentoMatricula') && $cancelamento->estaAberto();
    }

    public function concluir(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return $authUser->can('Concluir:CancelamentoMatricula') && $cancelamento->estaAprovado();
    }

    public function registrarDevolucao(AuthUser $authUser, CancelamentoMatricula $cancelamento): bool
    {
        return $authUser->can('Concluir:CancelamentoMatricula') && $cancelamento->devolucaoPendente();
    }
}
