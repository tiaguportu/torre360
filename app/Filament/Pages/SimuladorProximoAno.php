<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\CenarioProximoAno;
use App\Models\PeriodoRematricula;
use App\Models\Serie;
use App\Services\SimuladorProximoAnoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Simulador do próximo ano letivo (Onda 76): quantos alunos cada série deve ter, quantas turmas pede e se cada turma chega ao ponto de
 * equilíbrio, com a rematrícula, a lista de espera e o risco de evasão de hoje. Mexa nas suposições (taxas, risco, reajuste, turmas) e
 * guarde cenários para a reunião. É projeção, não compromisso.
 */
class SimuladorProximoAno extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static UnitEnum|string|null $navigationGroup = 'Relatórios';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Simulador do Próximo Ano Letivo';

    protected static ?string $navigationLabel = 'Simulador do Próximo Ano';

    protected static ?string $slug = 'simulador-proximo-ano';

    protected string $view = 'filament.pages.simulador-proximo-ano';

    #[Url(as: 'campanha')]
    public ?int $periodoId = null;

    public float $taxaAndamento = 90.0;

    public float $taxaNaoIniciadas = 60.0;

    public int $corteRisco = 60;

    public bool $incluirEspera = true;

    public float $reajuste = 0.0;

    /** @var array<int|string, mixed> */
    public array $turmas = [];

    /** @var array<int|string, mixed> */
    public array $capacidade = [];

    public ?int $novaSerie = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:SimuladorProximoAno');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->periodoId ??= app(SimuladorProximoAnoService::class)->periodoPadrao()?->id;
        $this->restaurar();
    }

    private function servico(): SimuladorProximoAnoService
    {
        return app(SimuladorProximoAnoService::class);
    }

    public function periodo(): ?PeriodoRematricula
    {
        abort_unless(static::canAccess(), 403);

        return $this->periodoId ? PeriodoRematricula::query()->find($this->periodoId) : null;
    }

    /**
     * @return array<int, string>
     */
    public function campanhas(): array
    {
        abort_unless(static::canAccess(), 403);

        return PeriodoRematricula::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function parametros(): array
    {
        return [
            'taxa_andamento' => $this->taxaAndamento,
            'taxa_nao_iniciadas' => $this->taxaNaoIniciadas,
            'corte_risco' => $this->corteRisco,
            'incluir_espera' => $this->incluirEspera,
            'reajuste_percentual' => $this->reajuste,
            'turmas' => $this->turmas,
            'capacidade' => $this->capacidade,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function projecao(): ?array
    {
        abort_unless(static::canAccess(), 403);

        $periodo = $this->periodo();

        if (! $periodo) {
            return null;
        }

        return $this->memo ??= $this->servico()->projetar($periodo, $this->parametros());
    }

    /**
     * @return array{periodo: ?string, taxa_andamento: ?float, taxa_renovacao: ?float}|null
     */
    public function sugestoes(): ?array
    {
        $periodo = $this->periodo();

        return $periodo ? $this->servico()->sugestoes($periodo) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cenarios(): array
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->periodoId) {
            return [];
        }

        return $this->servico()->comparar(CenarioProximoAno::query()->where('periodo_rematricula_id', $this->periodoId)->orderByDesc('id')->get());
    }

    /**
     * Séries que ainda não têm linha na tabela e podem ganhar uma turma no "e se".
     *
     * @return array<int, string>
     */
    public function seriesParaAbrir(): array
    {
        abort_unless(static::canAccess(), 403);

        $presentes = collect($this->projecao()['linhas'] ?? [])->pluck('serie_id')->all();

        return Serie::query()->with('curso')->orderBy('nome')->get()
            ->reject(fn (Serie $s): bool => in_array($s->id, $presentes, true))
            ->mapWithKeys(fn (Serie $s): array => [$s->id => trim(($s->curso?->nome ? $s->curso->nome.' — ' : '').$s->nome)])
            ->all();
    }

    public function updated(string $propriedade): void
    {
        $this->memo = null;
    }

    public function restaurar(): void
    {
        abort_unless(static::canAccess(), 403);

        $padrao = $this->servico()->parametrosPadrao();
        $this->aplicar($padrao);
        $this->turmas = [];
        $this->capacidade = [];
        $this->memo = null;
    }

    public function usarSugestao(): void
    {
        abort_unless(static::canAccess(), 403);

        $sugestao = $this->sugestoes();

        if ($sugestao && $sugestao['taxa_andamento'] !== null) {
            $this->taxaAndamento = $sugestao['taxa_andamento'];
        }

        if ($sugestao && $sugestao['taxa_renovacao'] !== null) {
            $this->taxaNaoIniciadas = $sugestao['taxa_renovacao'];
        }

        $this->memo = null;
    }

    public function abrirTurma(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->novaSerie) {
            $this->turmas[$this->novaSerie] = 1;
            $this->novaSerie = null;
            $this->memo = null;
        }
    }

    public function carregarCenario(int $id): void
    {
        abort_unless(static::canAccess(), 403);

        $cenario = CenarioProximoAno::query()->where('periodo_rematricula_id', $this->periodoId)->findOrFail($id);
        $this->aplicar((array) $cenario->parametros);
        $this->turmas = (array) ($cenario->parametros['turmas'] ?? []);
        $this->capacidade = (array) ($cenario->parametros['capacidade'] ?? []);
        $this->memo = null;

        Notification::make()->title("Cenário \"{$cenario->nome}\" carregado")->body('Os parâmetros voltaram para a tela; o resultado é recalculado com os dados de hoje.')->success()->send();
    }

    public function excluirCenario(int $id): void
    {
        abort_unless(static::canAccess(), 403);

        $cenario = CenarioProximoAno::query()->findOrFail($id);
        $usuario = auth()->user();

        abort_unless($cenario->criado_por_user_id === $usuario?->id || $usuario?->can('Gerenciar:SimuladorProximoAno'), 403);

        $cenario->delete();

        Notification::make()->title('Cenário excluído')->success()->send();
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function aplicar(array $parametros): void
    {
        $p = $this->servico()->normalizar($parametros);
        $this->taxaAndamento = $p['taxa_andamento'];
        $this->taxaNaoIniciadas = $p['taxa_nao_iniciadas'];
        $this->corteRisco = $p['corte_risco'];
        $this->incluirEspera = $p['incluir_espera'];
        $this->reajuste = $p['reajuste_percentual'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvar')
                ->label('Salvar cenário')
                ->icon('heroicon-o-bookmark')
                ->color('primary')
                ->visible(fn (): bool => $this->periodoId !== null)
                ->modalHeading('Salvar este cenário')
                ->modalDescription('Guarda os parâmetros e a fotografia do resultado de hoje, para comparar com outros cenários na reunião.')
                ->schema([
                    TextInput::make('nome')->label('Nome do cenário')->placeholder('Ex.: Conservador')->required()->maxLength(120),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);

                    try {
                        $this->servico()->salvar($this->periodo(), (string) $data['nome'], $this->parametros(), auth()->user());
                    } catch (\DomainException $e) {
                        Notification::make()->title('Não foi possível salvar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Cenário salvo')->success()->send();
                }),
            $this->ajudaAction('Simulador do próximo ano', HelpContent::make('🧮', 'Simulador do próximo ano letivo', 'Quantos alunos, quantas turmas e se cada turma se paga, antes de decidir.')
                ->passos('🔁 Como usar', [
                    'Escolha a campanha de rematrícula. A tela mostra o que está confirmado, o que está em andamento, a lista de espera e quem ainda nem começou.',
                    'Ajuste as suposições: a taxa de conclusão das rematrículas em andamento, a taxa de renovação de quem ainda não começou, o corte de risco de evasão e o reajuste.',
                    'Em cada série, mude o número de turmas ou a capacidade para ver vagas livres, excesso e se a turma chega ao ponto de equilíbrio.',
                    'Guarde cenários (conservador, esperado, otimista) e compare lado a lado na reunião.',
                ])
                ->secao('📌 Bom saber', [
                    ['🔮', 'É projeção', 'O resultado é uma estimativa com os dados de hoje, não um compromisso. O cenário salvo guarda a fotografia do dia.'],
                    ['🏫', 'Série de destino', 'A projeção é por série de destino, a que a família escolheu. Quem ainda não escolheu entra à parte, só no total.'],
                    ['⚖️', 'Ponto de equilíbrio', 'Usa o custo planejado da turma (Controladoria de Turmas) e o preço da série. Sem custo cadastrado, não há equilíbrio a comparar.'],
                    ['📅', 'Prazo', 'O valor da anuidade e o reajuste precisam ser divulgados com antecedência (Lei nº 9.870/1999): a decisão de abrir ou fechar turma tem data.'],
                ])),
        ];
    }
}
