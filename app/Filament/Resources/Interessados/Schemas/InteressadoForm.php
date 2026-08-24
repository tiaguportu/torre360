<?php

namespace App\Filament\Resources\Interessados\Schemas;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Filament\Resources\Pessoas\Schemas\PessoaForm;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Notifications\AcompanhamentoInteressadoNotification;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InteressadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('CRM')
                    ->tabs([
                        Tab::make('Dados do Negócio')
                            ->schema([
                                Actions::make([
                                    Action::make('alerta_contato')
                                        ->label('ATENÇÃO: Este interessado precisa de contato urgente! Clique aqui para alertar o consultor.')
                                        ->icon('heroicon-m-exclamation-triangle')
                                        ->color('danger')
                                        ->badge()
                                        ->action(function (Interessado $record) {
                                            $consultor = $record->usuario;

                                            if ($consultor) {
                                                // Notificação de E-mail
                                                $consultor->notify(new AcompanhamentoInteressadoNotification($record));

                                                // Notificação do SININHO (Filament Database)
                                                FilamentNotification::make()
                                                    ->title('Acompanhamento de Interessado Pendente')
                                                    ->body("O interessado {$record->pessoa->nome} precisa de contato urgente.")
                                                    ->icon('heroicon-o-exclamation-triangle')
                                                    ->color('danger')
                                                    ->actions([
                                                        Action::make('view')
                                                            ->label('Ver Interessado')
                                                            ->url(InteressadoResource::getUrl('edit', ['record' => $record]))
                                                            ->button(),
                                                    ])
                                                    ->sendToDatabase($consultor);

                                                FilamentNotification::make()
                                                    ->title('Consultor Notificado!')
                                                    ->body("O consultor {$consultor->name} recebeu um alerta por e-mail e no sininho.")
                                                    ->success()
                                                    ->send();
                                            } else {
                                                FilamentNotification::make()
                                                    ->title('Falha ao Notificar')
                                                    ->body('Não há um consultor responsável vinculado a este interessado.')
                                                    ->danger()
                                                    ->send();
                                            }
                                        })
                                        ->requiresConfirmation()
                                        ->modalHeading('Enviar Alerta de Acompanhamento?')
                                        ->modalDescription('Uma notificação será enviada ao sistema e ao e-mail do consultor responsável.')
                                        ->modalSubmitActionLabel('Sim, enviar alerta')
                                        ->extraAttributes([
                                            'class' => 'w-full justify-center py-4 text-lg font-bold animate-pulse',
                                        ]),
                                ])
                                    ->columnSpanFull()
                                    ->visible(fn (?Interessado $record) => $record?->precisaDeContato() ?? false),

                                // Resumo Visual (somente na edição)
                                Section::make('Resumo do Lead')
                                    ->schema([
                                        Placeholder::make('dias_funil')
                                            ->label('Dias no Funil')
                                            ->content(fn (Interessado $record): string => $record->diasNoFunil().' dias'),
                                        Placeholder::make('total_contatos')
                                            ->label('Total de Contatos')
                                            ->content(fn (Interessado $record): string => $record->totalContatos().' contato(s)'),
                                        Placeholder::make('lead_score')
                                            ->label('Lead Score (automático)')
                                            ->content(fn (Interessado $record): string => $record->lead_score !== null
                                                ? "{$record->lead_score} / 100"
                                                : 'Ainda não calculado'),
                                        Placeholder::make('lead_score_detalhamento')
                                            ->label('Detalhamento do Score')
                                            ->columnSpanFull()
                                            ->content(fn (Interessado $record): string => collect(LeadScoreService::detalhar($record))
                                                ->map(fn (array $fator) => "{$fator['fator']}: {$fator['pontos']}/{$fator['maximo']}")
                                                ->implode(' · ')),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->visible(fn (?Interessado $record) => $record !== null),

                                Select::make('pessoa_id')
                                    ->label('Pessoa / Interessado')
                                    ->relationship('pessoa', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $pessoa = Pessoa::find($state);
                                            $set('pessoa_email', $pessoa?->email);
                                            $set('pessoa_telefone', $pessoa?->telefone);
                                        } else {
                                            $set('pessoa_email', null);
                                            $set('pessoa_telefone', null);
                                        }
                                    })
                                    ->createOptionForm(fn (Schema $schema) => PessoaForm::configure($schema)->getComponents()),

                                TextInput::make('pessoa_email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(255)
                                    ->afterStateHydrated(fn (Set $set, Get $get) => $set('pessoa_email', Pessoa::find($get('pessoa_id'))?->email)),

                                TextInput::make('pessoa_telefone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->maxLength(20)
                                    ->afterStateHydrated(fn (Set $set, Get $get) => $set('pessoa_telefone', Pessoa::find($get('pessoa_id'))?->telefone)),

                                Select::make('status_interessado_id')
                                    ->label('Status')
                                    ->relationship('status', 'nome')
                                    ->required()
                                    ->native(false),

                                Select::make('origem_interessado_id')
                                    ->label('Origem')
                                    ->relationship('origem', 'nome')
                                    ->required()
                                    ->native(false),

                                Select::make('usuario_id')
                                    ->label('Consultor Responsável')
                                    ->relationship('usuario', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false),

                                Select::make('temperatura')
                                    ->label('Temperatura (percepção do consultor)')
                                    ->options([
                                        'quente' => '🔥 Quente',
                                        'morno' => '🟡 Morno',
                                        'frio' => '🔵 Frio',
                                    ])
                                    ->native(false)
                                    ->helperText('Avaliação manual e subjetiva do consultor. Não é calculada automaticamente — use o "Lead Score" no resumo para o indicador automático.'),

                                Select::make('faixa_distancia_escola')
                                    ->label('Distância até a Escola')
                                    ->options([
                                        'ate_2km' => 'Até 2km',
                                        'de_2_a_5km' => '2 a 5km',
                                        'de_5_a_10km' => '5 a 10km',
                                        'mais_de_10km' => 'Mais de 10km',
                                    ])
                                    ->native(false),

                                Select::make('meio_transporte')
                                    ->label('Meio de Transporte')
                                    ->options([
                                        'carro_proprio' => 'Carro próprio',
                                        'van_escolar' => 'Van escolar',
                                        'transporte_publico' => 'Transporte público',
                                        'a_pe_ou_bicicleta' => 'A pé / Bicicleta',
                                    ])
                                    ->native(false),

                                TextInput::make('valor_estimado')
                                    ->label('Valor Estimado (R$)')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->minValue(0),

                                DateTimePicker::make('data_proximo_contato')
                                    ->label('Próximo Contato')
                                    ->native(false),

                                TextInput::make('motivo_perda')
                                    ->label('Motivo da Perda')
                                    ->visible(fn (Get $get) => self::isStatusPerdido($get('status_interessado_id')))
                                    ->columnSpanFull(),

                                Textarea::make('observacoes')
                                    ->label('Observações')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tab::make('Dependentes')
                            ->schema([
                                Repeater::make('dependentes')
                                    ->relationship('dependentes')
                                    ->schema([
                                        TextInput::make('nome_crianca')
                                            ->label('Nome da Criança')
                                            ->required(),
                                        Select::make('serie_id')
                                            ->label('Série de Interesse')
                                            ->relationship('serie', 'nome')
                                            ->required(),
                                        DatePicker::make('data_nascimento')
                                            ->label('Data de Nascimento')
                                            ->native(false),
                                        Select::make('vinculo')
                                            ->label('Vínculo')
                                            ->options([
                                                'Pai' => 'Pai',
                                                'Mãe' => 'Mãe',
                                                'Parente' => 'Parente',
                                                'Tutor' => 'Tutor',
                                            ])
                                            ->native(false),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->itemLabel(fn (array $state): ?string => $state['nome_crianca'] ?? null),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Verifica se o status selecionado é um status de perda.
     */
    private static function isStatusPerdido(?int $statusId): bool
    {
        if (! $statusId) {
            return false;
        }

        $status = StatusInteressado::find($statusId);

        return $status && $status->is_final && ! $status->is_ganho;
    }
}
