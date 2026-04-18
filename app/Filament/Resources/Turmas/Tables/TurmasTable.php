<?php

namespace App\Filament\Resources\Turmas\Tables;

use App\Enums\SituacaoMatricula;
use App\Models\AvaliacaoHabilidade;
use App\Models\EtapaAvaliativa;
use App\Models\Turma;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\StaticAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Schema;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TurmasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('turno.nome')
                    ->label('Turno')
                    ->sortable(),
                TextColumn::make('professorConselheiro.nome')
                    ->label('Professor Conselheiro')
                    ->sortable(),
                TextColumn::make('vagas_maximas')
                    ->label('Vagas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('avaliarHabilidades')
                    ->label('Avaliar Habilidades')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                Select::make('etapa_avaliativa_id')
                                    ->label('Etapa Avaliativa')
                                    ->options(EtapaAvaliativa::pluck('nome', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, Turma $record) => self::updateAvaliacoesState($set, $get, $record)),
                                Select::make('habilidade_id')
                                    ->label('Habilidade')
                                    ->options(fn (Turma $record) => $record->habilidades->pluck('nome', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, Turma $record) => self::updateAvaliacoesState($set, $get, $record)),
                            ]),
                        Repeater::make('avaliacoes')
                            ->label('Avaliação dos Alunos')
                            ->schema([
                                Hidden::make('matricula_id'),
                                TextInput::make('aluno_nome')
                                    ->label('Aluno')
                                    ->disabled()
                                    ->dehydrated(false),
                                Select::make('conceito')
                                    ->label('Conceito')
                                    ->options([
                                        'Pleno' => 'Pleno',
                                        'Básico' => 'Básico',
                                        'Insuficiente' => 'Insuficiente',
                                        'Não Avaliado' => 'Não Avaliado',
                                    ])
                                    ->required(),
                                TextInput::make('observacao')
                                    ->label('Observação'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->columns(3)
                            ->grid(1)
                            ->itemLabel(fn (array $state): ?string => $state['aluno_nome'] ?? null),
                    ])
                    ->mountUsing(function (Schema $schema, Turma $record) {
                        $matriculas = $record->matriculas()
                            ->where('situacao', SituacaoMatricula::ATIVA)
                            ->with('pessoa')
                            ->get();

                        $avaliacoes = $matriculas->map(fn ($m) => [
                            'matricula_id' => $m->id,
                            'aluno_nome' => $m->pessoa->nome,
                            'conceito' => 'Pleno',
                            'observacao' => null,
                        ])->toArray();

                        $schema->fill(['avaliacoes' => $avaliacoes]);
                    })
                    ->action(function (array $data) {
                        foreach ($data['avaliacoes'] as $av) {
                            AvaliacaoHabilidade::updateOrCreate(
                                [
                                    'matricula_id' => $av['matricula_id'],
                                    'habilidade_id' => $data['habilidade_id'],
                                    'etapa_avaliativa_id' => $data['etapa_avaliativa_id'],
                                ],
                                [
                                    'conceito' => $av['conceito'],
                                    'observacao' => $av['observacao'],
                                ]
                            );
                        }

                        Notification::make()
                            ->title('Avaliações salvas com sucesso!')
                            ->success()
                            ->send();
                    })
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->modalSubmitActionLabel('Salvar Avaliações'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    protected static function updateAvaliacoesState(\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, Turma $record): void
    {
        $etapaId = $get('etapa_avaliativa_id');
        $habilidadeId = $get('habilidade_id');

        if (! $etapaId || ! $habilidadeId) {
            return;
        }

        $matriculas = $record->matriculas()
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->with('pessoa')
            ->get();

        $avaliacoesExistentes = AvaliacaoHabilidade::where('habilidade_id', $habilidadeId)
            ->where('etapa_avaliativa_id', $etapaId)
            ->whereIn('matricula_id', $matriculas->pluck('id'))
            ->get()
            ->keyBy('matricula_id');

        $avaliacoes = $matriculas->map(fn ($m) => [
            'matricula_id' => $m->id,
            'aluno_nome' => $m->pessoa->nome,
            'conceito' => $avaliacoesExistentes[$m->id]->conceito ?? 'Pleno',
            'observacao' => $avaliacoesExistentes[$m->id]->observacao ?? null,
        ])->toArray();

        $set('avaliacoes', $avaliacoes);
    }
}
