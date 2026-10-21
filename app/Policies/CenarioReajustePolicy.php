<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusCenarioReajuste;
use App\Models\CenarioReajuste;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Reajuste muda a receita da escola: só a administração simula, aprova e aplica. Depois de aprovado o cenário não se
 * altera; aplicado, só se desfaz antes de qualquer rematrícula efetivada (regra do serviço).
 */
class CenarioReajustePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CenarioReajuste');
    }

    public function view(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('View:CenarioReajuste');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CenarioReajuste');
    }

    public function update(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('Update:CenarioReajuste') && $cenario->status === StatusCenarioReajuste::Simulacao;
    }

    public function delete(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('Delete:CenarioReajuste') && ! $cenario->estaAplicado();
    }

    public function aprovar(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('Aprovar:CenarioReajuste') && $cenario->status === StatusCenarioReajuste::Simulacao;
    }

    public function aplicar(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('Aplicar:CenarioReajuste') && $cenario->status === StatusCenarioReajuste::Aprovado;
    }

    public function desfazer(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('Aplicar:CenarioReajuste') && $cenario->estaAplicado();
    }

    public function comunicar(AuthUser $authUser, CenarioReajuste $cenario): bool
    {
        return $authUser->can('Comunicar:CenarioReajuste') && $cenario->status !== StatusCenarioReajuste::Simulacao;
    }
}
