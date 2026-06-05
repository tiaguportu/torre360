<?php

namespace App\Filament\Resources\NotaHabilidades\Schemas;

use App\Enums\ConceitoHabilidade;
use App\Models\AvaliacaoHabilidade;
use App\Models\Matricula;
use App\Models\Turma;
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
                        Grid::make(3)
                            ->schema([
                                Select::make('avaliacao_habilidade_id')
                                    ->label('Avaliação de Habilidade')
                                    ->relationship('avaliacaoHabilidade', 'id')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => sprintf(
                                        '%s (%s)',
                                        $record->turma?->nome ?? 'Sem Turma',
                                        $record->etapaAvaliativa?->nome ?? 'Sem Etapa'
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($set) {
                                        $set('matricula_id', null);
                                        $set('habilidade_id', null);
                                    }),
                                Select::make('habilidade_id')
                                    ->label('Habilidade')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->options(function (callable $get) {
                                        $avaliacaoHabilidadeId = $get('avaliacao_habilidade_id');
                                        if (! $avaliacaoHabilidadeId) {
                                            return [];
                                        }

                                        $avaliacaoHabilidade = AvaliacaoHabilidade::find($avaliacaoHabilidadeId);
                                        if (! $avaliacaoHabilidade || ! $avaliacaoHabilidade->turma_id) {
                                            return [];
                                        }

                                        return Turma::find($avaliacaoHabilidade->turma_id)?->habilidades?->pluck('nome', 'id') ?? [];
                                    })
                                    ->helperText(function (callable $get) {
                                        $avaliacaoHabilidadeId = $get('avaliacao_habilidade_id');
                                        if (! $avaliacaoHabilidadeId) {
                                            return null;
                                        }
                                        $avaliacaoHabilidade = AvaliacaoHabilidade::find($avaliacaoHabilidadeId);
                                        if ($avaliacaoHabilidade && $avaliacaoHabilidade->turma_id && Turma::find($avaliacaoHabilidade->turma_id)?->habilidades()->count() === 0) {
                                            return 'Atenção: A turma desta avaliação não possui habilidades vinculadas. Vincule-as no cadastro da turma.';
                                        }

                                        return null;
                                    }),
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
