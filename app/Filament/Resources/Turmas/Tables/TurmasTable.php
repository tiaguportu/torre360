<?php

namespace App\Filament\Resources\Turmas\Tables;

use App\Enums\SituacaoMatricula;
use App\Models\AvaliacaoHabilidade;
use App\Models\EtapaAvaliativa;
use App\Models\NotaHabilidade;
use App\Models\TemplateCracha;
use App\Models\Turma;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

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
                                    ->afterStateUpdated(fn (Set $set, Get $get, Turma $record) => self::updateAvaliacoesState($set, $get, $record)),
                                Select::make('habilidade_id')
                                    ->label('Habilidade')
                                    ->options(function (Turma $record) {
                                        if (! \Illuminate\Support\Facades\Schema::hasTable('turma_habilidade')) {
                                            return [];
                                        }

                                        return $record->habilidades->pluck('nome', 'id');
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, Get $get, Turma $record) => self::updateAvaliacoesState($set, $get, $record)),
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
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Salvar Avaliações'),
                Action::make('imprimirBoletins')
                    ->label('Imprimir Boletins')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->modalHeading('Imprimir Boletins da Turma')
                    ->modalDescription('Selecione a etapa avaliativa para os boletins desta turma.')
                    ->modalSubmitActionLabel('Baixar PDF')
                    ->form([
                        Select::make('etapa_id')
                            ->label('Etapa Avaliativa')
                            ->options(function () {
                                $etapas = EtapaAvaliativa::query()
                                    ->orderBy('id')
                                    ->pluck('nome', 'id')
                                    ->toArray();

                                return [0 => 'Todas as Etapas'] + $etapas;
                            })
                            ->default(0)
                            ->required(),
                    ])
                    ->action(function (Turma $record, array $data) {
                        $params = [
                            'turma_ids' => [$record->id],
                        ];
                        if ($data['etapa_id'] > 0) {
                            $params['etapa_id'] = $data['etapa_id'];
                        }

                        return redirect()->route('turmas.boletins.download', $params);
                    })
                    ->visible(fn (Turma $record) => auth()->user()->can('Boletim:Matricula') && (
                        $record->matriculas()->whereHas('notas', fn ($q) => $q->whereNotNull('valor'))->exists() ||
                        NotaHabilidade::whereIn('matricula_id', $record->matriculas()->pluck('id'))->exists()
                    )),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('imprimirBoletinsLote')
                        ->label('Imprimir Boletins em Lote')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->modalHeading('Imprimir Boletins das Turmas')
                        ->modalDescription('Selecione a etapa avaliativa para os boletins das turmas selecionadas.')
                        ->modalSubmitActionLabel('Baixar PDF')
                        ->form([
                            Select::make('etapa_id')
                                ->label('Etapa Avaliativa')
                                ->options(function () {
                                    $etapas = EtapaAvaliativa::query()
                                        ->orderBy('id')
                                        ->pluck('nome', 'id')
                                        ->toArray();

                                    return [0 => 'Todas as Etapas'] + $etapas;
                                })
                                ->default(0)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $params = [
                                'turma_ids' => $records->pluck('id')->toArray(),
                            ];
                            if ($data['etapa_id'] > 0) {
                                $params['etapa_id'] = $data['etapa_id'];
                            }

                            return redirect()->route('turmas.boletins.download', $params);
                        })
                        ->visible(fn () => auth()->user()->can('Boletim:Matricula')),
                    BulkAction::make('imprimirCrachasLote')
                        ->label('Imprimir Crachá dos Alunos')
                        ->icon('heroicon-o-identification')
                        ->color('success')
                        ->form([
                            Select::make('template_cracha_id')
                                ->label('Selecione o Modelo de Crachá')
                                ->options(fn () => TemplateCracha::pluck('nome', 'id'))
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $template = TemplateCracha::find($data['template_cracha_id']);
                            if (! $template) {
                                Notification::make()
                                    ->danger()
                                    ->title('Template não encontrado')
                                    ->send();

                                return;
                            }

                            // Busca as matrículas ativas de todas as turmas selecionadas
                            $pessoasComTurma = collect();
                            foreach ($records as $turma) {
                                $matriculas = $turma->matriculas()
                                    ->where('situacao', SituacaoMatricula::ATIVA)
                                    ->with('pessoa')
                                    ->get();

                                foreach ($matriculas as $m) {
                                    if ($m->pessoa) {
                                        $pessoasComTurma->push((object) [
                                            'pessoa' => $m->pessoa,
                                            'turma' => $turma,
                                        ]);
                                    }
                                }
                            }

                            if ($pessoasComTurma->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Nenhum aluno ativo encontrado nas turmas selecionadas')
                                    ->send();

                                return null;
                            }

                            $layout = $template->dados_layout;
                            $objects = $layout['objects'] ?? [];
                            $backgroundImage = $layout['backgroundImage']['src'] ?? null;

                            // Dimensões do crachá em pontos (pixels * 0.75)
                            $crachaLargura = $template->largura * 0.75;
                            $crachaAltura = $template->altura * 0.75;

                            $pdf = Pdf::loadView('pdf.cracha-lote', [
                                'pessoasComTurma' => $pessoasComTurma,
                                'objects' => $objects,
                                'backgroundImage' => $backgroundImage,
                                'crachaLargura' => $crachaLargura,
                                'crachaAltura' => $crachaAltura,
                            ])->setPaper('a4', 'portrait');

                            return response()->streamDownload(
                                fn () => print ($pdf->output()),
                                'crachas_turmas.pdf',
                                ['Content-Type' => 'application/pdf']
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                ]),
            ])
            ->stackedOnMobile();
    }

    protected static function updateAvaliacoesState(Set $set, Get $get, Turma $record): void
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
