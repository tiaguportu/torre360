<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Funcionario;
use App\Models\PontoRegistro;
use App\Models\User;
use App\Services\Ponto\PontoApuracaoService;
use App\Support\FuncionarioDoUsuario;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Espelho de ponto do mês: marcações, jornada prevista, saldo do dia e alertas. O trabalhador vê o próprio; quem tem
 * `VerEspelhoDeTodos` escolhe qualquer funcionário e cada consulta a dados de outra pessoa fica no log de atividades.
 */
class EspelhoDePonto extends Page implements HasForms
{
    use HasAjudaAction;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'Espelho de Ponto';

    protected static ?string $navigationLabel = 'Espelho de Ponto';

    protected static ?string $slug = 'rh/espelho-de-ponto';

    protected string $view = 'filament.pages.espelho-de-ponto';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filtros = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (FuncionarioDoUsuario::para($user) !== null || Gate::allows('verEspelhoDeTodos', PontoRegistro::class));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'funcionario_id' => FuncionarioDoUsuario::para(auth()->user())?->id,
            'mes' => today()->month,
            'ano' => today()->year,
        ]);

        $this->registrarConsulta();
    }

    public function podeEscolherFuncionario(): bool
    {
        return Gate::allows('verEspelhoDeTodos', PontoRegistro::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filtros')
            ->components([
                Grid::make(3)->schema([
                    Select::make('funcionario_id')
                        ->label('Funcionário')
                        ->searchable()
                        ->live()
                        ->visible(fn (): bool => $this->podeEscolherFuncionario())
                        ->getSearchResultsUsing(fn (string $search): array => Funcionario::query()
                            ->with('pessoa')
                            ->whereHas('pessoa', fn ($q) => $q->where('nome', 'like', '%'.addcslashes($search, '%_\\').'%'))
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn (Funcionario $f): array => [$f->id => ($f->pessoa?->nome ?? 'Funcionário').' — '.$f->cargo])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($f = Funcionario::query()->with('pessoa')->find($value)) ? ($f->pessoa?->nome ?? 'Funcionário').' — '.$f->cargo : null),
                    Select::make('mes')
                        ->label('Mês')
                        ->options(collect(range(1, 12))->mapWithKeys(fn (int $m): array => [$m => mb_convert_case(now()->startOfYear()->addMonths($m - 1)->translatedFormat('F'), MB_CASE_TITLE)])->all())
                        ->live()
                        ->required(),
                    Select::make('ano')
                        ->label('Ano')
                        ->options(collect(range(today()->year, today()->year - 5))->mapWithKeys(fn (int $a): array => [$a => (string) $a])->all())
                        ->live()
                        ->required(),
                ]),
            ]);
    }

    public function getFuncionarioSelecionado(): ?Funcionario
    {
        /** @var User|null $user */
        $user = auth()->user();
        $id = $this->filtros['funcionario_id'] ?? null;
        $proprio = FuncionarioDoUsuario::para($user);

        // Quem não vê o de todos só enxerga o próprio, qualquer que seja o valor enviado pelo navegador.
        if (! $this->podeEscolherFuncionario()) {
            return $proprio;
        }

        return $id ? Funcionario::query()->with(['pessoa', 'unidade'])->find($id) : $proprio;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEspelho(): ?array
    {
        $funcionario = $this->getFuncionarioSelecionado();

        if (! $funcionario) {
            return null;
        }

        abort_unless(Gate::allows('verEspelho', [PontoRegistro::class, $funcionario]), 403);

        return app(PontoApuracaoService::class)->espelho(
            $funcionario,
            (int) ($this->filtros['ano'] ?? today()->year),
            (int) ($this->filtros['mes'] ?? today()->month),
        );
    }

    public function updatedFiltros(): void
    {
        $this->registrarConsulta();
    }

    /**
     * Consultar o ponto de outra pessoa deixa rastro (quem, de quem e qual competência; nunca o conteúdo).
     */
    private function registrarConsulta(): void
    {
        $funcionario = $this->getFuncionarioSelecionado();

        if (! $funcionario || FuncionarioDoUsuario::ehOProprio(auth()->user(), $funcionario)) {
            return;
        }

        activity('ponto')
            ->causedBy(auth()->user())
            ->withProperties([
                'funcionario_id' => $funcionario->id,
                'documento' => 'espelho',
                'competencia' => sprintf('%04d-%02d', (int) ($this->filtros['ano'] ?? today()->year), (int) ($this->filtros['mes'] ?? today()->month)),
            ])
            ->log('Consulta ao espelho de ponto de outro funcionário.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->getFuncionarioSelecionado() !== null)
                ->url(fn (): ?string => ($f = $this->getFuncionarioSelecionado())
                    ? route('ponto.espelho', [$f, (int) ($this->filtros['ano'] ?? today()->year), (int) ($this->filtros['mes'] ?? today()->month)])
                    : null)
                ->openUrlInNewTab(),
            $this->ajudaAction('Espelho de Ponto', HelpContent::make('🗓️', 'Espelho de Ponto', 'O resumo do mês: o que foi marcado, o que era previsto e o saldo de cada dia.')
                ->secao('📖 Como ler', [
                    ['⏱️', 'Saldo', 'Diferença entre o trabalhado e o previsto no dia. Variações de até 10 minutos no dia não contam (CLT, art. 58, § 1º).'],
                    ['✳️', 'Marcação com asterisco', 'Foi incluída por um ajuste aprovado; o registro original continua intacto.'],
                    ['⚠️', 'Alertas', 'Intervalo curto, descanso de menos de 11 horas entre jornadas, excesso de horas no dia, trabalho em feriado.'],
                    ['💼', 'Crédito no banco × extras a pagar', 'Só vai para o banco de horas o saldo dos dias cobertos por um acordo vigente. Sem acordo, a hora excedente é hora extra a pagar.'],
                ])
                ->alerta('Dia "Incompleto" tem número ímpar de marcações. Peça um ajuste em "Ajustes de Ponto" para que ele entre na apuração.')),
        ];
    }
}
