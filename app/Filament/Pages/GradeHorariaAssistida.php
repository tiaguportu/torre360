<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\TipoDisponibilidadeProfessor;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\DisponibilidadeProfessor;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Services\DisponibilidadeProfessorService;
use App\Services\GradeAssistidaService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Grade horária assistida (Onda 64): a grade semanal de uma turma conferida contra a matriz curricular da série, a
 * disponibilidade dos professores da turma e a sugestão dos horários que faltam.
 */
class GradeHorariaAssistida extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static UnitEnum|string|null $navigationGroup = 'Calendário e Horários';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Grade Horária Assistida';

    protected static ?string $navigationLabel = 'Grade Assistida';

    protected static ?string $slug = 'calendario/grade-assistida';

    protected string $view = 'filament.pages.grade-horaria-assistida';

    public ?int $periodoId = null;

    #[Url(as: 'turma')]
    public ?int $turmaId = null;

    /** @var array<string, mixed>|null */
    protected ?array $sugestaoCalculada = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('GradeAssistida:Turma');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $periodoDaTurma = $this->turmaId ? Turma::query()->whereKey($this->turmaId)->value('periodo_letivo_id') : null;

        $this->periodoId = $periodoDaTurma
            ?? PeriodoLetivo::query()->whereDate('data_inicio', '<=', today())->orderByDesc('data_inicio')->value('id')
            ?? PeriodoLetivo::query()->orderByDesc('data_inicio')->value('id');

        if ($this->turmaId && ! $periodoDaTurma) {
            $this->turmaId = null;
        }
    }

    /**
     * Trocar o período limpa a turma escolhida se ela não for dele.
     */
    public function updatedPeriodoId(): void
    {
        if ($this->turmaId && ! Turma::query()->whereKey($this->turmaId)->where('periodo_letivo_id', $this->periodoId)->exists()) {
            $this->turmaId = null;
        }
    }

    private function servico(): GradeAssistidaService
    {
        return app(GradeAssistidaService::class);
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

        return Turma::query()
            ->where('periodo_letivo_id', $this->periodoId)
            ->where('status', '!=', 'cancelada')
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    public function getPeriodo(): ?PeriodoLetivo
    {
        return $this->periodoId ? PeriodoLetivo::query()->find($this->periodoId) : null;
    }

    public function getTurma(): ?Turma
    {
        abort_unless(static::canAccess(), 403);

        return $this->turmaId
            ? Turma::query()->where('periodo_letivo_id', $this->periodoId)->with(['serie', 'turno'])->find($this->turmaId)
            : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getCobertura(): Collection
    {
        $turma = $this->getTurma();

        return $turma ? $this->servico()->cobertura($turma) : collect();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGrade(): ?array
    {
        $turma = $this->getTurma();

        return $turma ? $this->servico()->grade($turma) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getVisaoGeral(): Collection
    {
        abort_unless(static::canAccess(), 403);

        $periodo = $this->getPeriodo();

        return $periodo ? $this->servico()->visaoGeral($periodo) : collect();
    }

    /**
     * Professores que lecionam disciplinas da turma, com o resumo das janelas de cada um.
     *
     * @return Collection<int, array{id: int, nome: string, resumo: string, disciplinas: string}>
     */
    public function getProfessores(): Collection
    {
        $turma = $this->getTurma();

        if (! $turma) {
            return collect();
        }

        $cobertura = $this->servico()->cobertura($turma)->filter(fn (array $linha): bool => $linha['professor_id'] !== null);
        $janelas = app(DisponibilidadeProfessorService::class)->janelasDe($cobertura->pluck('professor_id'));

        return $cobertura
            ->groupBy('professor_id')
            ->map(fn (Collection $linhas, int|string $professorId): array => [
                'id' => (int) $professorId,
                'nome' => (string) $linhas->first()['professor'],
                'resumo' => app(DisponibilidadeProfessorService::class)->resumo($janelas->get((int) $professorId, collect())),
                'disciplinas' => $linhas->pluck('disciplina')->implode(', '),
            ])
            ->sortBy('nome')
            ->values();
    }

    /**
     * A sugestão para a turma escolhida, calculada uma vez por requisição.
     *
     * @return array<string, mixed>
     */
    public function sugestao(): array
    {
        $turma = $this->getTurma();

        if (! $turma) {
            return ['propostas' => [], 'nao_alocadas' => [], 'avisos' => []];
        }

        return $this->sugestaoCalculada ??= $this->servico()->sugerir($turma);
    }

    // --- Ações -------------------------------------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sugerir')
                ->label('Sugerir horários')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (): bool => $this->turmaId !== null)
                ->modalHeading('Sugestão de horários')
                ->modalDescription('Confira o que será colocado na grade. Nada muda até você aplicar. A sugestão é calculada de novo na hora de aplicar.')
                ->modalSubmitActionLabel('Aplicar sugestão')
                ->modalContent(fn () => view('filament.pages.partials.grade-sugestao', ['sugestao' => $this->sugestao(), 'dias' => DisponibilidadeProfessorService::DIAS]))
                ->action(function (): void {
                    $turma = $this->getTurma();

                    if (! $turma) {
                        return;
                    }

                    $resultado = $this->servico()->aplicar($turma, auth()->user());
                    $this->sugestaoCalculada = null;

                    if ($resultado['criadas'] === 0 && $resultado['ignoradas'] === []) {
                        Notification::make()
                            ->title('Nada para aplicar')
                            ->body($resultado['avisos'] !== [] ? implode(' ', $resultado['avisos']) : 'A grade desta turma já está completa ou não há horário livre.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $corpo = "{$resultado['criadas']} horário(s) colocado(s) na grade.";

                    if ($resultado['ignoradas'] !== []) {
                        $corpo .= ' Ficaram de fora por conflito: '.implode(' | ', array_slice($resultado['ignoradas'], 0, 3)).'.';
                    }

                    if ($resultado['nao_alocadas'] !== []) {
                        $corpo .= ' Aulas sem lugar: '.collect($resultado['nao_alocadas'])->sum('quantidade').'. Veja a conferência abaixo.';
                    }

                    $notificacao = Notification::make()->title('Sugestão aplicada')->body($corpo);
                    $resultado['ignoradas'] === [] && $resultado['nao_alocadas'] === [] ? $notificacao->success() : $notificacao->warning()->persistent();
                    $notificacao->send();
                }),
            $this->ajudaAction('Grade Horária Assistida', $this->getHelpContent()),
        ];
    }

    public function disponibilidadeAction(): Action
    {
        return Action::make('disponibilidade')
            ->label('Disponibilidade')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->size('xs')
            ->modalHeading(fn (array $arguments): string => 'Disponibilidade: '.(Pessoa::query()->whereKey((int) ($arguments['professor'] ?? 0))->value('nome') ?? 'professor'))
            ->modalDescription('Sem nenhuma janela, o professor está livre o tempo todo. Com janelas "disponível", só dá aula dentro delas. "Indisponível" sempre bloqueia.')
            ->modalWidth('5xl')
            ->fillForm(function (array $arguments): array {
                $professorId = $this->professorDosArgumentos($arguments);

                return ['janelas' => $professorId
                    ? DisponibilidadeProfessor::query()->where('pessoa_id', $professorId)->orderBy('dia_semana')->orderBy('hora_inicio')->get()
                        ->map(fn (DisponibilidadeProfessor $j): array => [
                            'dia_semana' => $j->dia_semana,
                            'hora_inicio' => $j->inicio(),
                            'hora_fim' => $j->fim(),
                            'tipo' => $j->tipo->value,
                            'observacao' => $j->observacao,
                        ])->all()
                    : []];
            })
            ->schema([
                Repeater::make('janelas')
                    ->label('Janelas')
                    ->schema([
                        Select::make('dia_semana')
                            ->label('Dia')
                            ->options(DisponibilidadeProfessorService::DIAS)
                            ->required(),
                        TimePicker::make('hora_inicio')
                            ->label('Das')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('hora_fim')
                            ->label('Às')
                            ->seconds(false)
                            ->after('hora_inicio')
                            ->required(),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoDisponibilidadeProfessor::class)
                            ->default(TipoDisponibilidadeProfessor::Disponivel->value)
                            ->required(),
                        TextInput::make('observacao')
                            ->label('Observação')
                            ->maxLength(160),
                    ])
                    ->columns(5)
                    ->defaultItems(0)
                    ->maxItems(30)
                    ->addActionLabel('Acrescentar janela'),
            ])
            ->action(function (array $data, array $arguments): void {
                $professorId = $this->professorDosArgumentos($arguments);

                if (! $professorId) {
                    return;
                }

                try {
                    app(DisponibilidadeProfessorService::class)->substituir($professorId, array_values($data['janelas'] ?? []));
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível salvar')->body($e->getMessage())->danger()->send();

                    return;
                }

                activity('calendario')->causedBy(auth()->user())->withProperties(['pessoa_id' => $professorId, 'janelas' => count($data['janelas'] ?? [])])->log('Disponibilidade do professor atualizada.');

                $this->sugestaoCalculada = null;
                Notification::make()->title('Disponibilidade salva')->success()->send();
            });
    }

    /**
     * O professor vem do cliente: só vale se leciona alguma disciplina da turma escolhida.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function professorDosArgumentos(array $arguments): ?int
    {
        abort_unless(static::canAccess(), 403);

        $professorId = (int) ($arguments['professor'] ?? 0);

        if (! $this->turmaId || ! $professorId) {
            return null;
        }

        $leciona = DB::table('turma_disciplina')->where('turma_id', $this->turmaId)->where('professor_id', $professorId)->exists();

        if (! $leciona) {
            Notification::make()->title('Professor não encontrado nesta turma')->danger()->send();

            return null;
        }

        return $professorId;
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🗓️', 'Grade Horária Assistida', 'Confira a grade da turma e deixe o sistema sugerir os horários que faltam.')
            ->passos('🔁 Como usar', [
                'Cadastre os tempos de aula de cada turno em Configurações > Turnos (aba "Tempos de aula"): o sistema só sabe onde cabe cada aula com eles.',
                'Na Matriz Curricular, informe quantas aulas por semana cada disciplina tem na série, e em cada turma defina o professor de cada disciplina.',
                'Escolha o período e a turma. A conferência mostra o que a matriz pede, o que já está na grade e o que falta.',
                'Em "Disponibilidade dos professores", informe quando cada um pode (ou não) dar aula.',
                'Clique em "Sugerir horários", confira a proposta e aplique. Depois ajuste na aba "Grade Horária" da turma e gere o cronograma.',
            ])
            ->secao('🎯 O que a sugestão respeita', [
                ['🕒', 'Tempos do turno', 'Cada aula ocupa um tempo livre da turma.'],
                ['👩‍🏫', 'Professor', 'Não coloca o professor em dois lugares ao mesmo tempo (nem em outra turma do mesmo período) nem fora da disponibilidade dele.'],
                ['📚', 'Distribuição', 'Espalha a disciplina pela semana; dois tempos seguidos só quando precisa.'],
                ['🔒', 'O que já existe', 'Nunca troca nem apaga horários que já estão na grade.'],
            ])
            ->alerta('A sugestão é um ponto de partida, não uma otimização: se sobrar aula sem lugar, o motivo aparece. Libere horários, ajuste a disponibilidade ou o limite diário da disciplina (config/grade_horaria.php) e sugira de novo.')
            ->dica('Disciplina sem professor definido na turma não é sugerida: sem professor o sistema não tem como evitar conflitos.');
    }
}
