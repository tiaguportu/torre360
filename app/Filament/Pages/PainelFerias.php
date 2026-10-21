<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\SituacaoConcessaoFerias;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Funcionarios\FuncionarioResource;
use App\Models\Funcionario;
use App\Services\FeriasVencimentoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Painel de férias dos empregados CLT: o que está com o prazo de concessão acabando, o que já venceu (risco de
 * pagamento em dobro) e os períodos que deveriam existir e não foram cadastrados.
 */
class PainelFerias extends Page
{
    use HasAjudaAction;

    public const CACHE_SELO = 'rh.painel_ferias.selo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Painel de Férias';

    protected static ?string $navigationLabel = 'Painel de Férias';

    protected static ?string $slug = 'rh/painel-ferias';

    protected string $view = 'filament.pages.painel-ferias';

    /** Filtro da tela: "atencao" (padrão), "todas" ou o valor de uma `SituacaoConcessaoFerias`. */
    public string $filtro = 'atencao';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('VerPainelFerias:Funcionario');
    }

    /**
     * Quantidade de períodos vencidos ou com prazo acabando, guardada por 10 minutos para o menu lateral não
     * consultar todos os empregados a cada página.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $total = Cache::remember(self::CACHE_SELO, 600, function (): int {
            $resumo = app(FeriasVencimentoService::class)->resumo();

            return $resumo[SituacaoConcessaoFerias::Vencida->value] + $resumo[SituacaoConcessaoFerias::AVencer->value];
        });

        return $total > 0 ? (string) $total : null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getLinhas(): Collection
    {
        abort_unless(static::canAccess(), 403);

        $linhas = app(FeriasVencimentoService::class)->painel();

        return match ($this->filtro) {
            'todas' => $linhas,
            'atencao' => $linhas->filter(fn (array $l): bool => $l['situacao']->exigeAtencao()),
            default => $linhas->filter(fn (array $l): bool => $l['situacao']->value === $this->filtro),
        };
    }

    /**
     * @return array<string, int>
     */
    public function getResumo(): array
    {
        return app(FeriasVencimentoService::class)->resumo();
    }

    public function urlDoFuncionario(Funcionario $funcionario): ?string
    {
        return FuncionarioResource::canEdit($funcionario)
            ? FuncionarioResource::getUrl('edit', ['record' => $funcionario])
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Painel de Férias', HelpContent::make('🌴', 'Painel de Férias', 'Quais férias precisam ser concedidas antes de virarem pagamento em dobro.')
                ->secao('📖 Como ler', [
                    ['⏳', 'Prazo acabando', 'As férias precisam ser concedidas até 12 meses depois do fim do período aquisitivo. O painel avisa quando faltam 90 dias ou menos.'],
                    ['🚨', 'Vencida', 'O prazo passou e ainda há dias a gozar: a CLT manda pagar esses dias em dobro (art. 137). Programe as férias o quanto antes e consulte a contabilidade.'],
                    ['📝', 'Período sem registro', 'Pela data de admissão deveria existir um período aquisitivo, mas ele não foi cadastrado. Cadastre em Funcionários → aba Férias para o controle funcionar.'],
                ])
                ->alerta('O painel considera só empregados CLT ativos e usa os dias que o RH informou na aba Férias. Fracionamento e redução por faltas não são calculados.')),
        ];
    }
}
