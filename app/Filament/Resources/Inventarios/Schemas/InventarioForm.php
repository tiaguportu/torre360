<?php

namespace App\Filament\Resources\Inventarios\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Auditoria de Acervo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Identificação do Inventário')
                            ->placeholder('Ex: Inventário Anual 2026 - Acervo Geral')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        DatePicker::make('data_inicio')
                            ->label('Data de Início')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        Select::make('status')
                            ->label('Situação')
                            ->options([
                                'em_andamento' => 'Em Andamento',
                                'concluido' => 'Concluído',
                            ])
                            ->default('em_andamento')
                            ->required()
                            ->native(false),
                        Textarea::make('observacoes')
                            ->label('Observações e Instruções')
                            ->placeholder('Notas sobre estantes auditadas, equipes ou critérios...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
