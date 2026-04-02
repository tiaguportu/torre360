<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Notifications\UserNewPasswordNotification;
use Exception;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomRequestPasswordReset extends RequestPasswordReset
{
    public function request(): void
    {
        $data = $this->form->getState();

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            Notification::make()
                ->title(__('Passowrd reset requested'))
                ->body('Se o e-mail informado estiver em nossa base de dados, uma nova senha será enviada em breve.')
                ->success()
                ->send();

            return;
        }

        try {
            // Gerar senha forte (12 caractéres com símbolos)
            $newPassword = Str::password(12);

            // Atualizar no banco
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // Enviar notificação
            $user->notify(new UserNewPasswordNotification($newPassword));

            Notification::make()
                ->title('Senha enviada!')
                ->body('Uma nova senha forte foi gerada e enviada para seu e-mail.')
                ->success()
                ->send();

            $this->redirect(filament()->getLoginUrl());
        } catch (Exception $exception) {
            Notification::make()
                ->title('Erro ao resetar senha')
                ->body('Não foi possível enviar a nova senha. Tente novamente mais tarde.')
                ->danger()
                ->send();
        }
    }
}
