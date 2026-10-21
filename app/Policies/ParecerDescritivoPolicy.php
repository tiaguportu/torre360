<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusParecerDescritivo;
use App\Models\ParecerDescritivo;
use App\Models\User;
use App\Support\EscopoProfessor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * O professor escreve e envia para revisão; a coordenação publica. Parecer publicado não é editado: precisa ser
 * reaberto por quem tem `Publicar`. Quem atua como professor só alcança os alunos das próprias turmas.
 */
class ParecerDescritivoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ParecerDescritivo');
    }

    public function view(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $authUser->can('View:ParecerDescritivo') && $this->alcancaAluno($authUser, $parecer);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ParecerDescritivo');
    }

    public function update(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $authUser->can('Update:ParecerDescritivo')
            && $parecer->status !== StatusParecerDescritivo::Publicado
            && $this->alcancaAluno($authUser, $parecer);
    }

    public function delete(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $authUser->can('Delete:ParecerDescritivo') && ! $parecer->estaPublicado();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ParecerDescritivo');
    }

    public function enviarParaRevisao(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $parecer->status === StatusParecerDescritivo::Rascunho && $this->update($authUser, $parecer);
    }

    public function publicar(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $authUser->can('Publicar:ParecerDescritivo') && ! $parecer->estaPublicado();
    }

    public function reabrir(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $authUser->can('Publicar:ParecerDescritivo') && $parecer->estaPublicado();
    }

    public function gerarComIa(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        return $authUser->can('GerarComIa:ParecerDescritivo')
            && $parecer->status !== StatusParecerDescritivo::Publicado
            && $this->alcancaAluno($authUser, $parecer);
    }

    private function alcancaAluno(AuthUser $authUser, ParecerDescritivo $parecer): bool
    {
        /** @var User $authUser */
        return EscopoProfessor::podeVerMatricula($authUser, $parecer->matricula);
    }
}
