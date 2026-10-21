<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PortfolioItem;
use App\Models\User;
use App\Support\EscopoProfessor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Portfólio é registro pedagógico de uma criança (às vezes com foto ou vídeo). Quem atua como professor só alcança os
 * alunos das próprias turmas; coordenação, secretaria e administração alcançam todos.
 */
class PortfolioItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PortfolioItem');
    }

    public function view(AuthUser $authUser, PortfolioItem $item): bool
    {
        return $authUser->can('View:PortfolioItem') && $this->alcancaAluno($authUser, $item);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PortfolioItem');
    }

    public function update(AuthUser $authUser, PortfolioItem $item): bool
    {
        return $authUser->can('Update:PortfolioItem') && $this->alcancaAluno($authUser, $item);
    }

    public function delete(AuthUser $authUser, PortfolioItem $item): bool
    {
        return $authUser->can('Delete:PortfolioItem') && $this->alcancaAluno($authUser, $item);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PortfolioItem');
    }

    private function alcancaAluno(AuthUser $authUser, PortfolioItem $item): bool
    {
        /** @var User $authUser */
        return EscopoProfessor::podeVerMatricula($authUser, $item->matricula);
    }
}
