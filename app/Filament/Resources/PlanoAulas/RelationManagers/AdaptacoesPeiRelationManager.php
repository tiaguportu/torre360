<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoAulas\RelationManagers;

use App\Enums\StatusAdaptacaoPei;
use App\Models\Matricula;
use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Services\CopilotoPedagogico\GerarPeiInput;
use App\Services\CopilotoPedagogicoIaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class AdaptacoesPeiRelationManager extends RelationManager
{
    protected static string $relationship = 'adaptacoesPei';

    protected static ?string $title = 'Adaptações Curriculares e PEI (Inclusão)';

    protected static ?string $modelLabel = 'Adaptação PEI';

    protected static ?string $pluralModelLabel = 'Adaptações PEI';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->label('Status da Adaptação')
                    ->options(StatusAdaptacaoPei::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var PlanoAula $planoAula */
        $planoAula = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (PlanoAulaAdaptacao $record): string => 'Matrícula: '.($record->matricula?->numero_matricula ?? 'MAT-'.$record->matricula_id)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('conteudo.prazo_revisao_dias')
                    ->label('Prazo Revisão')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} dias" : '15 dias')
                    ->alignCenter(),

                TextColumn::make('geradoPor.name')
                    ->label('Gerado Por')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('aprovado_em')
                    ->label('Aprovado Em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendente')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('gerarPei')
                    ->label('Gerar Adaptação PEI com IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->can('GerarPei:PlanoAula') && config('copiloto_pedagogico.pei_habilitado', false))
                    ->modalHeading('Copiloto de Inclusão: Geração de Adaptação Curricular (PEI)')
                    ->modalDescription('Selecione o estudante da turma para o qual a proposta de aula será adaptada conforme as diretrizes do DUA e LBI.')
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Estudante')
                            ->options(function () use ($planoAula) {
                                return Matricula::where('turma_id', $planoAula->turma_id)
                                    ->where('situacao', 'ativa')
                                    ->with('pessoa')
                                    ->get()
                                    ->mapWithKeys(fn (Matricula $m) => [$m->id => ($m->pessoa?->nome ?? 'Estudante').' ('.($m->numero_matricula ?? 'MAT-'.$m->id).')']);
                            })
                            ->searchable()
                            ->required(),

                        Textarea::make('observacoes_pedagogicas')
                            ->label('Observações Pedagógicas Adicionais do Docente (Opcional)')
                            ->helperText('Não inclua dados pessoais, documentos ou histórico clínico. O texto passará por anonimização automática antes do envio à IA.')
                            ->rows(3),
                    ])
                    ->action(function (array $data) use ($planoAula): void {
                        $matricula = Matricula::with([
                            'pessoa.necessidadesEducacaoEspecial.categoria',
                            'pessoa.transtornosAprendizagem.categoria',
                            'pessoa.recursosAcessibilidade.categoria',
                        ])->findOrFail((int) $data['matricula_id']);

                        $pessoa = $matricula->pessoa;

                        // Mapeia exclusivamente categorias padronizadas de acessibilidade
                        $catsNec = $pessoa?->necessidadesEducacaoEspecial?->pluck('categoria.nome')->filter()->values()->all() ?? [];
                        $catsTrans = $pessoa?->transtornosAprendizagem?->pluck('categoria.nome')->filter()->values()->all() ?? [];
                        $catsRec = $pessoa?->recursosAcessibilidade?->pluck('categoria.nome')->filter()->values()->all() ?? [];

                        $nascimento = $pessoa?->data_nascimento;
                        $idadeAnos = $nascimento instanceof Carbon ? $nascimento->age : 12;

                        $planoAula->loadMissing(['turma.serie', 'disciplina', 'habilidades']);

                        $habilidades = $planoAula->habilidades->map(fn ($h) => [
                            'codigo' => $h->codigo ?? '',
                            'nome' => $h->nome ?? '',
                        ])->all();

                        $input = new GerarPeiInput(
                            planoAulaId: $planoAula->id,
                            matriculaId: $matricula->id,
                            etapaEnsino: $planoAula->turma?->serie?->nome ?? 'Ensino Fundamental',
                            faixaEtariaAnos: max(3, min(25, $idadeAnos)),
                            disciplinaNome: $planoAula->disciplina?->nome ?? 'Componente Curricular',
                            temaOuObjetivoAula: (string) ($planoAula->objetivos ?? 'Atividade curricular'),
                            categoriasNecessidades: $catsNec,
                            categoriasTranstornos: $catsTrans,
                            categoriasRecursos: $catsRec,
                            habilidadesBncc: $habilidades,
                            observacaoPedagogicaHigienizada: $data['observacoes_pedagogicas'] ?? null,
                        );

                        $copilotoService = app(CopilotoPedagogicoIaService::class);
                        $output = $copilotoService->gerarAdaptacaoPei($input, auth()->user());

                        PlanoAulaAdaptacao::updateOrCreate(
                            [
                                'plano_aula_id' => $planoAula->id,
                                'matricula_id' => $matricula->id,
                            ],
                            [
                                'conteudo' => $output->toArray(),
                                'status' => StatusAdaptacaoPei::Rascunho,
                                'gerado_por_user_id' => auth()->id(),
                                'modelo_ia' => $output->modeloIa,
                            ]
                        );

                        Notification::make()
                            ->success()
                            ->title('Adaptação PEI gerada com sucesso!')
                            ->body('A proposta de adaptação inclusiva foi gerada e armazenada de forma encriptada. Revise o documento.')
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('visualizar')
                    ->label('Ver Detalhes')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (PlanoAulaAdaptacao $record): string => 'Adaptação PEI: '.($record->matricula?->pessoa?->nome ?? 'Estudante'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->schema([
                        Textarea::make('barreiras')
                            ->label('Barreiras Identificadas')
                            ->rows(3)
                            ->disabled()
                            ->default(fn (PlanoAulaAdaptacao $record) => $record->conteudo['barreiras'] ?? ''),

                        Textarea::make('adaptacoes')
                            ->label('Adaptações Práticas Curriculares')
                            ->rows(6)
                            ->disabled()
                            ->default(function (PlanoAulaAdaptacao $record): string {
                                $p = $record->conteudo['adaptacoes_praticas'] ?? [];

                                return '• Instruções: '.($p['instrucoes_passo_a_passo'] ?? '-')."\n"
                                    .'• Apoio Visual: '.($p['apoio_visual'] ?? '-')."\n"
                                    .'• Tempo Estendido: '.($p['tempo_estendido'] ?? '-')."\n"
                                    .'• Recursos/Acessibilidade: '.($p['recursos_acessibilidade'] ?? '-')."\n"
                                    .'• Ambiente: '.($p['organizacao_ambiente'] ?? '-');
                            }),

                        Textarea::make('estrategias')
                            ->label('Estratégias Mediadoras para o Professor')
                            ->rows(3)
                            ->disabled()
                            ->default(fn (PlanoAulaAdaptacao $record) => $record->conteudo['estrategias_professor'] ?? ''),
                    ]),

                Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PlanoAulaAdaptacao $record) => $record->status !== StatusAdaptacaoPei::Aprovado && auth()->user()?->can('GerarPei:PlanoAula'))
                    ->requiresConfirmation()
                    ->modalHeading('Homologar Adaptação PEI')
                    ->modalDescription('Deseja aprovar e homologar esta adaptação pedagógica oficial?')
                    ->action(function (PlanoAulaAdaptacao $record): void {
                        $record->update([
                            'status' => StatusAdaptacaoPei::Aprovado,
                            'aprovado_por_user_id' => auth()->id(),
                            'aprovado_em' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Adaptação PEI Aprovada!')
                            ->body('O documento agora consta como homologado oficialmente.')
                            ->send();
                    }),

                Action::make('exportarPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn () => auth()->user()?->can('ExportarPei:PlanoAula'))
                    ->url(fn (PlanoAulaAdaptacao $record): string => route('planos-aula.adaptacoes.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->stackedOnMobile();
    }
}
