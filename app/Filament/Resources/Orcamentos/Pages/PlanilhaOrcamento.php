<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Orcamentos\OrcamentoResource;
use App\Models\CentroCusto;
use App\Models\Orcamento;
use App\Models\PlanoConta;
use App\Services\OrcamentoService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Planilha do orçamento (Onda 43): uma linha por conta do plano (e centro de custo), uma coluna por mês. Só o rascunho se edita; o
 * aprovado aparece em leitura.
 */
class PlanilhaOrcamento extends Page
{
    use HasAjudaAction;
    use InteractsWithRecord;

    protected static string $resource = OrcamentoResource::class;

    protected string $view = 'filament.resources.orcamentos.planilha';

    protected static ?string $title = 'Planilha do Orçamento';

    /** @var array<string, array<int, string>> chave "plano:centro" => mês => valor digitado */
    public array $grade = [];

    public ?int $novoPlano = null;

    public ?int $novoCentro = null;

    /** @var array<string, array{plano: string, centro: ?string, tipo: string}>|null */
    private ?array $rotulosMemo = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()?->can('view', $this->orcamento()), 403);

        $this->carregar();
    }

    public function getTitle(): string
    {
        return 'Planilha: '.$this->orcamento()->rotulo();
    }

    public function orcamento(): Orcamento
    {
        /** @var Orcamento $orcamento */
        $orcamento = $this->getRecord();

        return $orcamento;
    }

    public function editavel(): bool
    {
        return $this->orcamento()->ehRascunho() && (bool) auth()->user()?->can('update', $this->orcamento());
    }

    private function servico(): OrcamentoService
    {
        return app(OrcamentoService::class);
    }

    private function carregar(): void
    {
        $this->grade = [];

        foreach ($this->servico()->grade($this->orcamento()) as $chave => $linha) {
            $this->grade[$chave] = array_map(fn (float $v): string => $v > 0 ? number_format($v, 2, ',', '') : '', $linha['meses']);
        }
    }

    /**
     * @return array<int, string>
     */
    public function opcoesDePlano(): array
    {
        return PlanoConta::query()
            ->whereIn('tipo', ['receita', 'despesa'])
            ->where('is_active', true)
            ->orderBy('codigo')
            ->get()
            ->mapWithKeys(fn (PlanoConta $p): array => [$p->id => "{$p->codigo} - {$p->nome} (".($p->tipo === 'receita' ? 'receita' : 'despesa').')'])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function opcoesDeCentro(): array
    {
        return CentroCusto::query()->where('is_active', true)->orderBy('nome')->pluck('nome', 'id')->all();
    }

    /**
     * Rótulo de cada linha da grade, por chave.
     *
     * @return array<string, array{plano: string, centro: ?string, tipo: string}>
     */
    public function rotulos(): array
    {
        // A tela pede os rótulos a cada linha e a cada total: monta uma vez por requisição enquanto as linhas não mudam.
        if ($this->rotulosMemo !== null && array_keys($this->rotulosMemo) === array_keys($this->grade)) {
            return $this->rotulosMemo;
        }

        $rotulos = [];
        $planos = PlanoConta::query()->get()->keyBy('id');
        $centros = CentroCusto::query()->get()->keyBy('id');

        foreach (array_keys($this->grade) as $chave) {
            [$planoId, $centroId] = array_map('intval', explode(':', $chave));
            $plano = $planos->get($planoId);
            $rotulos[$chave] = [
                'plano' => $plano ? "{$plano->codigo} - {$plano->nome}" : "Conta #{$planoId}",
                'centro' => $centroId ? $centros->get($centroId)?->nome : null,
                'tipo' => (string) ($plano?->tipo ?? ''),
            ];
        }

        return $this->rotulosMemo = $rotulos;
    }

    public function totalDaLinha(string $chave): float
    {
        return round(array_sum(array_map(fn ($v) => $this->lerSemFalhar((string) $v), $this->grade[$chave] ?? [])), 2);
    }

    public function totalDoMes(int $mes, ?string $tipo = null): float
    {
        $rotulos = $this->rotulos();
        $total = 0.0;

        foreach ($this->grade as $chave => $meses) {
            if ($tipo !== null && ($rotulos[$chave]['tipo'] ?? '') !== $tipo) {
                continue;
            }

            $total += $this->lerSemFalhar($meses[$mes] ?? '');
        }

        return round($total, 2);
    }

    private function lerSemFalhar(string $texto): float
    {
        try {
            return $this->servico()->lerValor($texto);
        } catch (\DomainException) {
            return 0.0;
        }
    }

    public function adicionarLinha(): void
    {
        abort_unless($this->editavel(), 403);

        if (! $this->novoPlano) {
            Notification::make()->title('Escolha a conta do plano')->warning()->send();

            return;
        }

        $chave = $this->servico()->chave($this->novoPlano, $this->novoCentro ?: null);

        if (isset($this->grade[$chave])) {
            Notification::make()->title('Esta conta já está na planilha')->warning()->send();

            return;
        }

        $this->grade[$chave] = array_fill(1, 12, '');
        $this->novoPlano = null;
        $this->novoCentro = null;
    }

    public function removerLinha(string $chave): void
    {
        abort_unless($this->editavel(), 403);

        unset($this->grade[$chave]);
    }

    public function salvar(): void
    {
        abort_unless($this->editavel(), 403);

        $servico = $this->servico();
        $atuais = $servico->grade($this->orcamento());

        try {
            foreach ($this->grade as $chave => $meses) {
                [$planoId, $centroId] = array_map('intval', explode(':', $chave));

                for ($mes = 1; $mes <= 12; $mes++) {
                    $servico->definirValor($this->orcamento(), $planoId, $centroId ?: null, $mes, $servico->lerValor((string) ($meses[$mes] ?? '')));
                }
            }

            // Linha tirada da grade: zera os meses dela.
            foreach ($atuais as $chave => $linha) {
                if (! isset($this->grade[$chave])) {
                    for ($mes = 1; $mes <= 12; $mes++) {
                        $servico->definirValor($this->orcamento(), $linha['plano']->id, $linha['centro']?->id, $mes, 0.0);
                    }
                }
            }
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível salvar')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $this->carregar();

        Notification::make()->title('Planilha salva')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voltar')
                ->label('Voltar ao orçamento')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => OrcamentoResource::getUrl('view', ['record' => $this->orcamento()])),
            $this->ajudaAction('Planilha do orçamento', HelpContent::make('🏦', 'Planilha do orçamento', 'Conta do plano × 12 meses.')
                ->passos('🔁 Como usar', [
                    'Escolha a conta (e, se quiser, o centro de custo) e use "Adicionar linha".',
                    'Digite o previsto de cada mês (vírgula ou ponto). Mês em branco ou zero fica sem previsto.',
                    'Clique em "Salvar". Quando estiver pronta, volte ao orçamento e aprove.',
                ])
                ->secao('📌 Bom saber', [
                    ['🌳', 'Conta-mãe', 'Uma linha na conta-mãe compara com o realizado de todas as subcontas. Não ponha a mesma despesa na mãe e na filha: contaria em dobro.'],
                    ['🔒', 'Aprovado', 'Depois de aprovado a planilha só se lê. Para mudar valores, abra uma revisão.'],
                ])),
        ];
    }
}
