<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Ddr\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomLogin extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        $components = [
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ];

        if (! app()->environment('local', 'testing')) {
            $components[] = Captcha::make('captcha')
                ->label('reCAPTCHA')
                ->hiddenLabel();
        }

        return $schema->components($components);
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $e) {
            $data = $this->form->getState();
            $user = User::where('email', $data['email'] ?? null)->first();

            // Proteção estrita contra Enumeração de Usuários (OWASP A07):
            // Só informa que a conta está desativada se a senha informada for EXATA.
            // Se a senha estiver incorreta ou o e-mail não existir, exibe o erro padrão de credenciais.
            if ($user && ! $user->is_active && Hash::check($data['password'] ?? '', $user->password)) {
                throw ValidationException::withMessages([
                    'data.email' => 'Esta conta está desativada. Por favor, entre em contato com o administrador.',
                ]);
            }

            throw $e;
        }
    }
}
