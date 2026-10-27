<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IndicadorMeta;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Metas do Painel da Direção (Onda 45): quem define a meta é a direção; ver a lista é de quem acompanha o painel.
 */
class IndicadorMetaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IndicadorMeta');
    }

    public function view(AuthUser $authUser, IndicadorMeta $meta): bool
    {
        return $authUser->can('View:IndicadorMeta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IndicadorMeta');
    }

    public function update(AuthUser $authUser, IndicadorMeta $meta): bool
    {
        return $authUser->can('Update:IndicadorMeta');
    }

    public function delete(AuthUser $authUser, IndicadorMeta $meta): bool
    {
        return $authUser->can('Delete:IndicadorMeta');
    }
}
