<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\CronogramaAula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Services\CargaLetivaService;
use App\Services\ReposicaoAulaService;
use App\Support\HelpContent;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Carga letiva (Onda 49): dias e horas de aula previstos, realizados e projetados de cada turma contra o mínimo da LDB, a
 * aula que não aconteceu e a sua reposição.
 */
class CargaLetiva extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static UnitEnum|string|null $navigationGroup = 'Calendário e Horários';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Carga Letiva';

    protected static ?string $navigationLabel = 'Carga Letiva';

    protected static ?string $slug = 'calendario/carga-letiva';

    protected string $view = 'filament.pages.carga-letiva';

    #[Url(as: 'periodo')]
    public ?int $periodoId = null;

    #[Url(as: 'turma')]
    public ?int $turmaId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('CargaLetiva:Turma');
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

    public function podeRepor(): bool
    {
        return (bool) auth()->user()?->can('Reposicao:Turma');
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodos(): array
    {
        abort_unless(static::canAccess(), 403);

        return PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all();
    }

    public function getPeriodo(): ?PeriodoLetivo
    {
        return $this->periodoId ? PeriodoLetivo::query()->find($this->periodoId) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getTurmasApuradas(): Collection
    {
        abort_unless(static::canAccess(), 403);

        $periodo = $this->getPeriodo();

        return $periodo ? app(CargaLetivaService::class)->apurarPeriodo($periodo) : collect();
    }

    public function getTurma(): ?Turma
    {
        abort_unless(static::canAccess(), 403);

        return $this->turmaId ? Turma::query()->where('periodo_letivo_id', $this->periodoId)->find($this->turmaId) : null;
    }

    /**
     * @return Collection<int, CronogramaAula>
     */
    public function getAulasSemRegistro(Turma $turma): Collection
    {
        return app(CargaLetivaService::class)->aulasSemRegistro($turma)->load('disciplina');
    }

    /**
     * @return Collection<int, CronogramaAula>
     */
    public function getAulasNaoRealizadas(Turma $turma): Collection
    {
        return app(CargaLetivaService::class)->aulasNaoRealizadas($turma);
    }

    public function naoRealizadaAction(): Action
    {
        return Action::make('naoRealizada')
            ->label('Não aconteceu')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->size('xs')
            ->visible(fn (): bool => $this->podeRepor())
            ->modalHeading('Aula que não aconteceu')
            ->modalDescription('A aula sai da carga letiva. Depois, agende a reposição para ela voltar à conta.')
            ->schema([
                TextInput::make('motivo')->label('Motivo')->placeholder('Falta do professor, evento, chuva...')->required()->maxLength(160),
            ])
            ->action(function (array $data, array $arguments): void {
                $aula = $this->aulaDosArgumentos($arguments);

                if (! $aula) {
                    return;
                }

                try {
                    app(ReposicaoAulaService::class)->marcarNaoRealizada($aula, (string) $data['motivo'], auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível marcar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Aula marcada como não realizada')->success()->send();
            });
    }

    public function desfazerAction(): Action
    {
        return Action::make('desfazer')
            ->label('Voltar para prevista')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->size('xs')
            ->visible(fn (): bool => $this->podeRepor())
            ->requiresConfirmation()
            ->modalHeading('Voltar a aula para prevista')
            ->modalDescription('Use se a marcação foi engano. Só dá enquanto a aula não tem reposição agendada.')
            ->action(function (array $arguments): void {
                $aula = $this->aulaDosArgumentos($arguments);

                if (! $aula) {
                    return;
                }

                try {
                    app(ReposicaoAulaService::class)->desfazerNaoRealizada($aula, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível desfazer')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('A aula voltou a ser prevista')->success()->send();
            });
    }

    public function reporAction(): Action
    {
        return Action::make('repor')
            ->label('Agendar reposição')
            ->icon('heroicon-o-calendar-days')
            ->color('primary')
            ->size('xs')
            ->visible(fn (): bool => $this->podeRepor())
            ->modalHeading('Agendar a reposição')
            ->modalDescription('A reposição entra no cronograma da turma como uma aula marcada como reposição, e conta no realizado quando acontecer.')
            ->fillForm(function (array $arguments): array {
                $aula = $this->aulaDosArgumentos($arguments);

                return $aula ? ['hora_inicio' => substr((string) $aula->hora_inicio, 0, 5), 'hora_fim' => substr((string) $aula->hora_fim, 0, 5)] : [];
            })
            ->schema([
                DatePicker::make('data')->label('Data da reposição')->native(false)->minDate(today())->required(),
                TimePicker::make('hora_inicio')->label('Das')->seconds(false)->required(),
                TimePicker::make('hora_fim')->label('Às')->seconds(false)->after('hora_inicio')->required(),
                Select::make('professor_id')
                    ->label('Professor (se for outro)')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => \App\Models\Pessoa::query()->where('nome', 'like', "%{$search}%")->orderBy('nome')->limit(30)->pluck('nome', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => \App\Models\Pessoa::query()->whereKey($value)->value('nome')),
            ])
            ->action(function (array $data, array $arguments): void {
                $aula = $this->aulaDosArgumentos($arguments);

                if (! $aula) {
                    return;
                }

                try {
                    app(ReposicaoAulaService::class)->agendarReposicao(
                        $aula,
                        Carbon::parse($data['data']),
                        (string) $data['hora_inicio'],
                        (string) $data['hora_fim'],
                        filled($data['professor_id'] ?? null) ? (int) $data['professor_id'] : null,
                        auth()->user(),
                    );
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível agendar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Reposição agendada')->success()->send();
            });
    }

    /**
     * A aula vem do cliente: só vale se for da turma escolhida na tela.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function aulaDosArgumentos(array $arguments): ?CronogramaAula
    {
        abort_unless(static::canAccess() && $this->podeRepor(), 403);

        $aula = $this->turmaId
            ? CronogramaAula::query()->where('turma_id', $this->turmaId)->find((int) ($arguments['aula'] ?? 0))
            : null;

        if (! $aula) {
            Notification::make()->title('Aula não encontrada nesta turma')->danger()->send();
        }

        return $aula;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Relatório (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => $this->periodoId !== null)
                ->url(fn (): string => route('carga-letiva.pdf', ['periodo' => $this->periodoId]))
                ->openUrlInNewTab(),
            Action::make('csv')
                ->label('Planilha (CSV)')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->visible(fn (): bool => $this->periodoId !== null)
                ->url(fn (): string => route('carga-letiva.csv', ['periodo' => $this->periodoId])),
            $this->ajudaAction('Carga Letiva', HelpContent::make('⚖️', 'Carga Letiva', 'Conferência do calendário contra o mínimo de dias e horas da LDB.')
                ->passos('🔁 Como usar', [
                    'Escolha o período letivo. Cada turma mostra os dias e as horas previstos, os já realizados e a projeção até o fim do período.',
                    'O semáforo compara com o mínimo da etapa: 800 horas e 200 dias no fundamental e na educação infantil, 1.000 horas e 200 dias no ensino médio (ajustável em config/frequencia_legal.php).',
                    'Abra uma turma para ver as aulas passadas sem chamada nem conteúdo registrados. Se a aula não aconteceu, marque "Não aconteceu" e agende a reposição.',
                    'Baixe o relatório em PDF ou planilha para a secretaria de educação ou o conselho de educação.',
                ])
                ->secao('📌 Como a conta é feita', [
                    ['⏱️', 'Horas', 'Soma a duração real de cada aula do cronograma, em hora de 60 minutos (a hora-aula de 45 ou 50 minutos não infla o total).'],
                    ['✅', 'Aula realizada', 'Tem chamada lançada ou conteúdo ministrado registrado. A aula passada sem nenhum dos dois não conta, e aparece como "sem registro".'],
                    ['📅', 'Dias', 'Dias em que a turma tem aula no cronograma (ou os dias úteis do período, se a escola configurar o modo calendário). Dias não letivos ficam de fora.'],
                    ['🔁', 'Reposição', 'A aula não realizada sai da conta e a reposição agendada entra.'],
                ])
                ->alerta('O mínimo e o que vale como dia de efetivo trabalho escolar variam por sistema de ensino. É um apoio ao planejamento: a validação do calendário é do órgão da sua rede.')),
        ];
    }
}
