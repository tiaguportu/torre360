<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reuniao;
use App\Models\ReuniaoAluno;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A pauta por aluno do conselho de classe: vê quem vê a reunião; deliberar é de quem a política da reunião autoriza. Ninguém cria
 * nem apaga linha à mão: ela vem da turma.
 */
class ReuniaoAlunoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Reuniao');
    }

    public function view(AuthUser $authUser, ReuniaoAluno $linha): bool
    {
        $reuniao = Reuniao::query()->find($linha->reuniao_id);

        return $reuniao !== null && $authUser->can('view', $reuniao);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, ReuniaoAluno $linha): bool
    {
        $reuniao = Reuniao::query()->find($linha->reuniao_id);

        return $reuniao !== null && $authUser->can('deliberar', $reuniao);
    }

    public function delete(AuthUser $authUser, ReuniaoAluno $linha): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
