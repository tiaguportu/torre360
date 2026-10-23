<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource;
use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Services\FaltasExcessivasService;
use App\Support\HelpContent;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Alunos com faltas acima do limite de notificação (Onda 57): a lista da coordenação, com as faltas totais e as
 * injustificadas lado a lado, a justificativa de faltas (atestado) e a abertura do caso.
 */
class FaltasExcessivas extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 29;

    protected static ?string $title = 'Alunos Acima do Limite de Faltas';

    protected static ?string $navigationLabel = 'Faltas Excessivas';

    protected static ?string $slug = 'academico/faltas-excessivas';

    protected string $view = 'filament.pages.faltas-excessivas';

    #[Url(as: 'periodo')]
    public ?int $periodoId = null;

    #[Url(as: 'turma')]
    public ?int $turmaId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('Apurar:CasoFrequencia');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->periodoId ??= PeriodoLetivo::query()->whereDate('data_inicio', '<=', today())->orderByDesc('data_inicio')->value('id')
            ?? PeriodoLetivo::query()->orderByDesc('data_inicio')->value('id');
    }

    public function updatedPeriodoId(): void
    {
        if ($this->turmaId && ! Turma::query()->whereKey($this->turmaId)->where('periodo_letivo_id', $this->periodoId)->exists()) {
            $this->turmaId = null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodos(): array
    {
        abort_unless(static::canAccess(), 403);

        return PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public function getTurmas(): array
    {
        abort_unless(static::canAccess(), 403);

        return Turma::query()->where('periodo_letivo_id', $this->periodoId)->where('status', '!=', 'cancelada')->orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function getPeriodo(): ?PeriodoLetivo
    {
        return $this->periodoId ? PeriodoLetivo::query()->find($this->periodoId) : null;
    }

    public function getLimitePercentual(): float
    {
        return app(FaltasExcessivasService::class)->limitePercentual();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getLinhas(): Collection
    {
        abort_unless(static::canAccess(), 403);

        $periodo = $this->getPeriodo();

        return $periodo ? app(FaltasExcessivasService::class)->acimaDoLimite($periodo, $this->turmaId) : collect();
    }

    public function abrirCasoAction(): Action
    {
        return Action::make('abrirCaso')
            ->label('Abrir caso')
            ->icon('heroicon-o-folder-plus')
            ->color('danger')
            ->size('xs')
            ->visible(fn (): bool => (bool) auth()->user()?->can('Create:CasoFrequencia'))
            ->modalHeading(fn (array $arguments): string => 'Abrir caso: '.($this->matriculaDosArgumentos($arguments)?->pessoa?->nome ?? 'aluno'))
            ->modalDescription('O caso guarda as faltas de hoje e o registro dos contatos com a família. Depois dele, gere o ofício ao Conselho Tutelar.')
            ->schema([
                Textarea::make('observacao')->label('Observação interna (opcional)')->helperText('Fica criptografada.')->rows(3)->maxLength(1000),
            ])
            ->action(function (array $data, array $arguments) {
                $matricula = $this->matriculaDosArgumentos($arguments);
                $periodo = $this->getPeriodo();

                if (! $matricula || ! $periodo) {
                    return null;
                }

                try {
                    $caso = app(FaltasExcessivasService::class)->abrirCaso($matricula, $periodo, auth()->user(), $data['observacao'] ?? null);
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível abrir o caso')->body($e->getMessage())->danger()->send();

                    return null;
                }

                Notification::make()->title('Caso aberto')->body('Registre o contato com a família e, se for o caso, gere o ofício.')->success()->send();

                return redirect(CasoFrequenciaResource::getUrl('view', ['record' => $caso]));
            });
    }

    public function justificarAction(): Action
    {
        return Action::make('justificar')
            ->label('Justificar faltas')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->size('xs')
            ->visible(fn (): bool => (bool) auth()->user()?->can('Update:CasoFrequencia'))
            ->modalHeading(fn (array $arguments): string => 'Justificar faltas: '.($this->matriculaDosArgumentos($arguments)?->pessoa?->nome ?? 'aluno'))
            ->modalDescription('Marque as faltas que têm justificativa (atestado médico, óbito na família...). Elas continuam contando como faltas na lei, mas aparecem como justificadas no caso e no ofício.')
            ->modalWidth('3xl')
            ->schema(fn (array $arguments): array => [
                CheckboxList::make('faltas')
                    ->label('Faltas ainda não justificadas')
                    ->options($this->faltasInjustificadas($this->matriculaDosArgumentos($arguments)))
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->required(),
                TextInput::make('justificativa')->label('Justificativa')->required()->maxLength(160),
            ])
            ->action(function (array $data, array $arguments): void {
                $matricula = $this->matriculaDosArgumentos($arguments);

                if (! $matricula) {
                    return;
                }

                try {
                    $total = app(FaltasExcessivasService::class)->justificarFaltas($matricula, array_map('intval', $data['faltas'] ?? []), (string) $data['justificativa'], auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível justificar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title("{$total} falta(s) justificada(s)")->success()->send();
            });
    }

    /**
     * Faltas do aluno no período que ainda não foram justificadas: id => "dd/mm — Disciplina".
     *
     * @return array<int, string>
     */
    private function faltasInjustificadas(?Matricula $matricula): array
    {
        $periodo = $this->getPeriodo();

        if (! $matricula || ! $periodo) {
            return [];
        }

        $aulas = (new CronogramaAula)->getTable();
        $frequencias = (new FrequenciaEscolar)->getTable();

        return FrequenciaEscolar::query()
            ->join($aulas, "{$aulas}.id", '=', "{$frequencias}.cronograma_aula_id")
            ->where("{$frequencias}.matricula_id", $matricula->id)
            ->where("{$frequencias}.situacao", 'ausente')
            ->where("{$frequencias}.justificada", false)
            ->whereDate("{$aulas}.data", '>=', $periodo->data_inicio)
            ->whereDate("{$aulas}.data", '<=', $periodo->data_fim)
            ->whereNull("{$aulas}.nao_realizada_em")
            ->leftJoin('disciplina', 'disciplina.id', '=', "{$aulas}.disciplina_id")
            ->orderBy("{$aulas}.data")
            ->select("{$frequencias}.id as id", "{$aulas}.data as data", 'disciplina.nome as disciplina')
            ->get()
            ->mapWithKeys(fn ($linha): array => [(int) $linha->id => Carbon::parse($linha->data)->format('d/m/Y').' — '.($linha->disciplina ?? 'Aula')])
            ->all();
    }

    /**
     * A matrícula vem do cliente: só vale se estiver na lista de alunos acima do limite que a tela mostra.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function matriculaDosArgumentos(array $arguments): ?Matricula
    {
        abort_unless(static::canAccess(), 403);

        $id = (int) ($arguments['matricula'] ?? 0);

        if ($id === 0) {
            return null;
        }

        $linha = $this->getLinhas()->first(fn (array $l): bool => $l['matricula']->id === $id);

        return $linha['matricula'] ?? null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('casos')
                ->label('Casos de faltas')
                ->icon('heroicon-o-shield-exclamation')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ViewAny:CasoFrequencia'))
                ->url(fn (): string => CasoFrequenciaResource::getUrl('index')),
            $this->ajudaAction('Faltas Excessivas', HelpContent::make('⚠️', 'Faltas Excessivas', 'Alunos que passaram do limite de faltas para comunicar o Conselho Tutelar.')
                ->passos('🔁 Como usar', [
                    'Escolha o período letivo (e a turma, se quiser). A lista mostra os alunos ativos acima do limite.',
                    'Se uma falta tem atestado ou outra justificativa, use "Justificar faltas". Ela segue valendo como falta na lei, mas aparece justificada.',
                    'Clique em "Abrir caso" para guardar a apuração de hoje e começar a registrar os contatos com a família.',
                ])
                ->secao('⚖️ A regra', [
                    ['📏', 'O limite', 'A LDB (art. 12, VIII) manda notificar o Conselho Tutelar quando as faltas passam de 30% do percentual permitido. Com 75% de frequência mínima, são 7,5% das aulas previstas no ano.'],
                    ['📅', 'Aulas previstas', 'Vêm do cronograma de aulas da turma no período (a mesma conta da Carga Letiva). Aula marcada como não realizada não entra.'],
                    ['👨‍👩‍👧', 'Antes do ofício', 'O ECA (art. 56, II) fala em faltas injustificadas e em recursos escolares esgotados: por isso a escola registra os contatos com a família antes de enviar o ofício.'],
                ])
                ->alerta('Quem decide notificar é a direção. A tela só mostra os números e guarda o histórico.')),
        ];
    }
}
