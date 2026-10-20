<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Schemas;

use App\Models\InstrumentoAvaliacaoDocente;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstrumentoAvaliacaoDocenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Instrumento')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->placeholder('Ex.: Observação em sala de aula')
                            ->required()
                            ->maxLength(255),
                        Select::make('escala_maxima')
                            ->label('Escala de notas')
                            ->options([4 => '1 a 4', 5 => '1 a 5', 10 => '1 a 10'])
                            ->default(5)
                            ->required()
                            ->disabled(fn (?InstrumentoAvaliacaoDocente $record): bool => (bool) $record?->emUso())
                            ->helperText('Cada critério recebe uma nota de 1 até o valor máximo escolhido.'),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('ativo')
                            ->label('Disponível para novos ciclos')
                            ->default(true),
                    ]),
                Section::make('Critérios')
                    ->description('O que será observado e quanto cada critério pesa na pontuação final.')
                    ->schema([
                        Placeholder::make('aviso_em_uso')
                            ->hiddenLabel()
                            ->content('Este instrumento já foi usado em avaliações. Para preservar o histórico dos professores, os critérios não podem mais ser alterados: crie um novo instrumento (por exemplo, copiando este) para mudar o roteiro.')
                            ->visible(fn (?InstrumentoAvaliacaoDocente $record): bool => (bool) $record?->emUso()),
                        Repeater::make('criterios')
                            ->relationship('criterios')
                            ->orderColumn('ordem')
                            ->reorderable()
                            ->addActionLabel('Adicionar critério')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->disabled(fn (?InstrumentoAvaliacaoDocente $record): bool => (bool) $record?->emUso())
                            ->columns(3)
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Critério')
                                    ->placeholder('Ex.: Planejamento da aula')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('peso')
                                    ->label('Peso')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.1)
                                    ->maxValue(99)
                                    ->required(),
                                Textarea::make('descricao')
                                    ->label('O que observar')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
