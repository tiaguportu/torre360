<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Interessado;
use App\Models\LeadScoreConfiguracao;
use App\Services\LeadScoreService;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use UnitEnum;

class ConfiguracaoLeadScore extends Page implements HasForms
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $navigationLabel = 'Pesos do Lead Score';

    protected static ?string $title = 'Pesos do Lead Score';

    protected static ?string $slug = 'crm/pesos-lead-score';

    /** Rótulos dos 12 fatores (mesma chave de config('lead_score.pesos')). */
    private const FATORES = [
        'percepcao_consultor' => 'Percepção do consultor',
        'filhos' => 'Nº de filhos',
        'distancia' => 'Distância da escola',
        'transporte' => 'Meio de transporte',
        'profissao' => 'Profissão',
        'valor_estimado' => 'Valor estimado',
        'interacoes_sucesso' => 'Interações bem-sucedidas',
        'total_interacoes' => 'Total de interações',
        'recencia' => 'Recência do contato',
        'completude_cadastro' => 'Completude do cadastro',
        'origem' => 'Origem do lead',
        'estagio_funil' => 'Estágio no funil',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('content')->fill($this->valoresAtuais());
    }

    /**
     * @return array<string, mixed>
     */
    private function valoresAtuais(): array
    {
        $config = config('lead_score');

        return [
            'pesos' => $config['pesos'],
            'faixas_cor' => $config['faixas_cor'],
            'percepcao_consultor' => $config['percepcao_consultor'],
            'filhos' => $config['filhos'],
            'faixa_distancia_escola' => $config['faixa_distancia_escola'],
            'meio_transporte' => $config['meio_transporte'],
            'valor_estimado' => $config['valor_estimado'],
            'profissoes' => $config['profissoes'],
            'profissao_padrao' => $config['profissao_padrao'],
            'interacoes_sucesso' => $config['interacoes_sucesso'],
            'total_interacoes' => $config['total_interacoes'],
            'recencia' => $config['recencia'],
            'origem' => $config['origem'],
            'origem_padrao' => $config['origem_padrao'],
        ];
    }

    private function pontos(string $nome): TextInput
    {
        return TextInput::make($nome)->numeric()->integer()->minValue(0)->maxValue(100)->required();
    }

    /**
     * @param  array<string, string>  $opcoes
     * @return array<int, TextInput>
     */
    private function camposPorOpcao(string $grupo, array $opcoes): array
    {
        return collect($opcoes)
            ->map(fn (string $rotulo, string $chave) => $this->pontos("{$grupo}.{$chave}")->label($rotulo))
            ->values()
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        $pesosCampos = collect(self::FATORES)
            ->map(fn (string $rotulo, string $chave) => $this->pontos("pesos.{$chave}")->label($rotulo)->live(onBlur: true))
            ->values()
            ->all();

        return $schema
            ->components([
                Tabs::make('Configuração')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Pesos e Cores')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Section::make('Peso máximo de cada fator')
                                    ->description('Quanto cada fator pode somar ao score. A soma dos 12 pesos deve ser exatamente 100.')
                                    ->schema([
                                        ...$pesosCampos,
                                        Text::make(function (Get $get): string {
                                            $total = collect(array_keys(self::FATORES))->sum(fn ($k) => (int) $get("pesos.{$k}"));

                                            return "Soma atual: {$total} / 100".($total === 100 ? ' ✅' : ' — ajuste para fechar 100 ⚠️');
                                        })->columnSpanFull()->size('lg'),
                                    ])
                                    ->columns(3),
                                Section::make('Faixas de cor do score')
                                    ->description('Define quando o score é exibido como quente (verde), morno (âmbar) ou frio (vermelho).')
                                    ->schema([
                                        $this->pontos('faixas_cor.quente')->label('Quente a partir de (≥)'),
                                        $this->pontos('faixas_cor.morno')->label('Morno a partir de (≥)'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('Perfil / Fit')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Section::make('Percepção do consultor')
                                    ->description('Usa a "Temperatura" que o consultor define no formulário do lead. É o fator de maior peso por padrão.')
                                    ->schema($this->camposPorOpcao('percepcao_consultor', [
                                        'quente' => '🔥 Quente',
                                        'morno' => '🟡 Morno',
                                        'frio' => '🔵 Frio',
                                        'nao_informado' => 'Não informada',
                                    ]))
                                    ->columns(4),
                                Section::make('Nº de filhos (dependentes)')
                                    ->description('Vale a primeira faixa cujo mínimo o lead atinge (a lista é ordenada do maior para o menor ao salvar).')
                                    ->schema([
                                        Repeater::make('filhos')
                                            ->label('Faixas')
                                            ->schema([
                                                TextInput::make('minimo')->label('A partir de (filhos)')->numeric()->integer()->minValue(0)->required(),
                                                $this->pontos('pontos')->label('Pontos'),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Adicionar faixa')
                                            ->reorderable(false)
                                            ->minItems(1),
                                    ]),
                                Section::make('Distância até a escola')
                                    ->schema($this->camposPorOpcao('faixa_distancia_escola', [
                                        'ate_2km' => 'Até 2 km',
                                        'de_2_a_5km' => '2 a 5 km',
                                        'de_5_a_10km' => '5 a 10 km',
                                        'mais_de_10km' => 'Mais de 10 km',
                                    ]))
                                    ->columns(4),
                                Section::make('Meio de transporte')
                                    ->schema($this->camposPorOpcao('meio_transporte', [
                                        'carro_proprio' => 'Carro próprio',
                                        'van_escolar' => 'Van escolar',
                                        'a_pe_ou_bicicleta' => 'A pé / Bicicleta',
                                        'transporte_publico' => 'Transporte público',
                                        'nao_informado' => 'Não informado',
                                    ]))
                                    ->columns(5),
                                Section::make('Profissão')
                                    ->description('Palavra-chave (sem acento, minúscula, "contém") e pontos. Ex.: medic = 10 vale para médico, médica, medicina.')
                                    ->schema([
                                        KeyValue::make('profissoes')
                                            ->label('Palavras-chave')
                                            ->keyLabel('Palavra-chave')
                                            ->valueLabel('Pontos')
                                            ->addActionLabel('Adicionar palavra-chave')
                                            ->reorderable(false),
                                        $this->pontos('profissao_padrao')->label('Pontos quando vazia ou sem correspondência'),
                                    ]),
                                Section::make('Valor estimado de matrícula (R$)')
                                    ->description('Vale a primeira faixa cujo mínimo o valor atinge (ordenada do maior para o menor ao salvar).')
                                    ->schema([
                                        Repeater::make('valor_estimado')
                                            ->label('Faixas')
                                            ->schema([
                                                TextInput::make('minimo')->label('A partir de (R$)')->numeric()->minValue(0)->required(),
                                                $this->pontos('pontos')->label('Pontos'),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Adicionar faixa')
                                            ->reorderable(false)
                                            ->minItems(1),
                                    ]),
                            ]),

                        Tab::make('Engajamento')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Interações bem-sucedidas')
                                    ->schema([
                                        CheckboxList::make('interacoes_sucesso.resultados')
                                            ->label('Resultados que contam como sucesso')
                                            ->options([
                                                'agendou_visita' => 'Agendou Visita',
                                                'retornar' => 'Retornar depois',
                                                'sem_interesse' => 'Sem Interesse',
                                                'matriculou' => 'Efetuou Matrícula',
                                                'outro' => 'Outro',
                                            ])
                                            ->columns(3),
                                        $this->pontos('interacoes_sucesso.pontos_por_interacao')->label('Pontos por interação bem-sucedida'),
                                    ]),
                                Section::make('Total de interações')
                                    ->schema([
                                        $this->pontos('total_interacoes.pontos_por_interacao')->label('Pontos por interação registrada'),
                                    ]),
                                Section::make('Recência do último contato')
                                    ->description('Vale a primeira faixa cujo limite de dias não foi ultrapassado. Deixe "Até (dias)" vazio na última faixa (qualquer prazo maior). Ordenada automaticamente ao salvar.')
                                    ->schema([
                                        Repeater::make('recencia')
                                            ->label('Faixas')
                                            ->schema([
                                                TextInput::make('maximo_dias')->label('Até (dias sem contato)')->numeric()->integer()->minValue(0),
                                                $this->pontos('pontos')->label('Pontos'),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Adicionar faixa')
                                            ->reorderable(false)
                                            ->minItems(1),
                                    ]),
                            ]),

                        Tab::make('Origem')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Section::make('Pontos por origem do lead')
                                    ->description('Use o nome da origem como cadastrado (a comparação ignora maiúsculas/minúsculas).')
                                    ->schema([
                                        KeyValue::make('origem')
                                            ->label('Origens')
                                            ->keyLabel('Nome da origem')
                                            ->valueLabel('Pontos')
                                            ->addActionLabel('Adicionar origem')
                                            ->reorderable(false),
                                        $this->pontos('origem_padrao')->label('Pontos para as demais origens'),
                                    ]),
                            ]),
                    ]),
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
            Action::make('restaurar')
                ->label('Restaurar padrão')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Descarta todas as personalizações e volta aos valores originais do sistema (config/lead_score.php).')
                ->action('restaurarPadrao'),
            Action::make('recalcular')
                ->label('Recalcular todos os leads')
                ->icon('heroicon-m-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Recalcula o Lead Score de todos os interessados com a configuração salva. Pode levar alguns instantes.')
                ->action('recalcularLeads'),
            $this->ajudaAction('Pesos do Lead Score', HelpContent::make('🎚️', 'Pesos do Lead Score', 'Ajuste como o sistema pontua cada lead, sem mexer em código.')
                ->passos('🚀 Passo a passo', [
                    'Em Pesos e Cores, defina o peso máximo de cada fator (a soma precisa ser 100).',
                    'Nas demais abas, ajuste os pontos de cada faixa ou opção.',
                    'Clique em Salvar configuração.',
                    'Use Recalcular todos os leads para aplicar os novos pesos aos leads já cadastrados.',
                ])
                ->secao('📊 Como funciona?', [
                    ['⚖️', 'Pesos', 'Cada fator soma até o seu peso máximo; os 12 pesos totalizam 100.'],
                    ['🎯', 'Faixas e opções', 'Os pontos de cada faixa não podem ultrapassar o peso do fator.'],
                    ['🎨', 'Cores', 'Definem a partir de qual nota o score aparece como quente, morno ou frio.'],
                    ['↩️', 'Restaurar padrão', 'Volta aos valores originais do sistema.'],
                ])
                ->alerta('Salvar não altera os scores já gravados: use "Recalcular todos os leads". Os scores também se atualizam quando cada lead é salvo.')),
        ];
    }

    public function salvar(): void
    {
        $state = $this->getSchema('content')->getState();

        $erros = $this->validar($state);

        if ($erros !== []) {
            Notification::make()
                ->title('Configuração inválida')
                ->body(implode("\n", $erros))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        LeadScoreConfiguracao::create([
            'valores' => $this->normalizar($state),
            'atualizado_por' => auth()->id(),
        ]);
        LeadScoreConfiguracao::limparCache();
        LeadScoreConfiguracao::aplicar();

        Notification::make()
            ->title('Configuração salva')
            ->body('Use "Recalcular todos os leads" para aplicar aos leads existentes.')
            ->success()
            ->send();
    }

    public function restaurarPadrao(): void
    {
        LeadScoreConfiguracao::query()->delete();
        LeadScoreConfiguracao::limparCache();

        $padrao = require config_path('lead_score.php');
        config(['lead_score' => $padrao]);

        $this->getSchema('content')->fill($this->valoresAtuais());

        Notification::make()->title('Valores padrão restaurados')->success()->send();
    }

    public function recalcularLeads(): void
    {
        $total = 0;

        Interessado::query()->chunkById(200, function ($leads) use (&$total): void {
            $total += LeadScoreService::recalcularLote($leads);
        });

        Notification::make()->title("{$total} lead(s) recalculado(s)")->success()->send();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, string>
     */
    private function validar(array $state): array
    {
        $erros = [];
        $pesos = $state['pesos'];

        $soma = array_sum(array_map('intval', $pesos));
        if ($soma !== 100) {
            $erros[] = "A soma dos pesos deve ser 100 (atual: {$soma}).";
        }

        if ((int) $state['faixas_cor']['morno'] > (int) $state['faixas_cor']['quente']) {
            $erros[] = 'O corte "Morno" não pode ser maior que o corte "Quente".';
        }

        $maximos = [
            'percepcao_consultor' => collect($state['percepcao_consultor'])->max(),
            'filhos' => collect($state['filhos'])->max('pontos'),
            'distancia' => collect($state['faixa_distancia_escola'])->max(),
            'transporte' => collect($state['meio_transporte'])->max(),
            'profissao' => max(collect($state['profissoes'])->map(fn ($p) => (int) $p)->max() ?? 0, (int) $state['profissao_padrao']),
            'valor_estimado' => collect($state['valor_estimado'])->max('pontos'),
            'recencia' => collect($state['recencia'])->max('pontos'),
            'origem' => max(collect($state['origem'])->map(fn ($p) => (int) $p)->max() ?? 0, (int) $state['origem_padrao']),
        ];

        foreach ($maximos as $fator => $maximo) {
            if ((int) $maximo > (int) $pesos[$fator]) {
                $erros[] = 'Os pontos de "'.self::FATORES[$fator]."\" ({$maximo}) não podem passar do peso do fator ({$pesos[$fator]}).";
            }
        }

        return $erros;
    }

    /**
     * Converte o estado do formulário no formato esperado pelo LeadScoreService.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizar(array $state): array
    {
        $inteiros = fn (array $valores): array => array_map('intval', $valores);

        $porMinimoDesc = fn (array $faixas): array => collect($faixas)
            ->map(fn ($f) => ['minimo' => $f['minimo'] + 0, 'pontos' => (int) $f['pontos']])
            ->sortByDesc('minimo')
            ->values()
            ->all();

        $recencia = collect($state['recencia'])
            ->map(fn ($f) => [
                'maximo_dias' => filled($f['maximo_dias'] ?? null) ? (int) $f['maximo_dias'] : null,
                'pontos' => (int) $f['pontos'],
            ])
            ->sortBy(fn ($f) => $f['maximo_dias'] ?? PHP_INT_MAX)
            ->values()
            ->all();

        $chaveados = fn (array $mapa, bool $minusculas): array => collect($mapa)
            ->mapWithKeys(fn ($pontos, $chave) => [
                ($minusculas ? Str::lower(trim((string) $chave)) : trim((string) $chave)) => (int) $pontos,
            ])
            ->filter(fn ($p, $chave) => $chave !== '')
            ->all();

        return [
            'pesos' => $inteiros($state['pesos']),
            'faixas_cor' => $inteiros($state['faixas_cor']),
            'percepcao_consultor' => $inteiros($state['percepcao_consultor']),
            'filhos' => $porMinimoDesc($state['filhos']),
            'faixa_distancia_escola' => $inteiros($state['faixa_distancia_escola']),
            'meio_transporte' => $inteiros($state['meio_transporte']),
            'valor_estimado' => $porMinimoDesc($state['valor_estimado']),
            'profissoes' => $chaveados($state['profissoes'], true),
            'profissao_padrao' => (int) $state['profissao_padrao'],
            'interacoes_sucesso' => [
                'resultados' => array_values($state['interacoes_sucesso']['resultados'] ?? []),
                'pontos_por_interacao' => (int) $state['interacoes_sucesso']['pontos_por_interacao'],
            ],
            'total_interacoes' => [
                'pontos_por_interacao' => (int) $state['total_interacoes']['pontos_por_interacao'],
            ],
            'recencia' => $recencia,
            'origem' => $chaveados($state['origem'], true),
            'origem_padrao' => (int) $state['origem_padrao'],
        ];
    }
}
