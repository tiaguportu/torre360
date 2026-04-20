<?php

namespace App\Filament\Pages\Auth;

use Ddr\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Schemas\Schema;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
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
