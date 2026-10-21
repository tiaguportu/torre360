<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exceptions\PontoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\Funcionario;
use App\Models\PontoBancoHorasLancamento;
use App\Services\Ponto\BancoHorasService;
use App\Support\FuncionarioDoUsuario;
use App\Support\HelpContent;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Banco de horas: saldo, créditos a vencer (com prazo de compensação), extrato e horas vencidas a pagar. O trabalhador
 * vê o próprio; a administração escolhe o funcionário e pode registrar compensação (folga) e ajuste manual.
 */
class BancoDeHoras extends Page implements HasForms
{
    use HasAjudaAction;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 22;

    protected static ?string $title = 'Banco de Horas';

    protected static ?string $navigationLabel = 'Banco de Horas';

    protected static ?string $slug = 'rh/banco-de-horas';

    protected string $view = 'filament.pages.banco-de-horas';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filtros = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (FuncionarioDoUsuario::para($user) !== null || Gate::allows('verTodos', PontoBancoHorasLancamento::class));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(['funcionario_id' => FuncionarioDoUsuario::para(auth()->user())?->id]);
    }

    public function podeEscolherFuncionario(): bool
    {
        return Gate::allows('verTodos', PontoBancoHorasLancamento::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filtros')
            ->components([
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
            ]);
    }

    public function getFuncionarioSelecionado(): ?Funcionario
    {
        $proprio = FuncionarioDoUsuario::para(auth()->user());

        // Quem não vê o de todos só enxerga o próprio, qualquer que seja o valor enviado pelo navegador.
        if (! $this->podeEscolherFuncionario()) {
            return $proprio;
        }

        $id = $this->filtros['funcionario_id'] ?? null;

        return $id ? Funcionario::query()->with('pessoa')->find($id) : $proprio;
    }

    /**
     * @return array{saldo: int, utilizavel: int, devedor: int, creditos: list<array{lancamento: PontoBancoHorasLancamento, restante: int}>, extrato: Collection<int, PontoBancoHorasLancamento>, a_pagar: int}|null
     */
    public function getResumo(): ?array
    {
        $funcionario = $this->getFuncionarioSelecionado();

        if (! $funcionario) {
            return null;
        }

        abort_unless(Gate::allows('ver', [PontoBancoHorasLancamento::class, $funcionario]), 403);

        $banco = app(BancoHorasService::class);
        $posicao = $banco->posicao($funcionario);

        return [
            'saldo' => $banco->saldo($funcionario),
            'utilizavel' => $posicao['utilizavel'],
            'devedor' => $posicao['devedor'],
            'creditos' => collect($posicao['creditos'])->sortBy(fn (array $c): string => $c['lancamento']->vence_em?->toDateString() ?? '9999-12-31')->values()->all(),
            'extrato' => PontoBancoHorasLancamento::query()->where('funcionario_id', $funcionario->id)->orderByDesc('data')->orderByDesc('id')->limit(60)->get(),
            'a_pagar' => $banco->horasAPagar($funcionario, today()->subYear(), today()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compensar')
                ->label('Registrar folga (compensação)')
                ->icon('heroicon-o-calendar-days')
                ->visible(fn (): bool => Gate::allows('compensar', PontoBancoHorasLancamento::class) && $this->getFuncionarioSelecionado() !== null)
                ->schema([
                    DatePicker::make('data')->label('Data da folga')->native(false)->displayFormat('d/m/Y')->default(today())->required(),
                    TextInput::make('horas')->label('Horas compensadas')->numeric()->step(0.25)->minValue(0.25)->maxValue(24)->required()->helperText('Use fração para minutos: 1,5 = 1 h 30 min.'),
                    Textarea::make('observacao')->label('Observação')->rows(2)->maxLength(255),
                ])
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('compensar', PontoBancoHorasLancamento::class), 403);

                    try {
                        app(BancoHorasService::class)->compensar($this->getFuncionarioSelecionado(), Carbon::parse($data['data']), (int) round(((float) $data['horas']) * 60), auth()->user(), $data['observacao'] ?? null);
                    } catch (PontoException $e) {
                        Notification::make()->title('Não foi possível registrar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Folga registrada')->success()->send();
                }),
            Action::make('ajustar')
                ->label('Ajuste manual')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('ajustar', PontoBancoHorasLancamento::class) && $this->getFuncionarioSelecionado() !== null)
                ->schema([
                    DatePicker::make('data')->label('Data de referência')->native(false)->displayFormat('d/m/Y')->default(today())->required(),
                    TextInput::make('horas')->label('Horas (negativo para debitar)')->numeric()->step(0.25)->required(),
                    Textarea::make('motivo')->label('Motivo')->rows(3)->required()->minLength(10)->maxLength(255)->helperText('Fica registrado junto do seu nome. O lançamento original nunca é alterado.'),
                ])
                ->action(function (array $data): void {
                    abort_unless(Gate::allows('ajustar', PontoBancoHorasLancamento::class), 403);

                    try {
                        app(BancoHorasService::class)->ajustarManualmente($this->getFuncionarioSelecionado(), Carbon::parse($data['data']), (int) round(((float) $data['horas']) * 60), (string) $data['motivo'], auth()->user());
                    } catch (PontoException $e) {
                        Notification::make()->title('Não foi possível registrar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Ajuste registrado')->success()->send();
                }),
            $this->ajudaAction('Banco de Horas', HelpContent::make('⚖️', 'Banco de Horas', 'As horas a mais que viram folga, com prazo para serem compensadas.')
                ->secao('📖 Como funciona', [
                    ['➕', 'Crédito', 'Cada dia com saldo positivo, coberto por acordo de banco de horas vigente, vira crédito com data limite para compensar.'],
                    ['➖', 'Débito', 'Atrasos e folgas consomem primeiro o crédito que vence antes.'],
                    ['⏳', 'Prazo', 'Acordo individual escrito: até 6 meses. Acordo ou convenção coletiva: até 12 meses. Compensação no mesmo mês: até o fim do mês (CLT, art. 59).'],
                    ['💰', 'Horas a pagar', 'O crédito que vence sem ser compensado deixa de ser saldo e é pago como hora extra (CLT, art. 59, § 3º).'],
                ])
                ->alerta('Sem acordo vigente, a hora excedente não entra no banco: é hora extra a pagar. Cadastre o acordo na ficha do funcionário (aba "Acordos de banco de horas").')),
        ];
    }
}
