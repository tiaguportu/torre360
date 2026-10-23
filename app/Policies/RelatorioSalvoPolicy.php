<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RelatorioSalvo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Cada pessoa mexe nos seus relatórios; quem tem `VerTodos:RelatorioSalvo` mexe também nos dos outros. O super admin passa por
 * qualquer habilidade (Gate::before), então a lista também filtra pelo dono em {@see RelatorioSalvo::scopeVisiveisPara()}.
 */
class RelatorioSalvoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RelatorioSalvo');
    }

    public function view(AuthUser $authUser, RelatorioSalvo $relatorio): bool
    {
        return $authUser->can('View:RelatorioSalvo') && $this->ehDoUsuarioOuVeTodos($authUser, $relatorio);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RelatorioSalvo');
    }

    public function update(AuthUser $authUser, RelatorioSalvo $relatorio): bool
    {
        return $authUser->can('Update:RelatorioSalvo') && $this->ehDoUsuarioOuVeTodos($authUser, $relatorio);
    }

    public function delete(AuthUser $authUser, RelatorioSalvo $relatorio): bool
    {
        return $authUser->can('Delete:RelatorioSalvo') && $this->ehDoUsuarioOuVeTodos($authUser, $relatorio);
    }

    public function executar(AuthUser $authUser, RelatorioSalvo $relatorio): bool
    {
        return $authUser->can('Executar:RelatorioSalvo') && $this->ehDoUsuarioOuVeTodos($authUser, $relatorio);
    }

    private function ehDoUsuarioOuVeTodos(AuthUser $authUser, RelatorioSalvo $relatorio): bool
    {
        return $relatorio->criado_por_user_id === $authUser->getAuthIdentifier() || $authUser->can('VerTodos:RelatorioSalvo');
    }
}
