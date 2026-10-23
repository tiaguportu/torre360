<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CasoFrequencia;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A coordenação e a administração apuram, abrem o caso e registram o contato com a família; a secretaria consulta e
 * registra o envio do ofício. Caso de frequência traz dados de criança e da família: só a equipe com permissão vê, e não
 * há edição livre nem exclusão (o histórico é a prova de que a escola agiu).
 *
 * As habilidades carregam o estado do caso, mas o super admin passa por qualquer habilidade (Gate::before): as telas
 * conferem o estado também.
 */
class CasoFrequenciaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CasoFrequencia');
    }

    public function view(AuthUser $authUser, CasoFrequencia $caso): bool
    {
        return $authUser->can('View:CasoFrequencia');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CasoFrequencia');
    }

    public function update(AuthUser $authUser, CasoFrequencia $caso): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, CasoFrequencia $caso): bool
    {
        return false;
    }

    public function acompanhar(AuthUser $authUser, CasoFrequencia $caso): bool
    {
        return $authUser->can('Update:CasoFrequencia') && $caso->estaAberto();
    }

    public function enviar(AuthUser $authUser, CasoFrequencia $caso): bool
    {
        return $authUser->can('Enviar:CasoFrequencia') && $caso->estaAberto();
    }
}
