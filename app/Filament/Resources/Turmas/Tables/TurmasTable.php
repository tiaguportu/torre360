<?php

namespace App\Filament\Resources\Turmas\Tables;

use App\Enums\SituacaoMatricula;
use App\Models\AvaliacaoHabilidade;
use App\Models\EtapaAvaliativa;
use App\Models\NotaHabilidade;
use App\Models\TemplateCracha;
use App\Models\Turma;
use App\Models\TurmaHorario;
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
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('turno.nome')
                    ->label('Turno')
                    ->sortable(),
                TextColumn::make('tipo_mediacao_didatico_pedagogica')
                    ->label('Mediação')
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        1 => '1 - Presencial',
                        2 => '2 - Semipresencial',
                        3 => '3 - EAD',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tipo_turma')
                    ->label('Tipo de Turma')
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        4 => '4 - Atividade complementar',
                        5 => '5 - AEE',
                        6 => '6 - Curricular',
                        9 => '9 - Curricular c/ Ativ. Comp.',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('turma_educacao_especial')
                    ->label('Ed. Especial')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('professorConselheiro.nome')
                    ->label('Professor Conselheiro')
                    ->sortable(),
                TextColumn::make('vagas_maximas')
                    ->label('Vagas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('carga_horaria_total')
                    ->label('Carga Horária (h)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    BulkAction::make('bulkEdit')
                        ->label('Editar em Lote')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->form([
                            Select::make('serie_id')
                                ->relationship('serie', 'nome')
                                ->label('Série')
                                ->searchable()
                                ->preload(),
                            Select::make('turno_id')
                                ->relationship('turno', 'nome')
                                ->label('Turno')
                                ->searchable()
                                ->preload(),
                            Select::make('professor_conselheiro_id')
                                ->relationship('professorConselheiro', 'nome')
                                ->label('Professor Conselheiro')
                                ->searchable(['nome', 'cpf'])
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                                ->preload(),
                            TextInput::make('vagas_maximas')
                                ->label('Vagas Máximas')
                                ->numeric(),
                            TextInput::make('carga_horaria_total')
                                ->label('Carga Horária Total (horas)')
                                ->numeric(),
                            Select::make('tipo_avaliacao')
                                ->label('Tipo de Avaliação')
                                ->options([
                                    'notas' => 'Notas',
                                    'habilidades' => 'Habilidades',
                                    'hibrido' => 'Híbrido',
                                ]),
                            Select::make('tipo_mediacao_didatico_pedagogica')
                                ->label('Tipo de Mediação Didático-Pedagógica')
                                ->options([
                                    1 => '1 - Presencial',
                                    2 => '2 - Semipresencial',
                                    3 => '3 - Educação a distância – EAD',
                                ]),
                            Select::make('tipo_turma')
                                ->label('Tipo de Turma')
                                ->options([
                                    4 => '4 - Atividade complementar',
                                    5 => '5 - Atendimento educacional especializado (AEE)',
                                    6 => '6 - Curricular (etapa de ensino)',
                                    9 => '9 - Curricular (etapa de ensino) com Atividade Complementar',
                                ]),
                            Select::make('local_funcionamento_diferenciado')
                                ->label('Local de Funcionamento Diferenciado da Turma')
                                ->options([
                                    0 => '0 - A turma não está em local de funcionamento diferenciado',
                                    1 => '1 - Sala anexa',
                                    2 => '2 - Unidade de atendimento socioeducativo',
                                    3 => '3 - Unidade prisional',
                                ]),
                            Select::make('turma_educacao_especial')
                                ->label('Turma de Educação Especial (Classe Especial)')
                                ->options([
                                    '1' => 'Sim',
                                    '0' => 'Não',
                                ]),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $updateData = array_filter($data, fn ($value) => $value !== null && $value !== '');
                            if (empty($updateData)) {
                                return;
                            }

                            if (array_key_exists('turma_educacao_especial', $updateData)) {
                                $updateData['turma_educacao_especial'] = (bool) $updateData['turma_educacao_especial'];
                            }

                            $records->each(fn ($record) => $record->update($updateData));

                            Notification::make()
                                ->title('Turmas atualizadas em lote com sucesso!')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->can('Update:Turma')),
                    BulkAction::make('definirHorariosLote')
                        ->label('Horários de Funcionamento em Lote')
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->modalHeading('Definir Horário de Funcionamento em Lote')
                        ->modalDescription('Selecione os dias da semana e horários para aplicar às turmas selecionadas.')
                        ->form([
                            Toggle::make('substituir_existentes')
                                ->label('Substituir horários anteriores das turmas')
                                ->default(true),
                            Repeater::make('horarios')
                                ->label('Horários por Dia da Semana')
                                ->schema([
                                    Select::make('dia_semana')
                                        ->label('Dia da Semana')
                                        ->options([
                                            0 => 'Domingo',
                                            1 => 'Segunda-feira',
                                            2 => 'Terça-feira',
                                            3 => 'Quarta-feira',
                                            4 => 'Quinta-feira',
                                            5 => 'Sexta-feira',
                                            6 => 'Sábado',
                                        ])
                                        ->required()
                                        ->distinct(),
                                    TimePicker::make('hora_inicio')
                                        ->label('Hora de Início')
                                        ->seconds(false)
                                        ->required(),
                                    TimePicker::make('hora_fim')
                                        ->label('Hora de Término')
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $horarios = $data['horarios'] ?? [];
                            if (empty($horarios)) {
                                return;
                            }

                            $substituir = $data['substituir_existentes'] ?? true;

                            foreach ($records as $turma) {
                                if ($substituir) {
                                    $turma->horariosFuncionamento()->delete();
                                }

                                foreach ($horarios as $item) {
                                    TurmaHorario::updateOrCreate(
                                        [
                                            'turma_id' => $turma->id,
                                            'dia_semana' => $item['dia_semana'],
                                        ],
                                        [
                                            'hora_inicio' => $item['hora_inicio'],
                                            'hora_fim' => $item['hora_fim'],
                                        ]
                                    );
                                }
                            }

                            Notification::make()
                                ->title('Horários de funcionamento aplicados em lote com sucesso!')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->can('Update:Turma')),
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
