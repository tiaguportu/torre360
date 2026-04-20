<?php

namespace App\Filament\Pages\Auth;

use Ddr\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Form;

class Register extends BaseRegister
{
    public function form(Form $form): Form
    {
        return $form->schema([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            Captcha::make('captcha')
                ->label('reCAPTCHA')
                ->hiddenLabel(),
        ]);
    }
}
