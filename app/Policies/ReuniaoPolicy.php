<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reuniao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Reuniões e atas (Ondas 27 e 32). Quem organiza e quem participa vê a reunião; `VerTodas:Reuniao` (coordenação) abre também as
 * dos outros, menos as sigilosas: a devolutiva individual e as marcadas como confidenciais só são de quem organiza e de quem
 * participa. O super admin passa por qualquer habilidade (Gate::before), então as telas conferem também a situação da reunião e o
 * serviço recusa o que a regra não permite.
 */
class ReuniaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Reuniao');
    }

    public function view(AuthUser $authUser, Reuniao $reuniao): bool
    {
        return $authUser->can('View:Reuniao') && $this->enxerga($authUser, $reuniao);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Reuniao');
    }

    public function update(AuthUser $authUser, Reuniao $reuniao): bool
    {
        return $authUser->can('Update:Reuniao') && $reuniao->estaAgendada() && $this->gerencia($authUser, $reuniao);
    }

    public function delete(AuthUser $authUser, Reuniao $reuniao): bool
    {
        return $authUser->can('Delete:Reuniao')
            && ($reuniao->estaAgendada() || $reuniao->estaCancelada())
            && $this->gerencia($authUser, $reuniao);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Reuniao');
    }

    /**
     * Presença, ata, encaminhamentos, cancelamento e adendo: quem organiza ou coordena a reunião.
     */
    public function gerenciar(AuthUser $authUser, Reuniao $reuniao): bool
    {
        return $authUser->can('Update:Reuniao') && $this->gerencia($authUser, $reuniao);
    }

    /**
     * Escrever e fechar a ata: quem gerencia e, no conselho de classe, também o presidente.
     */
    public function redigirAta(AuthUser $authUser, Reuniao $reuniao): bool
    {
        return $this->gerenciar($authUser, $reuniao)
            || ($authUser->can('Deliberar:Reuniao') && $reuniao->ehConselho() && $reuniao->ehPresidente($authUser));
    }

    /**
     * Deliberar sobre cada aluno do conselho: quem gerencia e o presidente, e só enquanto a reunião está em andamento.
     */
    public function deliberar(AuthUser $authUser, Reuniao $reuniao): bool
    {
        return $authUser->can('Deliberar:Reuniao') && $reuniao->ehConselho() && $this->redigirAta($authUser, $reuniao);
    }

    private function enxerga(AuthUser $authUser, Reuniao $reuniao): bool
    {
        if ($reuniao->ehOrganizador($authUser) || $reuniao->participaDe($authUser)) {
            return true;
        }

        return ! $reuniao->ehSigilosa() && $authUser->can('VerTodas:Reuniao');
    }

    private function gerencia(AuthUser $authUser, Reuniao $reuniao): bool
    {
        if ($reuniao->ehOrganizador($authUser)) {
            return true;
        }

        return ! $reuniao->ehSigilosa() && $authUser->can('VerTodas:Reuniao');
    }
}
