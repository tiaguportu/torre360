<?php

namespace App\Policies;

use App\Models\PlanilhaLeiMensalidade;
use App\Models\User;

class PlanilhaLeiMensalidadePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:PlanilhaLeiMensalidade');
    }

    public function view(User $user, PlanilhaLeiMensalidade $record): bool
    {
        return $user->can('View:PlanilhaLeiMensalidade');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:PlanilhaLeiMensalidade');
    }

    public function update(User $user, PlanilhaLeiMensalidade $record): bool
    {
        return $user->can('Update:PlanilhaLeiMensalidade');
    }

    public function delete(User $user, PlanilhaLeiMensalidade $record): bool
    {
        return $user->can('Delete:PlanilhaLeiMensalidade');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:PlanilhaLeiMensalidade');
    }
}
