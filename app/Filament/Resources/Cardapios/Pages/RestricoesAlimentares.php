<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Cardapios\CardapioResource;
use App\Models\Cardapio;
use App\Services\CardapioAlergiaService;
use App\Support\HelpContent;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Para a cozinha: dado um dia, cada refeição e os alunos cuja restrição alimentar conflita com ela. É dado de saúde
 * de criança, então exige `VerAlergicos:Cardapio` e cada consulta fica registrada no log de atividades (sem o
 * conteúdo).
 */
class RestricoesAlimentares extends Page implements HasForms
{
    use HasAjudaAction;
    use InteractsWithForms;

    protected static string $resource = CardapioResource::class;

    protected string $view = 'filament.resources.cardapios.pages.restricoes-alimentares';

    protected static ?string $title = 'Restrições alimentares do dia';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filtros = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('verAlergicos', Cardapio::class);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(['data' => today()->toDateString()]);
        $this->registrarConsulta();
    }

    public function updatedFiltros(): void
    {
        $this->registrarConsulta();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filtros')
            ->components([
                DatePicker::make('data')
                    ->label('Dia')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->required(),
            ]);
    }

    /**
     * Refeições do dia (publicadas ou não: a cozinha precisa do que vai ser servido) e os alunos afetados.
     *
     * @return list<array{cardapio: Cardapio, afetados: array<string, list<array<string, mixed>>>}>
     */
    public function getRefeicoes(): array
    {
        abort_unless(static::canAccess(), 403);

        $dia = Carbon::parse($this->filtros['data'] ?? today());
        $servico = app(CardapioAlergiaService::class);

        return Cardapio::query()
            ->whereDate('data', $dia->toDateString())
            ->with('unidade')
            ->get()
            ->sortBy(fn (Cardapio $c): int => $c->tipo_refeicao->ordem())
            ->map(fn (Cardapio $c): array => ['cardapio' => $c, 'afetados' => $servico->alunosAfetados($c)])
            ->values()
            ->all();
    }

    private function registrarConsulta(): void
    {
        activity('cardapio')
            ->causedBy(auth()->user())
            ->withProperties(['data' => $this->filtros['data'] ?? today()->toDateString()])
            ->log('Consulta às restrições alimentares dos alunos.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voltar')
                ->label('Voltar ao cardápio')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => CardapioResource::getUrl('index')),
            Action::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.print()']),
            $this->ajudaAction('Restrições alimentares do dia', HelpContent::make('⚠️', 'Restrições alimentares do dia', 'Quais alunos têm restrição que conflita com o que será servido.')
                ->secao('📖 Como ler', [
                    ['🔴', 'Confirmado', 'O aluno tem o campo estruturado da ficha médica (lactose, glúten ou amendoim) e a refeição declara o alérgeno.'],
                    ['🟠', 'Possível', 'O texto livre das restrições do aluno cita o alérgeno. Confira com a família: o texto não é interpretado.'],
                    ['🟡', 'Conferir', 'O aluno tem restrição em texto livre que não casou com nenhum alérgeno declarado. Compare com a descrição do cardápio.'],
                ])
                ->alerta('Dado de saúde de criança: use só para preparar as refeições. Cada consulta fica registrada.')),
        ];
    }
}
