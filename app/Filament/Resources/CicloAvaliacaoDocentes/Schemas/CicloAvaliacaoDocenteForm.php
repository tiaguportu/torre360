<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Schemas;

use App\Models\CicloAvaliacaoDocente;
use App\Models\InstrumentoAvaliacaoDocente;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CicloAvaliacaoDocenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ciclo de avaliação')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->placeholder('Ex.: 1º Semestre 2026')
                            ->required()
                            ->maxLength(255),
                        Select::make('instrumento_id')
                            ->label('Instrumento')
                            ->options(fn (?CicloAvaliacaoDocente $record): array => InstrumentoAvaliacaoDocente::query()
                                ->where(fn ($q) => $q->where('ativo', true)->when($record?->instrumento_id, fn ($qq) => $qq->orWhere('id', $record->instrumento_id)))
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->disabled(fn (?CicloAvaliacaoDocente $record): bool => (bool) $record?->avaliacoes()->exists())
                            ->helperText('Não pode ser trocado depois que o ciclo tem avaliações.'),
                        DatePicker::make('data_inicio')
                            ->label('Início')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('data_fim')
                            ->label('Fim')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inicio')
                            ->required(),
                        Toggle::make('ativo')
                            ->label('Ciclo aberto')
                            ->default(true)
                            ->helperText('Desative para encerrar o ciclo: não aceita mais novas observações, e o histórico permanece.'),
                    ]),
            ]);
    }
}
