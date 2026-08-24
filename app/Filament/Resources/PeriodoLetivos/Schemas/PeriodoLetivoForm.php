<?php

namespace App\Filament\Resources\PeriodoLetivos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PeriodoLetivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
                DatePicker::make('data_inicio')
                    ->required(),
                DatePicker::make('data_fim')
                    ->required(),
                TextInput::make('nota_aprovacao')
                    ->label('Nota Mínima para Aprovação')
                    ->numeric()
                    ->default(7)
                    ->required()
                    ->helperText('Média final igual ou superior a este valor aprova o aluno na disciplina.'),
                TextInput::make('nota_recuperacao_minima')
                    ->label('Nota Mínima para Recuperação')
                    ->numeric()
                    ->default(5)
                    ->required()
                    ->helperText('Média final igual ou superior a este valor (e abaixo da nota de aprovação) coloca o aluno em recuperação. Abaixo disso, reprovado.'),
            ]);
    }
}
