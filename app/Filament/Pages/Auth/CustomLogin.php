<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class CustomLogin extends BaseLogin
{
    /**
     * @throws ValidationException
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $e) {
            // Se falhou as credenciais, vamos ver se o usuário existe e está desativado
            // para dar uma mensagem mais específica.
            $data = $this->form->getState();
            $user = User::where('email', $data['email'])->first();

            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'data.email' => 'Esta conta está desativada. Por favor, entre em contato com o administrador.',
                ]);
            }

            throw $e;
        }
    }
}
