<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusRequisicaoCompra;
use App\Models\RequisicaoCompra;
use App\Services\ComprasService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Compras (Onda 38). Cada pessoa vê as próprias requisições; `VerTodas` abre as dos outros setores. O super admin passa por qualquer
 * habilidade (Gate::before), então as telas também conferem o estado da requisição, e a segregação de funções (quem solicita não
 * aprova, quem aprova não recebe) vem do próprio serviço, que vale para todos.
 */
class RequisicaoCompraPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RequisicaoCompra');
    }

    public function view(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        return $authUser->can('View:RequisicaoCompra') && $this->ehDoUsuarioOuVeTodas($authUser, $requisicao);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RequisicaoCompra');
    }

    public function update(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        return $authUser->can('Update:RequisicaoCompra') && $requisicao->ehRascunho() && $requisicao->solicitante_user_id === $authUser->getAuthIdentifier();
    }

    public function delete(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        return false;
    }

    public function enviar(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        return $requisicao->ehRascunho() && $requisicao->solicitante_user_id === $authUser->getAuthIdentifier() && $authUser->can('Create:RequisicaoCompra');
    }

    public function cotar(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        return $authUser->can('Cotar:RequisicaoCompra') && $requisicao->status === StatusRequisicaoCompra::EmCotacao;
    }

    public function aprovar(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        $pedido = $requisicao->pedido;

        return $pedido !== null && app(ComprasService::class)->impedimentoParaAprovar($pedido, $authUser) === null;
    }

    public function receber(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        $pedido = $requisicao->pedido;

        return $pedido !== null && $authUser->can('Receber:PedidoCompra') && app(ComprasService::class)->impedimentoParaReceber($pedido, $authUser) === null;
    }

    public function cancelar(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        if ($requisicao->status->encerrada() || $requisicao->status === StatusRequisicaoCompra::RecebidaParcial) {
            return false;
        }

        return $requisicao->solicitante_user_id === $authUser->getAuthIdentifier() || $authUser->can('Cotar:RequisicaoCompra');
    }

    private function ehDoUsuarioOuVeTodas(AuthUser $authUser, RequisicaoCompra $requisicao): bool
    {
        return $requisicao->solicitante_user_id === $authUser->getAuthIdentifier() || $authUser->can('VerTodas:RequisicaoCompra');
    }
}
