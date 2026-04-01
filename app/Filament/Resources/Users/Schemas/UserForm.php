<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

                Toggle::make('send_credentials')
                    ->label('Enviar informações de acesso para o e-mail do usuário')
                    ->helperText('Se marcado, um e-mail será enviado com os dados de login e senha criados.')
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'create'),

                Select::make('roles')
                    ->label('Papéis (Roles)')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),

                Select::make('pessoa_id')
                    ->label('Pessoa Vinculada')
                    ->relationship('pessoa', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ($record->cpf ? " - {$record->cpf}" : ""))
                    ->searchable(['nome', 'cpf'])
                    ->preload()
                    ->nullable()
                    ->helperText('Opcional: vincule este usuário a uma Pessoa existente no sistema.'),
            ]);
    }
}
