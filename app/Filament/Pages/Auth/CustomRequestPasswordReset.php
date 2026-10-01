<?php

namespace App\Filament\Pages\Auth;

use Ddr\FilamentCaptcha\Forms\Components\Captcha;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Schemas\Schema;

class CustomRequestPasswordReset extends BaseRequestPasswordReset
{
    public function form(Schema $schema): Schema
    {
        $components = [
            $this->getEmailFormComponent(),
        ];

        if (! app()->environment('local', 'testing')) {
            $components[] = Captcha::make('captcha')
                ->label('reCAPTCHA')
                ->hiddenLabel();
        }

        return $schema->components($components);
    }
}
