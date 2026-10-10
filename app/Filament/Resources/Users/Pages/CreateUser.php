<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\VideoTutorial;
use App\Notifications\WelcomeUserMail;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && ! $user->hasRole('super_admin')) {
            $superAdminRoleId = Role::where('name', 'super_admin')->value('id');
            if ($superAdminRoleId && in_array($superAdminRoleId, (array) ($data['roles'] ?? []))) {
                abort(403, 'Ação não permitida: você não possui permissão para atribuir o papel de Super Administrador.');
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->data;

        if ($data['create_pessoa'] ?? false) {
            /** @var User $user */
            $user = $this->record;

            $pessoa = Pessoa::create([
                'nome' => $user->name,
                'email' => $user->email,
            ]);

            $user->pessoas()->attach($pessoa->id);
        }

        if ($data['send_credentials'] ?? false) {
            /** @var User $user */
            $user = $this->record;

            // A senha digitada não é enviada: o e-mail leva um link para o usuário definir a própria.
            $user->notify(new WelcomeUserMail);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Novo Usuário')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'usuarios-cadastro')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Preencha nome, e-mail e senha do novo usuário. Use o botão ao lado do campo Senha para gerar uma senha forte automaticamente.</p>';
        $html .= '<p>O <strong>Papel (Role)</strong> escolhido define as permissões do usuário no sistema — atribua com cuidado.</p>';
        $html .= '<p>Marque <em>"Criar Pessoa automaticamente"</em> se este usuário ainda não tiver um cadastro de Pessoa vinculado (ex: um novo funcionário).</p>';

        return $html;
    }
}
