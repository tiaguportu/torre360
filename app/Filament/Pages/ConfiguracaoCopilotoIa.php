<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\CopilotoIaConfiguracao;
use App\Services\CrmIaVendasService;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ConfiguracaoCopilotoIa extends Page implements HasForms
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithForms;

    /** Chaves fixas (usadas pelos selects do Copiloto); só as descrições são editáveis. */
    private const ROTULOS_OBJETIVOS = [
        'primeiro_contato' => '👋 Primeiro Contato',
        'convite_visita' => '🏫 Convite para Tour Pedagógico',
        'quebra_objecao' => '🛡️ Superar Dúvidas / Objeções',
        'reativacao' => '🔄 Reativar Família Sumida',
        'fechamento' => '🎓 Fechamento de Matrícula',
    ];

    private const ROTULOS_TONS = [
        'acolhedor' => '❤️ Acolhedor & Educacional',
        'objetivo' => '⚡ Objetivo & Prático',
        'inspirador' => '✨ Inspirador & Entusiasta',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $navigationLabel = 'Comportamento do Copiloto IA';

    protected static ?string $title = 'Comportamento do Copiloto IA';

    protected static ?string $slug = 'crm/comportamento-copiloto-ia';

    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('content')->fill($this->paraFormulario(CopilotoIaConfiguracao::valores()));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Persona e diretrizes')
                    ->description('Quem a IA é e as regras que toda mensagem deve seguir. As diretrizes são numeradas automaticamente. A linha do objetivo e do tom, o contrato de saída (texto puro, sem links) e a proteção contra instruções maliciosas nos dados do lead são fixos e sempre aplicados.')
                    ->schema([
                        Textarea::make('persona')
                            ->label('Persona e função')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Repeater::make('diretrizes')
                            ->label('Diretrizes obrigatórias')
                            ->schema([
                                Textarea::make('texto')
                                    ->label('Diretriz')
                                    ->rows(2)
                                    ->required()
                                    ->maxLength(500),
                            ])
                            ->addActionLabel('Adicionar diretriz')
                            ->minItems(1)
                            ->maxItems(15)
                            ->itemLabel(fn (array $state): ?string => filled($state['texto'] ?? null) ? str($state['texto'])->limit(70)->toString() : null)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make('O que a IA menciona e evita')
                    ->description('Texto livre, uma ideia por linha. Entra no prompt depois das diretrizes.')
                    ->schema([
                        Textarea::make('mencionar')
                            ->label('Sempre mencionar / destacar')
                            ->placeholder("Ex:\nPeríodo integral com almoço incluso\nProjeto bilíngue desde a educação infantil")
                            ->rows(4)
                            ->maxLength(1500),
                        Textarea::make('evitar')
                            ->label('Nunca mencionar nem prometer')
                            ->placeholder("Ex:\nDescontos ou bolsas não aprovados\nValores de mensalidade\nGarantia de vaga sem pré-matrícula")
                            ->rows(4)
                            ->maxLength(1500),
                    ])
                    ->columns(2),

                Section::make('Objetivos da mensagem')
                    ->description('O que cada objetivo pede à IA. Os nomes dos objetivos (e as opções do Copiloto) não mudam; só a descrição enviada à IA.')
                    ->schema(collect(self::ROTULOS_OBJETIVOS)->map(
                        fn (string $rotulo, string $chave): Textarea => Textarea::make("objetivos.{$chave}")
                            ->label($rotulo)
                            ->rows(3)
                            ->required()
                            ->maxLength(600)
                    )->values()->all())
                    ->collapsed(),

                Section::make('Tons de voz')
                    ->description('Como a IA deve soar em cada tom.')
                    ->schema(collect(self::ROTULOS_TONS)->map(
                        fn (string $rotulo, string $chave): Textarea => Textarea::make("tons.{$chave}")
                            ->label($rotulo)
                            ->rows(2)
                            ->required()
                            ->maxLength(400)
                    )->values()->all())
                    ->collapsed(),

                Section::make('Avançado: parâmetros do Gemini')
                    ->description('Mexa só se souber o efeito: temperatura alta deixa as mensagens mais variadas (e menos previsíveis); poucos tokens podem cortar a mensagem no meio.')
                    ->schema([
                        TextInput::make('gemini.temperature')
                            ->label('Temperatura (0 a 1)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.1)
                            ->required(),
                        TextInput::make('gemini.max_output_tokens')
                            ->label('Limite de tokens da resposta')
                            ->numeric()
                            ->integer()
                            ->minValue(200)
                            ->maxValue(2000)
                            ->required(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvar')
                ->label('Salvar configuração')
                ->icon('heroicon-m-check')
                ->color('primary')
                ->action('salvar'),
            Action::make('previa')
                ->label('Pré-visualizar prompt')
                ->icon('heroicon-m-eye')
                ->color('gray')
                ->modalHeading('Prompt que a IA recebe (com o que está no formulário)')
                ->modalDescription('Exemplo com o objetivo "Convite para Tour Pedagógico" e o tom "Acolhedor". Os dados do lead entram à parte, na mensagem do usuário.')
                ->modalContent(fn (): HtmlString => new HtmlString(
                    '<pre style="white-space:pre-wrap;font-size:.8rem;line-height:1.5">'.e($this->montarPrevia()).'</pre>'
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
            Action::make('restaurar')
                ->label('Restaurar padrão')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Descarta todas as personalizações e volta aos valores originais do sistema (config/copiloto_ia.php).')
                ->action('restaurarPadrao'),
            $this->ajudaAction('Comportamento do Copiloto IA', HelpContent::make('🧠', 'Comportamento do Copiloto IA', 'Ajuste como o Copiloto WhatsApp IA escreve as mensagens, sem mexer em código.')
                ->passos('🚀 Passo a passo', [
                    'Edite a persona, as diretrizes e o que a IA deve mencionar ou evitar.',
                    'Se quiser, ajuste a descrição de cada objetivo e de cada tom de voz.',
                    'Use Pré-visualizar prompt para ver exatamente o texto que a IA recebe.',
                    'Clique em Salvar configuração. A mudança vale na próxima mensagem gerada.',
                ])
                ->secao('📚 Como funciona?', [
                    ['🌐', 'Regras gerais', 'Valem para todas as mensagens do Copiloto, inclusive "Personalizar com Copiloto IA" no envio rápido.'],
                    ['📄', 'Instruções por modelo', 'Cada Modelo de WhatsApp pode ter instruções próprias, somadas a estas regras quando o modelo é usado como base.'],
                    ['🔒', 'O que não muda', 'A proibição de links, o texto puro na saída e a proteção contra instruções maliciosas nos dados do lead são sempre aplicadas.'],
                    ['↩️', 'Restaurar padrão', 'Volta aos valores originais do sistema.'],
                ])
                ->alerta('Evite prometer descontos, vagas ou valores nas regras: a IA vai repetir isso para as famílias.')),
        ];
    }

    public function salvar(): void
    {
        $state = $this->getSchema('content')->getState();

        CopilotoIaConfiguracao::create([
            'valores' => $this->normalizar($state),
            'atualizado_por' => auth()->id(),
        ]);
        CopilotoIaConfiguracao::limparCache();

        Notification::make()
            ->title('Configuração salva')
            ->body('Vale a partir da próxima mensagem gerada pelo Copiloto.')
            ->success()
            ->send();
    }

    public function restaurarPadrao(): void
    {
        CopilotoIaConfiguracao::query()->delete();
        CopilotoIaConfiguracao::limparCache();

        $this->getSchema('content')->fill($this->paraFormulario(CopilotoIaConfiguracao::valores()));

        Notification::make()->title('Valores padrão restaurados')->success()->send();
    }

    private function montarPrevia(): string
    {
        $config = CopilotoIaConfiguracao::mesclar(
            config('copiloto_ia'),
            $this->normalizar($this->getSchema('content')->getRawState()),
        );

        return app(CrmIaVendasService::class)->montarSystemInstructionCopiloto(
            objetivo: 'convite_visita',
            tom: 'acolhedor',
            config: $config,
        );
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function paraFormulario(array $valores): array
    {
        $valores['diretrizes'] = array_map(fn (string $texto): array => ['texto' => $texto], array_values($valores['diretrizes']));

        return $valores;
    }

    /**
     * Converte o estado do formulário no formato salvo (igual ao de config/copiloto_ia.php).
     * Tolerante a campos ausentes, pois também alimenta a pré-visualização com o formulário ainda incompleto.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizar(array $state): array
    {
        $diretrizes = collect($state['diretrizes'] ?? [])
            ->map(fn ($item): string => trim((string) ($item['texto'] ?? '')))
            ->filter()
            ->values()
            ->all();

        $textos = fn (string $grupo): array => collect($state[$grupo] ?? [])
            ->map(fn ($texto): string => trim((string) $texto))
            ->filter()
            ->all();

        $normalizado = [
            'persona' => trim((string) ($state['persona'] ?? '')),
            'diretrizes' => $diretrizes,
            'mencionar' => trim((string) ($state['mencionar'] ?? '')),
            'evitar' => trim((string) ($state['evitar'] ?? '')),
            'objetivos' => $textos('objetivos'),
            'tons' => $textos('tons'),
            'gemini' => array_filter([
                'temperature' => isset($state['gemini']['temperature']) && $state['gemini']['temperature'] !== '' ? (float) $state['gemini']['temperature'] : null,
                'max_output_tokens' => isset($state['gemini']['max_output_tokens']) && $state['gemini']['max_output_tokens'] !== '' ? (int) $state['gemini']['max_output_tokens'] : null,
            ], fn ($valor): bool => $valor !== null),
        ];

        // Prévia com formulário incompleto: lista/persona vazias caem no padrão em vez de gerar um prompt quebrado.
        if ($normalizado['diretrizes'] === []) {
            unset($normalizado['diretrizes']);
        }
        if ($normalizado['persona'] === '') {
            unset($normalizado['persona']);
        }

        return $normalizado;
    }
}
