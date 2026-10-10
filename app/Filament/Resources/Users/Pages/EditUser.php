<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\UserUpdatedMail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        $user = auth()->user();
        if ($this->record instanceof User && $this->record->hasRole('super_admin') && ! $user?->hasRole('super_admin')) {
            abort(403, 'Acesso não autorizado: apenas usuários com o papel Super Administrador podem editar este usuário.');
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if (! $user?->hasRole('super_admin')) {
            $superAdminRoleId = Role::where('name', 'super_admin')->value('id');
            if ($superAdminRoleId && in_array($superAdminRoleId, (array) ($data['roles'] ?? []))) {
                abort(403, 'Ação não permitida: você não possui permissão para atribuir o papel de Super Administrador.');
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (): bool => ! auth()->user()?->hasRole('super_admin') && $this->record instanceof User && $this->record->hasRole('super_admin')),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Usuário')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Edite os dados cadastrais, altere senhas ou gerencie os papéis do usuário no sistema com controle estrito de privilégios.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('View:User')) {
            $html .= '<li><strong>Visualizar:</strong> Permite consultar o infolist completo com os detalhes cadastrais deste usuário.</li>';
        }

        if ($user && $user->can('Delete:User')) {
            $html .= '<li><strong>Excluir:</strong> Permite desativar ou remover o usuário do sistema, respeitando as salvaguardas de auto-exclusão e governança de Super Administrador.</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function afterSave(): void
    {
        $data = $this->data;

        if ($data['send_credentials'] ?? false) {
            /** @var User $user */
            $user = $this->record;

            // O e-mail avisa que a senha mudou e oferece um link para definir outra; nunca envia a senha.
            $user->notify(new UserUpdatedMail(senhaAlterada: filled($data['password'] ?? null)));
        }
    }
}
