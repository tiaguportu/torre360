<?php

namespace App\Filament\Resources\Interessados\Schemas;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Filament\Resources\Pessoas\Schemas\PessoaForm;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Notifications\AcompanhamentoInteressadoNotification;
use App\Services\ConsultorWhatsappService;
use App\Services\LeadDuplicadoDetectorService;
use App\Services\LeadScoreService;
use App\Services\LeadSlaService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class InteressadoForm
{
    /**
     * Texto de confirmação do alerta de acompanhamento: o e-mail que vai receber a mensagem e um link do
     * WhatsApp do consultor, já com a mensagem do lead pronta (a mesma da ação "Enviar ao consultor").
     * O e-mail sai para o endereço do usuário consultor (canal `mail` da notificação, sem override de rota).
     */
    public static function descricaoDoAlerta(Interessado $record): HtmlString
    {
        $consultor = $record->loadMissing('usuario')->usuario;

        if (! $consultor) {
            return new HtmlString(e('Este interessado não tem consultor responsável, então não há para quem enviar o alerta.'));
        }

        $emails = blank($consultor->email)
            ? e("O consultor {$consultor->name} não tem e-mail cadastrado: só a notificação no sistema será enviada.")
            : e('E-mail que será enviado para:').'<br><strong>'.e($consultor->email).'</strong> ('.e($consultor->name).')';

        return new HtmlString(implode('<br><br>', [
            e('Uma notificação será enviada ao sistema e ao e-mail do consultor responsável.'),
            $emails,
            self::linkWhatsappDoAlerta($record, $consultor),
        ]));
    }

    /**
     * Link `wa.me` para o consultor com a mensagem do lead; sem telefone cadastrado, o WhatsApp abre sem
     * destinatário e quem clica escolhe o contato.
     */
    private static function linkWhatsappDoAlerta(Interessado $record, User $consultor): string
    {
        $whatsapp = app(ConsultorWhatsappService::class);

        $link = '<a href="'.e($whatsapp->urlParaInteressado($record)).'" target="_blank" rel="noopener" style="font-weight: 600; text-decoration: underline;">'
            .e("Abrir o WhatsApp de {$consultor->name} com a mensagem pronta").'</a>';

        $aviso = $whatsapp->consultorTemTelefone($record)
            ? ''
            : '<br>'.e("{$consultor->name} está sem telefone cadastrado: o WhatsApp abrirá para você escolher o contato.");

        return e('Se preferir, avise também pelo WhatsApp:').'<br>'.$link.$aviso;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.crm.aviso-lead-duplicado')
                    ->viewData(function (?Interessado $record): array {
                        if (! $record || ! $record->exists) {
                            return ['duplicados' => collect(), 'leadAtual' => null];
                        }

                        return [
                            'duplicados' => app(LeadDuplicadoDetectorService::class)->detectar($record),
                            'leadAtual' => $record,
                        ];
                    })
                    ->visible(fn (?Interessado $record): bool => (bool) ($record?->exists && app(LeadDuplicadoDetectorService::class)->temDuplicados($record))),
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
                                        ->modalDescription(fn (Interessado $record): HtmlString => self::descricaoDoAlerta($record))
                                        ->modalSubmitActionLabel('Sim, enviar alerta')
                                        ->extraAttributes([
                                            'class' => 'w-full justify-center py-4 text-lg font-bold animate-pulse',
                                        ]),
                                ])
                                    ->key('alertaContato')
                                    ->columnSpanFull()
                                    ->visible(fn (?Interessado $record) => $record?->precisaDeContato() ?? false),

                                // Resumo Visual (somente na edição)
                                Section::make('Resumo do Lead')
                                    ->icon('heroicon-o-chart-bar')
                                    ->schema([
                                        View::make('filament.resources.interessados.resumo-lead')
                                            ->viewData(fn (?Interessado $record): array => [
                                                'record' => $record,
                                                'fatores' => $record ? LeadScoreService::detalhar($record) : [],
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->columnSpanFull()
                                    ->visible(fn (?Interessado $record) => $record !== null),

                                Select::make('pessoa_id')
                                    ->label('Pessoa / Interessado')
                                    ->relationship('pessoa', 'nome')
                                    ->searchable()
                                    // Sem `preload()`: carregaria todas as pessoas do sistema (alunos, responsáveis, funcionários)
                                    // a cada abertura da ficha. A busca vem do servidor, limitada, por nome/e-mail/telefone/CPF.
                                    ->getSearchResultsUsing(fn (string $search): array => Pessoa::query()->busca($search)->orderBy('nome')->limit(50)->pluck('nome', 'id')->all())
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
                                    ->placeholder('(11) 98888-7777')
                                    ->mask(RawJs::make(<<<'JS'
                                        $input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'
                                        JS))
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

                                Select::make('campanha_marketing_id')
                                    ->label('Campanha de Marketing')
                                    ->relationship('campanha', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->helperText(fn (?Interessado $record): ?string => filled($record?->utm_source) || filled($record?->utm_campaign)
                                        ? 'UTM capturado: '.collect([$record->utm_source, $record->utm_medium, $record->utm_campaign])->filter()->implode(' / ')
                                        : null),

                                // Só quem pode atender leads aparece (contas de famílias, professores etc. ficam de fora);
                                // o consultor atual segue na lista mesmo que tenha perdido a permissão depois.
                                Select::make('usuario_id')
                                    ->label('Consultor Responsável')
                                    ->relationship(
                                        'usuario',
                                        'name',
                                        modifyQueryUsing: fn (Builder $query, ?Interessado $record) => $query
                                            ->where(fn (Builder $q) => $q->consultoresCrm()->orWhere($q->getModel()->getQualifiedKeyName(), $record?->usuario_id)),
                                    )
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
                                    ->helperText('Avaliação manual do consultor. Não é calculada automaticamente, mas entra no "Lead Score" com o maior peso.'),

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

                                TextInput::make('sla_primeira_resposta_minutos')
                                    ->label('SLA 1ª Resposta (minutos úteis)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('Padrão: 120 min úteis')
                                    ->helperText(function (?Interessado $record): string {
                                        if (! $record || ! $record->exists) {
                                            return 'Prazo em minutos comerciais (segunda a sexta, 08h-18h) para a 1ª resposta. Em branco usa o padrão da escola.';
                                        }

                                        $resumo = app(LeadSlaService::class)->resumoSla($record);

                                        return "{$resumo['texto']} • Limite comercial: {$resumo['limite']->format('d/m/Y H:i')}.";
                                    }),

                                Select::make('motivo_perda')
                                    ->label('Motivo da Perda')
                                    ->options(Interessado::MOTIVOS_PERDA)
                                    ->searchable()
                                    ->visible(fn (Get $get) => self::isStatusPerdido($get('status_interessado_id')))
                                    ->required(fn (Get $get) => self::isStatusPerdido($get('status_interessado_id')))
                                    ->columnSpanFull(),

                                Repeater::make('redes_sociais')
                                    ->label('Redes Sociais')
                                    ->schema([
                                        Select::make('rede')
                                            ->label('Rede')
                                            ->options(Interessado::REDES_SOCIAIS)
                                            ->native(false)
                                            ->required(),
                                        TextInput::make('url')
                                            ->label('Link do perfil')
                                            ->url()
                                            ->placeholder('https://instagram.com/usuario')
                                            ->maxLength(255)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Adicionar rede social')
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => Interessado::REDES_SOCIAIS[$state['rede'] ?? ''] ?? null)
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
                                        // Opcional: o formulário público também aceita aluno sem série definida.
                                        Select::make('serie_id')
                                            ->label('Série de Interesse')
                                            ->relationship('serie', 'nome'),
                                        Select::make('unidade_id')
                                            ->label('Unidade de Preferência')
                                            ->relationship('unidade', 'nome'),
                                        Select::make('turno_preferencia')
                                            ->label('Turno de Preferência')
                                            ->options(array_combine(InteressadoDependente::TURNOS_PREFERENCIA, InteressadoDependente::TURNOS_PREFERENCIA))
                                            ->native(false),
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

        return $status?->isPerda() ?? false;
    }
}
