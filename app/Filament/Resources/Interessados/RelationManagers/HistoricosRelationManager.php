<?php

namespace App\Filament\Resources\Interessados\RelationManagers;

use App\Models\HistoricoContato;
use App\Services\LeadFunilService;
use App\Services\LeadScoreService;
use App\Services\ProximaAcaoLeadService;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoricosRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';

    protected static ?string $title = 'Histórico de Contatos';

    /** @var array{quando: mixed, acao: mixed}|null Próxima ação digitada no formulário de contato, até o `after` aplicá-la ao lead. */
    protected ?array $proximaAcaoInformada = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo_contato_interessado_id')
                    ->label('Tipo de Contato')
                    ->relationship('tipoContato', 'nome')
                    ->live()
                    ->required(),
                DateTimePicker::make('data_contato')
                    ->label('Data do Contato')
                    ->default(now())
                    ->required(),
                TextInput::make('duracao_minutos')
                    ->label('Duração (minutos)')
                    ->numeric()
                    ->minValue(1),
                Select::make('resultado')
                    ->label('Resultado')
                    ->options([
                        'agendou_visita' => 'Agendou Visita',
                        'retornar' => 'Retornar depois',
                        'sem_interesse' => 'Sem Interesse',
                        'matriculou' => 'Efetuou Matrícula',
                        'outro' => 'Outro',
                    ])
                    ->live(),
                Textarea::make('relato')
                    ->label('Relato do Atendimento')
                    ->required()
                    ->columnSpanFull(),
                // Próxima ação obrigatória (Onda 84). Não são colunas do histórico: vão para o lead ao gravar o contato.
                TextInput::make('proxima_acao')
                    ->label('Próxima ação')
                    ->placeholder('Ex.: ligar para confirmar a visita')
                    ->maxLength(160)
                    ->required(fn (Get $get): bool => app(ProximaAcaoLeadService::class)->exigidaParaContato($get('resultado'), $get('tipo_contato_interessado_id')))
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && app(ProximaAcaoLeadService::class)->exigidaParaContato($get('resultado'), $get('tipo_contato_interessado_id'))),
                DateTimePicker::make('data_proximo_contato')
                    ->label('Quando')
                    ->default(now()->addDays(2))
                    ->seconds(false)
                    ->required(fn (Get $get): bool => app(ProximaAcaoLeadService::class)->exigidaParaContato($get('resultado'), $get('tipo_contato_interessado_id')))
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && app(ProximaAcaoLeadService::class)->exigidaParaContato($get('resultado'), $get('tipo_contato_interessado_id'))),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('relato')
            ->defaultSort('data_contato', 'desc')
            ->columns([
                TextColumn::make('tipoContato.nome')
                    ->label('Tipo'),
                TextColumn::make('data_contato')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('usuario.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->icon('heroicon-o-user'),
                TextColumn::make('automatico')
                    ->label('Registro')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Automático' : 'Manual')
                    ->color(fn (bool $state): string => $state ? 'gray' : 'success')
                    ->tooltip('Registros automáticos (régua de e-mails, análises de IA) não contam como interação nem zeram o "Sem interação".')
                    ->toggleable(),
                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'agendou_visita' => 'success',
                        'matriculou' => 'success',
                        'retornar' => 'warning',
                        'sem_interesse' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'agendou_visita' => 'Agendou Visita',
                        'retornar' => 'Retornar',
                        'sem_interesse' => 'Sem Interesse',
                        'matriculou' => 'Matriculou',
                        'outro' => 'Outro',
                        default => '—',
                    }),
                TextColumn::make('duracao_minutos')
                    ->label('Duração')
                    ->suffix(' min')
                    ->placeholder('—'),
                TextColumn::make('relato')
                    ->label('Relato')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar Contato')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['usuario_id'] = auth()->id();

                        // A próxima ação é do lead, não do contato: guarda para o `after` e tira do que vai ao histórico.
                        $this->proximaAcaoInformada = [
                            'quando' => $data['data_proximo_contato'] ?? null,
                            'acao' => $data['proxima_acao'] ?? null,
                        ];
                        unset($data['data_proximo_contato'], $data['proxima_acao']);

                        return $data;
                    })
                    ->after(function (HistoricoContato $record): void {
                        $interessado = $this->getOwnerRecord();

                        // Primeiro contato da escola com a família: encerra o SLA de 1ª resposta.
                        app(LeadFunilService::class)->registrarPrimeiroContato($interessado, $record);

                        $proxima = $this->proximaAcaoInformada ?? ['quando' => null, 'acao' => null];
                        $this->proximaAcaoInformada = null;

                        // Registro administrativo (funil, formulário do site) não é conversa: não toca na próxima ação do lead.
                        if (app(ProximaAcaoLeadService::class)->tipoAdministrativo($record->tipo_contato_interessado_id)) {
                            LeadScoreService::recalcular($interessado);

                            return;
                        }

                        try {
                            app(ProximaAcaoLeadService::class)->definir(
                                $interessado,
                                filled($proxima['quando']) ? Carbon::parse($proxima['quando']) : null,
                                $proxima['acao'],
                                $record->resultado,
                            );
                        } catch (\DomainException $e) {
                            Notification::make()->title('Contato registrado, mas a próxima ação não foi marcada')->body($e->getMessage())->warning()->send();
                        }

                        LeadScoreService::recalcular($interessado);
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->after(fn () => LeadScoreService::recalcular($this->getOwnerRecord())),
                DeleteAction::make()
                    ->after(fn () => LeadScoreService::recalcular($this->getOwnerRecord())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn () => LeadScoreService::recalcular($this->getOwnerRecord())),
                ]),
            ])
            ->stackedOnMobile();
    }
}
