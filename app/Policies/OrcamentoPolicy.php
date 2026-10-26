<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Orcamento;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * O orçamento aprovado não muda: editar e excluir só valem para o rascunho, e a revisão (nova versão) só para o aprovado. O super
 * admin passa por qualquer habilidade (Gate::before), então as telas também conferem o estado do registro.
 */
class OrcamentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Orcamento');
    }

    public function view(AuthUser $authUser, Orcamento $orcamento): bool
    {
        return $authUser->can('View:Orcamento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Orcamento');
    }

    public function update(AuthUser $authUser, Orcamento $orcamento): bool
    {
        return $authUser->can('Update:Orcamento') && $orcamento->ehRascunho();
    }

    public function delete(AuthUser $authUser, Orcamento $orcamento): bool
    {
        return $authUser->can('Delete:Orcamento') && $orcamento->ehRascunho();
    }

    public function aprovar(AuthUser $authUser, Orcamento $orcamento): bool
    {
        return $authUser->can('Aprovar:Orcamento') && $orcamento->ehRascunho();
    }

    public function revisar(AuthUser $authUser, Orcamento $orcamento): bool
    {
        return $authUser->can('Update:Orcamento') && $orcamento->estaAprovado();
    }
}
