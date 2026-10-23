<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConselhosTutelares\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConselhoTutelarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conselho Tutelar')
                    ->description('Para onde vai o ofício de faltas excessivas. Cadastre um para cada município em que a escola tem unidade; sem unidade, o conselho vale para todas.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->placeholder('Ex.: Conselho Tutelar da Região Central')
                            ->required()
                            ->maxLength(160)
                            ->columnSpanFull(),
                        Select::make('unidade_id')
                            ->label('Unidade atendida')
                            ->relationship('unidade', 'nome')
                            ->searchable()
                            ->preload()
                            ->placeholder('Todas as unidades'),
                        TextInput::make('municipio')
                            ->label('Município')
                            ->maxLength(120),
                        TextInput::make('uf')
                            ->label('UF')
                            ->maxLength(2)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null),
                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(160),
                        TextInput::make('endereco')
                            ->label('Endereço')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->helperText('Só o conselho ativo é sugerido ao abrir um caso.'),
                    ]),
            ]);
    }
}
