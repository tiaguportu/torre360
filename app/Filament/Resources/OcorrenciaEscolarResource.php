<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OcorrenciaEscolarResource\Pages;
use App\Models\OcorrenciaEscolar;
use App\Models\TipoOcorrencia;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OcorrenciaEscolarResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = OcorrenciaEscolar::class;

    protected static ?string $modelLabel = 'Ocorrência Escolar';

    protected static ?string $pluralModelLabel = 'Ocorrências da Rotina';

    protected static string|\UnitEnum|null $navigationGroup = 'Convivência e Disciplina';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'reenviarNotificacao',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação e Tipo da Ocorrência')
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Aluno (Matrícula Ativa)')
                            ->relationship('matricula', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->pessoa?->nome} - Turma: {$record->turma?->nome} ({$record->periodoLetivo?->nome})")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('tipo_ocorrencia_id')
                            ->label('Tipo de Ocorrência')
                            ->relationship('tipoOcorrencia', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    $tipo = TipoOcorrencia::find($state);
                                    if ($tipo) {
                                        $set('notificar_responsaveis', $tipo->notificar_responsaveis_padrao);
                                    }
                                }
                            }),

                        DateTimePicker::make('data_hora')
                            ->label('Data e Hora do Ocorrido')
                            ->default(now())
                            ->native(false)
                            ->required(),

                        Select::make('registrado_por_user_id')
                            ->label('Registrado Por')
                            ->relationship('registradoPor', 'name')
                            ->default(auth()->id())
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Relato & Providências')
                    ->schema([
                        Textarea::make('descricao')
                            ->label('Relato Detalhado da Ocorrência')
                            ->placeholder('Descreva o que ocorreu (atraso, falta de uniforme, conduta, elogio...)')
                            ->required()
                            ->rows(4),

                        Textarea::make('providencias_tomadas')
                            ->label('Providências / Medidas Tomadas')
                            ->placeholder('Ex: Conversa com o estudante, orientação pedagógica, comunicação à coordenação...')
                            ->rows(3),
                    ]),

                Section::make('Notificação aos Responsáveis em Tempo Real')
                    ->description('Marque se deseja disparar notificação aos responsáveis cadastrados do aluno.')
                    ->schema([
                        Toggle::make('notificar_responsaveis')
                            ->label('Enviar Alerta / Notificação em Tempo Real aos Responsáveis')
                            ->helperText('Caso ativo, enviará notificação no painel, e-mail e push aos pais.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('matricula.turma.nome')
                    ->label('Turma')
                    ->searchable(),

                TextColumn::make('tipoOcorrencia.nome')
                    ->label('Tipo de Ocorrência')
                    ->badge()
                    ->color(fn ($record) => match ($record->tipoOcorrencia?->gravidade) {
                        'positiva' => 'success',
                        'leve' => 'info',
                        'media' => 'warning',
                        'grave' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('descricao')
                    ->label('Relato')
                    ->limit(40),

                IconColumn::make('notificar_responsaveis')
                    ->label('Notificação Ativa')
                    ->boolean(),

                TextColumn::make('notificacao_enviada_em')
                    ->label('Enviada em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Não enviada'),
            ])
            ->actions([
                Action::make('reenviarNotificacao')
                    ->label('Reenviar Notificação')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->authorize(fn (OcorrenciaEscolar $record) => auth()->user()?->can('reenviarNotificacao', $record))
                    ->action(function (OcorrenciaEscolar $record) {
                        $record->update(['notificar_responsaveis' => true]);
                        $enviado = $record->enviarNotificacaoResponsaveis();

                        if ($enviado) {
                            Notification::make()
                                ->title('Notificação enviada aos responsáveis!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Nenhum usuário responsável encontrado para notificar.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('data_hora', 'desc')
            ->stackedOnMobile();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOcorrenciaEscolars::route('/'),
            'create' => Pages\CreateOcorrenciaEscolar::route('/create'),
            'edit' => Pages\EditOcorrenciaEscolar::route('/{record}/edit'),
        ];
    }
}
