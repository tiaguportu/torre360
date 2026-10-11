<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoMatricula;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\Matricula;
use App\Models\RiscoEvasaoConfiguracao;
use App\Services\RiscoEvasaoService;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ConfiguracaoRiscoEvasao extends Page implements HasForms
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Pesos do Risco de Evasão';

    protected static ?string $title = 'Pesos do Risco de Evasão';

    protected static ?string $slug = 'secretaria/pesos-risco-evasao';

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
        $config = config('risco_evasao');

        return [
            'pesos' => $config['pesos'],
            'faixas_cor' => $config['faixas_cor'],
            'frequencia' => $config['frequencia'],
            'desempenho' => $config['desempenho'],
            'playbook' => $config['playbook'] ?? [
                'corte_risco' => 60,
                'intervalo_dias_alerta' => 15,
                'notificar_coordenacao' => true,
                'criar_tarefa' => true,
            ],
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Peso máximo de cada fator')
                    ->description('Quanto cada fator pode somar ao score de risco. A soma dos 3 pesos deve ser exatamente 100.')
                    ->schema([
                        TextInput::make('pesos.frequencia')->label('Frequência')->numeric()->integer()->minValue(0)->maxValue(100)->required()->live(onBlur: true),
                        TextInput::make('pesos.desempenho')->label('Desempenho')->numeric()->integer()->minValue(0)->maxValue(100)->required()->live(onBlur: true),
                        TextInput::make('pesos.inadimplencia')->label('Inadimplência')->numeric()->integer()->minValue(0)->maxValue(100)->required()->live(onBlur: true),
                        Text::make(function (Get $get): string {
                            $total = (int) $get('pesos.frequencia') + (int) $get('pesos.desempenho') + (int) $get('pesos.inadimplencia');

                            return "Soma atual: {$total} / 100".($total === 100 ? ' ✅' : ' — ajuste para fechar 100 ⚠️');
                        })->columnSpanFull()->size('lg'),
                    ])
                    ->columns(3),

                Section::make('Faixas de cor do score')
                    ->description('Define quando o score é exibido como alto (vermelho), moderado (âmbar) ou baixo (verde) risco.')
                    ->schema([
                        TextInput::make('faixas_cor.alto')->label('Alto risco a partir de (≥)')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('faixas_cor.moderado')->label('Moderado a partir de (≥)')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                    ])
                    ->columns(2),

                Section::make('Playbook de Retenção Escolar & Prevenção de Evasão')
                    ->description('Gera alerta à coordenação, tarefa de acompanhamento e ativa o gatilho da régua ligado ao fluxo de rematrícula.')
                    ->schema([
                        TextInput::make('playbook.corte_risco')
                            ->label('Corte de risco para ativação (≥)')
                            ->helperText('Score mínimo (0-100) para disparar o playbook de retenção.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('playbook.intervalo_dias_alerta')
                            ->label('Intervalo sem repetição (dias)')
                            ->helperText('Janela de dias para não repetir alerta nem duplicar tarefas para o mesmo aluno.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(180)
                            ->required(),
                        Toggle::make('playbook.notificar_coordenacao')
                            ->label('Notificar coordenação e gestão')
                            ->default(true),
                        Toggle::make('playbook.criar_tarefa')
                            ->label('Criar tarefa de acompanhamento de retenção')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Frequência: faixas de faltas recentes (últimos 30 dias)')
                    ->description('Percentual mínimo de faltas (0-100) para cada faixa de pontos. Vale a primeira faixa cujo mínimo o aluno atinge — ordene da maior para a menor.')
                    ->schema([
                        Repeater::make('frequencia')
                            ->label('Faixas')
                            ->schema([
                                TextInput::make('minimo')->label('A partir de (% de faltas)')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                                TextInput::make('pontos')->label('Pontos')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Adicionar faixa')
                            ->reorderable(false)
                            ->minItems(1),
                    ]),

                Section::make('Desempenho: situação final no período mais recente')
                    ->description('Pontos conforme a pior situação encontrada entre as disciplinas do último período letivo fechado.')
                    ->schema([
                        TextInput::make('desempenho.reprovado')->label('Reprovado em alguma disciplina')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('desempenho.recuperacao')->label('Em recuperação (sem reprovação)')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('desempenho.aprovado')->label('Aprovado em tudo')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                    ])
                    ->columns(3),
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
                ->modalDescription('Descarta todas as personalizações e volta aos valores originais do sistema (config/risco_evasao.php).')
                ->action('restaurarPadrao'),
            Action::make('recalcular')
                ->label('Recalcular todas as matrículas')
                ->icon('heroicon-m-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Recalcula o Risco de Evasão de todas as matrículas ativas com a configuração salva. Pode levar alguns instantes.')
                ->action('recalcularMatriculas'),
            $this->ajudaAction('Pesos do Risco de Evasão & Playbook de Retenção', HelpContent::make('⚠️', 'Pesos do Risco de Evasão & Playbook de Retenção', 'Ajuste a pontuação de risco e automações preventivas de evasão escolar para alunos matriculados.')
                ->passos('🚀 Passo a passo', [
                    'Defina o peso máximo de cada fator (a soma precisa ser 100).',
                    'Configure o limiar de corte do Playbook de Retenção e a janela de dias sem repetição de alerta.',
                    'Ajuste as faixas de faltas e os pontos de cada situação de desempenho.',
                    'Clique em Salvar configuração.',
                    'Use Recalcular todas as matrículas para aplicar os novos pesos e avaliar o playbook para os alunos ativos.',
                ])
                ->secao('📊 Como funciona?', [
                    ['📅', 'Frequência', 'Percentual de faltas nos últimos 30 dias.'],
                    ['📚', 'Desempenho', 'Pior situação final entre as disciplinas do último período letivo fechado.'],
                    ['💳', 'Inadimplência', 'Se a matrícula tem fatura vencida em atraso, soma o peso cheio do fator.'],
                    ['🛡️', 'Playbook de Retenção', 'Gera alertas à coordenação, tarefa de acompanhamento e aciona o gatilho da régua no fluxo de rematrícula quando o score atinge o corte.'],
                    ['↩️', 'Restaurar padrão', 'Volta aos valores originais do sistema.'],
                ])
                ->alerta('Salvar não altera os scores já gravados: use "Recalcular todas as matrículas". O score também é recalculado automaticamente todos os dias.')),
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

        RiscoEvasaoConfiguracao::create([
            'valores' => $this->normalizar($state),
            'atualizado_por' => auth()->id(),
        ]);
        RiscoEvasaoConfiguracao::limparCache();
        RiscoEvasaoConfiguracao::aplicar();

        Notification::make()
            ->title('Configuração salva')
            ->body('Use "Recalcular todas as matrículas" para aplicar aos registros existentes.')
            ->success()
            ->send();
    }

    public function restaurarPadrao(): void
    {
        RiscoEvasaoConfiguracao::query()->delete();
        RiscoEvasaoConfiguracao::limparCache();

        $padrao = require config_path('risco_evasao.php');
        config(['risco_evasao' => $padrao]);

        $this->getSchema('content')->fill($this->valoresAtuais());

        Notification::make()->title('Valores padrão restaurados')->success()->send();
    }

    public function recalcularMatriculas(): void
    {
        $total = 0;

        RiscoEvasaoService::iniciarExecucao();
        try {
            Matricula::query()->where('situacao', SituacaoMatricula::ATIVA)->chunkById(200, function ($matriculas) use (&$total): void {
                foreach ($matriculas as $matricula) {
                    RiscoEvasaoService::recalcular($matricula);
                    $total++;
                }
            });
        } finally {
            RiscoEvasaoService::finalizarExecucao();
        }

        Notification::make()->title("{$total} matrícula(s) recalculada(s)")->success()->send();
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

        if ((int) $state['faixas_cor']['moderado'] > (int) $state['faixas_cor']['alto']) {
            $erros[] = 'O corte "Moderado" não pode ser maior que o corte "Alto risco".';
        }

        $maximoFrequencia = collect($state['frequencia'])->max('pontos');
        if ((int) $maximoFrequencia > (int) $pesos['frequencia']) {
            $erros[] = "Os pontos de \"Frequência\" ({$maximoFrequencia}) não podem passar do peso do fator ({$pesos['frequencia']}).";
        }

        $maximoDesempenho = max(array_map('intval', $state['desempenho']));
        if ($maximoDesempenho > (int) $pesos['desempenho']) {
            $erros[] = "Os pontos de \"Desempenho\" ({$maximoDesempenho}) não podem passar do peso do fator ({$pesos['desempenho']}).";
        }

        return $erros;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizar(array $state): array
    {
        $inteiros = fn (array $valores): array => array_map('intval', $valores);

        $frequencia = collect($state['frequencia'])
            ->map(fn ($f) => ['minimo' => (int) $f['minimo'], 'pontos' => (int) $f['pontos']])
            ->sortByDesc('minimo')
            ->values()
            ->all();

        return [
            'pesos' => $inteiros($state['pesos']),
            'faixas_cor' => $inteiros($state['faixas_cor']),
            'frequencia' => $frequencia,
            'desempenho' => $inteiros($state['desempenho']),
            'playbook' => [
                'corte_risco' => (int) ($state['playbook']['corte_risco'] ?? 60),
                'intervalo_dias_alerta' => (int) ($state['playbook']['intervalo_dias_alerta'] ?? 15),
                'notificar_coordenacao' => (bool) ($state['playbook']['notificar_coordenacao'] ?? true),
                'criar_tarefa' => (bool) ($state['playbook']['criar_tarefa'] ?? true),
            ],
        ];
    }
}
