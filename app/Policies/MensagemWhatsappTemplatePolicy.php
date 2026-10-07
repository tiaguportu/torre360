<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MensagemWhatsappTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MensagemWhatsappTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MensagemWhatsappTemplate');
    }

    public function view(AuthUser $authUser, MensagemWhatsappTemplate $mensagemWhatsappTemplate): bool
    {
        return $authUser->can('View:MensagemWhatsappTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MensagemWhatsappTemplate');
    }

    public function update(AuthUser $authUser, MensagemWhatsappTemplate $mensagemWhatsappTemplate): bool
    {
        return $authUser->can('Update:MensagemWhatsappTemplate');
    }

    public function delete(AuthUser $authUser, MensagemWhatsappTemplate $mensagemWhatsappTemplate): bool
    {
        return $authUser->can('Delete:MensagemWhatsappTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MensagemWhatsappTemplate');
    }

    public function restore(AuthUser $authUser, MensagemWhatsappTemplate $mensagemWhatsappTemplate): bool
    {
        return $authUser->can('Restore:MensagemWhatsappTemplate');
    }

    public function forceDelete(AuthUser $authUser, MensagemWhatsappTemplate $mensagemWhatsappTemplate): bool
    {
        return $authUser->can('ForceDelete:MensagemWhatsappTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MensagemWhatsappTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MensagemWhatsappTemplate');
    }

    public function replicate(AuthUser $authUser, MensagemWhatsappTemplate $mensagemWhatsappTemplate): bool
    {
        return $authUser->can('Replicate:MensagemWhatsappTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MensagemWhatsappTemplate');
    }
}
