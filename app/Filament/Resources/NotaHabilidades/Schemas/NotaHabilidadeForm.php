<?php

namespace App\Filament\Resources\NotaHabilidades\Schemas;

use App\Enums\ConceitoHabilidade;
use App\Models\AvaliacaoHabilidade;
use App\Models\Matricula;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotaHabilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Nota de Habilidade')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('avaliacao_habilidade_id')
                                    ->label('Avaliação de Habilidade')
                                    ->relationship('avaliacaoHabilidade', 'id')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => sprintf(
                                        '%s - %s (%s)',
                                        $record->turma?->nome ?? 'Sem Turma',
                                        $record->habilidade?->nome ?? 'Sem Habilidade',
                                        $record->etapaAvaliativa?->nome ?? 'Sem Etapa'
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('matricula_id', null)),
                                Select::make('matricula_id')
                                    ->label('Aluno')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->options(function (callable $get) {
                                        $avaliacaoHabilidadeId = $get('avaliacao_habilidade_id');
                                        if (! $avaliacaoHabilidadeId) {
                                            return [];
                                        }

                                        $avaliacaoHabilidade = AvaliacaoHabilidade::find($avaliacaoHabilidadeId);
                                        if (! $avaliacaoHabilidade || ! $avaliacaoHabilidade->turma_id) {
                                            return [];
                                        }

                                        return Matricula::where('turma_id', $avaliacaoHabilidade->turma_id)
                                            ->with('pessoa')
                                            ->get()
                                            ->mapWithKeys(fn ($m) => [$m->id => $m->pessoa->nome]);
                                    }),
                            ]),
                        Grid::make(1)
                            ->schema([
                                Select::make('conceito')
                                    ->options(ConceitoHabilidade::class)
                                    ->required(),
                                Textarea::make('observacao')
                                    ->label('Observações')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
