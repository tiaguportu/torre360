<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Services\FluxoProjetadoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Fluxo de caixa projetado (Onda 78): o caixa dos próximos dias a partir do saldo bancário, das faturas abertas, das contas a pagar e
 * das despesas recorrentes que ainda não viraram conta. É estimativa: a tela diz o cenário, a taxa de recebimento usada e a situação
 * da conciliação bancária ao lado do número.
 */
class FluxoProjetado extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 25;

    protected static ?string $title = 'Fluxo de Caixa Projetado';

    protected static ?string $navigationLabel = 'Fluxo Projetado';

    protected static ?string $slug = 'financeiro/fluxo-projetado';

    protected string $view = 'filament.pages.fluxo-projetado';

    #[Url(as: 'cenario')]
    public string $cenario = 'esperado';

    #[Url(as: 'dias')]
    public int $dias = 90;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:FluxoProjetado');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->dias = in_array($this->dias, [30, 60, 90, 180], true) ? $this->dias : 90;
        $this->cenario = array_key_exists($this->cenario, FluxoProjetadoService::CENARIOS) ? $this->cenario : 'esperado';
    }

    /**
     * @return array<string, string>
     */
    public function cenarios(): array
    {
        return FluxoProjetadoService::CENARIOS;
    }

    /**
     * @return array<string, mixed>
     */
    public function projecao(): array
    {
        abort_unless(static::canAccess(), 403);

        return $this->memo ??= app(FluxoProjetadoService::class)->projetar(null, $this->dias, $this->cenario);
    }

    public function updated(string $propriedade): void
    {
        $this->memo = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Fluxo projetado', HelpContent::make('🔭', 'Fluxo de caixa projetado', 'O caixa dos próximos dias, antes de ele acontecer.')
                ->passos('🔁 Como ler', [
                    'O ponto de partida é o saldo das transações bancárias lançadas. Se o banco não está conciliado, o saldo está errado e a projeção também.',
                    'Entram como recebimento as faturas abertas; como pagamento, as contas a pagar abertas e as despesas recorrentes que ainda não viraram conta.',
                    'Escolha o cenário: a média histórica pesa o que vence pelo que costuma ser pago; o otimista recebe tudo; o conservador não recebe nenhuma fatura aberta.',
                    'O aviso vermelho mostra a primeira data em que o saldo fica negativo. Use o tempo até lá para antecipar recebimentos ou renegociar pagamentos.',
                ])
                ->secao('📌 Bom saber', [
                    ['📐', 'É estimativa', 'A taxa de recebimento vem dos últimos meses; sem histórico, vale a taxa padrão da escola.'],
                    ['⏳', 'Faturas atrasadas', 'Entram alguns dias depois de hoje e com metade da taxa no cenário da média histórica.'],
                    ['🏦', 'Conciliação', 'A data do último lançamento e as transações sem conciliar aparecem na tela.'],
                ])),
        ];
    }
}
