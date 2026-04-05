<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ChangePassword extends BaseEditProfile
{
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent()
                    ->label('Nova Senha')
                    ->hintAction(
                        Action::make('generatePassword')
                            ->label('Gerar Senha Forte')
                            ->icon('heroicon-m-key')
                            ->action(function (Set $set) {
                                $password = Str::password(16);
                                $set('password', $password);
                                $set('passwordConfirmation', $password);
                            })
                    ),
                $this->getPasswordConfirmationFormComponent()
                    ->label('Confirmar Nova Senha'),
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/edit-profile.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
            ->live(onBlur: true)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::pages/auth/edit-profile.form.password_confirmation.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false);
    }
}
