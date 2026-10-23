<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentoAssinatura;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A secretaria e a administração criam, enviam e cancelam; a coordenação consulta. Documento de RH (contrato de trabalho,
 * termos do funcionário) só aparece para quem também vê Admissão e Desligamento. Sem edição nem exclusão: o texto enviado
 * e o histórico ficam.
 *
 * As habilidades carregam o estado do documento (não dá para enviar o que já foi enviado), mas o super admin passa por
 * qualquer habilidade (Gate::before): por isso as telas também conferem o estado, e o serviço recusa o que não cabe.
 */
class DocumentoAssinaturaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentoAssinatura');
    }

    public function view(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return $authUser->can('View:DocumentoAssinatura') && $this->podeVerRh($authUser, $documento);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentoAssinatura');
    }

    public function update(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return false;
    }

    public function enviar(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return $authUser->can('Enviar:DocumentoAssinatura') && $this->podeVerRh($authUser, $documento) && $documento->podeEnviar();
    }

    public function atualizar(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return $this->view($authUser, $documento) && $documento->podeSerConsultado();
    }

    /**
     * Os links de assinatura são a "chave" de cada assinante: só vê quem pode enviar o documento, e só enquanto ele aguarda.
     */
    public function verLinks(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return $authUser->can('Enviar:DocumentoAssinatura') && $this->podeVerRh($authUser, $documento) && $documento->aguardaAssinaturas();
    }

    public function cancelar(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return $authUser->can('Cancelar:DocumentoAssinatura') && $this->podeVerRh($authUser, $documento) && $documento->emAndamento();
    }

    private function podeVerRh(AuthUser $authUser, DocumentoAssinatura $documento): bool
    {
        return ! $documento->ehDeRh() || $authUser->can('View:ProcessoFuncionario');
    }
}
