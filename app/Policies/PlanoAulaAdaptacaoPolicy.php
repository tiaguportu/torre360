<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PlanoAulaAdaptacaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('GerarPei:PlanoAula') || $authUser->can('ExportarPei:PlanoAula');
    }

    public function view(AuthUser $authUser, PlanoAulaAdaptacao $adaptacao): bool
    {
        $temPermissao = $authUser->can('GerarPei:PlanoAula') || $authUser->can('ExportarPei:PlanoAula');

        if (! $temPermissao) {
            return false;
        }

        return $this->verificarEscopoProfessor($authUser, $adaptacao->planoAula);
    }

    public function create(AuthUser $authUser, ?PlanoAula $planoAula = null): bool
    {
        if (! $authUser->can('GerarPei:PlanoAula')) {
            return false;
        }

        if ($planoAula !== null) {
            return $this->verificarEscopoProfessor($authUser, $planoAula);
        }

        return true;
    }

    public function update(AuthUser $authUser, PlanoAulaAdaptacao $adaptacao): bool
    {
        if (! $authUser->can('GerarPei:PlanoAula')) {
            return false;
        }

        return $this->verificarEscopoProfessor($authUser, $adaptacao->planoAula);
    }

    public function delete(AuthUser $authUser, PlanoAulaAdaptacao $adaptacao): bool
    {
        if (! $authUser->can('GerarPei:PlanoAula')) {
            return false;
        }

        return $this->verificarEscopoProfessor($authUser, $adaptacao->planoAula);
    }

    public function exportarPei(AuthUser $authUser, PlanoAulaAdaptacao $adaptacao): bool
    {
        if (! $authUser->can('ExportarPei:PlanoAula')) {
            return false;
        }

        return $this->verificarEscopoProfessor($authUser, $adaptacao->planoAula);
    }

    /**
     * Valida se o usuário tem acesso ao plano de aula pelo escopo de professor.
     * Super Admin, coordenadores, administradores e outros papéis têm acesso global.
     * Professores só acessam planos de aula vinculados à sua própria pessoa ou turmas que lecionam.
     */
    public function verificarEscopoProfessor(AuthUser $authUser, ?PlanoAula $planoAula): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        if (session('active_role') !== 'professor') {
            return true;
        }

        if (! $planoAula) {
            return false;
        }

        /** @var User $authUser */
        $pessoasIds = array_filter(array_merge(
            [$authUser->pessoa?->id],
            $authUser->pessoas ? $authUser->pessoas->pluck('id')->toArray() : []
        ));

        if (empty($pessoasIds)) {
            return false;
        }

        // 1. O professor é o autor/titular do plano de aula
        if ($planoAula->professor_id && in_array($planoAula->professor_id, $pessoasIds, true)) {
            return true;
        }

        // 2. O professor é conselheiro da turma ou leciona alguma disciplina nela
        $turma = $planoAula->turma;
        if ($turma) {
            if ($turma->professor_conselheiro_id && in_array($turma->professor_conselheiro_id, $pessoasIds, true)) {
                return true;
            }

            if ($turma->disciplinas()->whereIn('turma_disciplina.professor_id', $pessoasIds)->exists()) {
                return true;
            }
        }

        return false;
    }
}
