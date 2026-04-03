<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->confirmed()
                    ->suffixAction(
                        Action::make('generatePassword')
                            ->label('Gerar senha forte')
                            ->icon('heroicon-m-key')
                            ->action(function (Set $set) {
                                $password = Str::password(12);
                                $set('password', $password);
                                $set('password_confirmation', $password);
                            })
                    ),

                TextInput::make('password_confirmation')
                    ->label('Confirmar Senha')
                    ->password()
                    ->dehydrated(false)
                    ->required(fn (string $operation): bool => $operation === 'create'),

                Toggle::make('is_active')
                    ->label('Usuário Ativo')
                    ->default(true)
                    ->helperText('Habilita ou desabilita o acesso do usuário ao sistema.'),

                Toggle::make('email_verified_at')
                    ->label('E-mail Verificado')
                    ->helperText('Marca o e-mail do usuário como verificado.')
                    ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                    ->afterStateHydrated(function (Toggle $component, $state) {
                        $component->state($state !== null);
                    }),

                Toggle::make('send_credentials')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Enviar informações de acesso para o e-mail do usuário' : 'Informar alteração por e-mail ao usuário')
                    ->helperText('Se marcado, um e-mail será enviado ao usuário.')
                    ->dehydrated(false),

                Select::make('roles')
                    ->label('Papéis (Roles)')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),

                Select::make('pessoas')
                    ->label('Pessoas Vinculadas')
                    ->multiple()
                    ->relationship('pessoas', 'nome')
                    ->preload()
                    ->searchable()
                    ->helperText('Um usuário pode estar vinculado a várias pessoas (ex: Professor e Responsável).'),
            ]);
    }
}
