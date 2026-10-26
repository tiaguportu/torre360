<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Orcamentos\OrcamentoResource;
use App\Models\Orcamento;
use App\Services\OrcamentoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Orçado × Realizado (Onda 43): o previsto acumulado até o mês escolhido contra o que já aconteceu, por conta do plano, com
 * semáforo. O realizado é o do DRE (transações bancárias classificadas na conta e nas subcontas).
 */
class OrcadoRealizado extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 23;

    protected static ?string $title = 'Orçado × Realizado';

    protected static ?string $navigationLabel = 'Orçado × Realizado';

    protected static ?string $slug = 'financeiro/orcado-realizado';

    protected string $view = 'filament.pages.orcado-realizado';

    #[Url(as: 'orcamento')]
    public ?int $orcamentoId = null;

    #[Url(as: 'mes')]
    public ?int $ateMes = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:OrcadoRealizado');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->ateMes ??= (int) now()->month;
        $this->orcamentoId ??= Orcamento::vigenteDoAno(now()->year)?->id
            ?? Orcamento::query()->aprovados()->orderByDesc('ano')->orderByDesc('versao')->value('id');
    }

    /**
     * @return array<int, string>
     */
    public function opcoesDeOrcamento(): array
    {
        abort_unless(static::canAccess(), 403);

        return Orcamento::query()
            ->whereIn('status', ['aprovado', 'substituido'])
            ->orderByDesc('ano')
            ->orderByDesc('versao')
            ->get()
            ->mapWithKeys(fn (Orcamento $o): array => [$o->id => "{$o->ano} · {$o->rotulo()}".($o->estaAprovado() ? '' : ' (substituído)')])
            ->all();
    }

    public function orcamento(): ?Orcamento
    {
        return $this->orcamentoId ? Orcamento::query()->whereIn('status', ['aprovado', 'substituido'])->find($this->orcamentoId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resultado(): array
    {
        abort_unless(static::canAccess(), 403);

        $orcamento = $this->orcamento();
        $servico = app(OrcamentoService::class);

        if (! $orcamento) {
            return ['orcamento' => null, 'linhas' => [], 'totais' => [], 'sem_classificacao' => ['quantidade' => 0, 'valor' => 0.0]];
        }

        $mes = max(1, min(12, (int) $this->ateMes));
        $linhas = $servico->comparativo($orcamento, $mes);
        $totais = ['receita' => ['previsto' => 0.0, 'realizado' => 0.0], 'despesa' => ['previsto' => 0.0, 'realizado' => 0.0]];

        foreach ($linhas as $linha) {
            if (isset($totais[$linha['tipo']])) {
                $totais[$linha['tipo']]['previsto'] += $linha['previsto'];
                $totais[$linha['tipo']]['realizado'] += $linha['realizado'];
            }
        }

        return [
            'orcamento' => $orcamento,
            'mes' => $mes,
            'linhas' => $linhas,
            'totais' => $totais,
            'sem_classificacao' => $servico->semClassificacao($orcamento->ano, $mes),
            'url' => OrcamentoResource::getUrl('view', ['record' => $orcamento]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Orçado × Realizado', HelpContent::make('⚖️', 'Orçado × Realizado', 'Quanto estava previsto e quanto já aconteceu, por conta.')
                ->passos('🔁 Como usar', [
                    'Escolha o orçamento aprovado e o mês até onde acompanhar. A tabela compara o previsto acumulado de janeiro até o mês com o realizado.',
                    'Verde está dentro do previsto, amarelo está chegando perto (despesa) ou abaixo do esperado (receita), vermelho estourou.',
                    'O aviso "sem classificação" mostra o movimento do banco sem conta do plano: ele fica fora do realizado até ser classificado.',
                ])
                ->secao('📌 Como a conta é feita', [
                    ['🎯', 'Previsto', 'Soma dos meses do orçamento, de janeiro até o mês escolhido.'],
                    ['📊', 'Realizado', 'Soma das transações bancárias do ano até o mês, na conta e nas subcontas: a mesma regra do DRE.'],
                    ['🚦', 'Semáforo', 'Despesa: amarela a partir de 90% do previsto (ORCAMENTO_ALERTA_PERCENTUAL), vermelha acima de 100%. Receita: o inverso.'],
                ])),
        ];
    }
}
