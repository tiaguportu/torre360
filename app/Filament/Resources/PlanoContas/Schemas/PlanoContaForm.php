<?php

namespace App\Filament\Resources\PlanoContas\Schemas;

use App\Models\PlanoConta;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanoContaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Código')
                    ->placeholder('Ex: 1.1')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('nome')
                    ->label('Nome da Conta')
                    ->required(),
                Select::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'receita' => 'Receita',
                        'despesa' => 'Despesa',
                        'ativo' => 'Ativo',
                        'passivo' => 'Passivo',
                    ])
                    ->required()
                    ->native(false),
                Select::make('pai_id')
                    ->label('Conta Pai')
                    ->relationship('pai', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (PlanoConta $record) => "{$record->codigo} - {$record->nome}")
                    ->searchable()
                    ->placeholder('Selecione se for uma subconta'),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true)
                    ->required(),
            ]);
    }
}
