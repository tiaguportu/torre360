<?php

namespace App\Filament\Resources\Avaliacaos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AvaliacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::getSchemaComponents());
    }

    public static function getSchemaComponents(): array
    {
        return [
            Select::make('etapa_avaliativa_id')
                ->relationship('etapaAvaliativa', 'nome')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? 'Sem Nome')
                ->label('Etapa Avaliativa')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('turma_id')
                ->relationship('turma', 'nome')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? 'Sem Nome')
                ->label('Turma')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('disciplina_id')
                ->relationship('disciplina', 'nome')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? 'Sem Nome')
                ->label('Disciplina')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('professor_id')
                ->relationship('professor', 'nome', modifyQueryUsing: fn ($query) => $query->whereHas('users', fn ($q) => $q->role('professor')))
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? 'Sem Nome')
                ->label('Professor')
                ->required()
                ->searchable()
                ->preload()
                ->default(auth()->user()?->hasRole('professor') ? auth()->user()->pessoa?->id : null)
                ->disabled(auth()->user()?->hasRole('professor') && auth()->user()->pessoa?->id !== null)
                ->dehydrated(),

            DatePicker::make('data_prevista')
                ->label('Data Prevista')
                ->required(),

            DatePicker::make('data_ocorrencia')
                ->label('Data de Ocorrência'),

            DatePicker::make('data_limite_lancamento')
                ->label('Data Limite de Lançamento')
                ->required(),

            TextInput::make('nota_maxima')
                ->label('Nota Máxima')
                ->numeric()
                ->default(10.00),

            TextInput::make('peso_etapa_avaliativa')
                ->label('Peso na Etapa')
                ->numeric()
                ->default(1.00),

            Select::make('categoria_avaliacao_id')
                ->relationship(
                    'categoria',
                    'nome',
                    modifyQueryUsing: fn ($query) => $query->orderBy('ordem')
                )
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome} - {$record->descricao}")
                ->label('Categoria')
                ->searchable(['nome', 'descricao'])
                ->required()
                ->preload(),
        ];
    }
}
