<?php

namespace App\Filament\Pages;

use App\Models\Curso;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turno;
use App\Services\EnsalamentoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Remanejamento de alunos entre as turmas de um período letivo.
 *
 * Toda matrícula já nasce numa turma, então esta tela não "ensala" ninguém: ela mostra a ocupação das
 * turmas, move alunos de uma turma para outra e redistribui os alunos de várias turmas para equilibrá-las.
 */
class EnsalamentoTurmas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $title = 'Remanejamento de Turmas';

    protected static ?string $navigationLabel = 'Remanejamento de Turmas';

    protected static ?string $slug = 'ensalamento';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.ensalamento-turmas';

    // Filtros
    public ?int $periodoLetivoId = null;

    public ?int $cursoId = null;

    public ?int $serieId = null;

    public ?int $turnoId = null;

    // Modal de Redistribuição Automática
    public bool $showModalDistribuicao = false;

    public array $turmasSelecionadasDistribuicao = [];

    public string $criterioDistribuicao = 'equilibrio_genero';

    public bool $respeitarLimiteVagas = true;

    // Modal de Mover Aluno
    public bool $showModalMover = false;

    public ?int $matriculaMoverId = null;

    public ?string $alunoMoverNome = null;

    public ?int $novaTurmaId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Ensalamento') ?? false;
    }

    /**
     * Quem grava (mover, redistribuir) precisa da permissão `Manage:Ensalamento`: os métodos públicos
     * do Livewire podem ser chamados direto, então esconder o botão não basta.
     */
    private function exigirPermissaoDeGestao(): void
    {
        abort_unless(auth()->user()?->can('Manage:Ensalamento'), 403);
    }

    public function mount(): void
    {
        // Abre no período em vigor hoje; se nenhum estiver em vigor, no mais recente.
        $this->periodoLetivoId = (
            PeriodoLetivo::query()
                ->whereDate('data_inicio', '<=', today())
                ->whereDate('data_fim', '>=', today())
                ->orderByDesc('data_inicio')
                ->first()
            ?? PeriodoLetivo::latest('data_inicio')->first()
        )?->id;

        $primeiroCurso = Curso::first();
        $this->cursoId = $primeiroCurso?->id;

        $primeiraSerie = Serie::where('curso_id', $this->cursoId)->first() ?: Serie::first();
        $this->serieId = $primeiraSerie?->id;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('distribuir_automatico')
                ->label('Redistribuição Automática')
                ->icon('heroicon-o-cpu-chip')
                ->color('primary')
                ->visible(fn () => auth()->user()->can('Manage:Ensalamento'))
                ->action(fn () => $this->abrirModalDistribuicao()),

            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Remanejamento de Turmas')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                ViewField::make('help_content')
                    ->view('filament.components.help-content')
                    ->viewData(['content' => $this->getHelpContent()]),
            ]);
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $canManage = $user->can('Manage:Ensalamento');

        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">O <strong>Remanejamento de Turmas</strong> permite acompanhar a ocupação das turmas de um período letivo e mover alunos entre elas de forma visual e balanceada. Toda matrícula já nasce numa turma (escolhida na matrícula ou na rematrícula); aqui você apenas ajusta a distribuição.</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Principais Recursos:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Cards Visuais de Turmas:</strong> Acompanhe a ocupação em tempo real (matrículas Ativas, Pendentes e em Reserva), a capacidade máxima das salas e o equilíbrio de gênero (meninos vs. meninas). Aparecem as turmas Planejadas e Ativas do período e da série escolhidos.</li>';

        if ($canManage) {
            $html .= '<li><strong>Mover aluno:</strong> Transfira um aluno para outra turma do mesmo período letivo. O sistema confere a vaga e não permite mover para turma lotada, fechada ou de outro período (para mudar de período use a rematrícula).</li>';
            $html .= '<li><strong>Redistribuição Automática:</strong> Algoritmo que embaralha os alunos das turmas escolhidas equilibrando meninos e meninas, por ordem alfabética ou por faixa etária.</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    public function updatedCursoId($value): void
    {
        $this->serieId = Serie::where('curso_id', $value)->first()?->id;
    }

    public function getCursosProperty(): Collection
    {
        return Curso::orderBy('nome_externo')->get();
    }

    public function getSeriesProperty(): Collection
    {
        $query = Serie::query();
        if ($this->cursoId) {
            $query->where('curso_id', $this->cursoId);
        }

        return $query->orderBy('nome')->get();
    }

    public function getPeriodosProperty(): Collection
    {
        return PeriodoLetivo::orderByDesc('data_inicio')->get();
    }

    public function getTurnosProperty(): Collection
    {
        return Turno::orderBy('nome')->get();
    }

    public function getTurmasCenarioProperty(): Collection
    {
        if (! $this->periodoLetivoId || ! $this->serieId) {
            return collect();
        }

        return app(EnsalamentoService::class)->obterTurmasCenario(
            $this->periodoLetivoId,
            $this->serieId,
            $this->turnoId
        );
    }

    public function abrirModalDistribuicao(): void
    {
        $this->exigirPermissaoDeGestao();

        if ($this->turmasCenario->isEmpty()) {
            Notification::make()
                ->title('Nenhuma turma cadastrada')
                ->body('Cadastre turmas para esta série e período antes de executar a redistribuição automática.')
                ->warning()
                ->send();

            return;
        }

        $this->turmasSelecionadasDistribuicao = $this->turmasCenario->pluck('id')->toArray();
        $this->criterioDistribuicao = 'equilibrio_genero';
        $this->respeitarLimiteVagas = true;
        $this->showModalDistribuicao = true;
    }

    public function executarDistribuicaoAutomatica(): void
    {
        $this->exigirPermissaoDeGestao();

        if (empty($this->turmasSelecionadasDistribuicao)) {
            Notification::make()->title('Selecione ao menos uma turma participante.')->warning()->send();

            return;
        }

        try {
            $resultado = app(EnsalamentoService::class)->distribuirAutomaticamente(
                $this->serieId,
                $this->periodoLetivoId,
                $this->turmasSelecionadasDistribuicao,
                $this->criterioDistribuicao,
                $this->respeitarLimiteVagas
            );

            $this->showModalDistribuicao = false;

            Notification::make()
                ->title('Redistribuição Concluída!')
                ->body("Total de {$resultado['total_distribuidos']} estudante(s) redistribuído(s) harmonicamente.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro na Redistribuição')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function abrirModalMover(int $matriculaId, string $nomeAluno): void
    {
        $this->exigirPermissaoDeGestao();

        $this->matriculaMoverId = $matriculaId;
        $this->alunoMoverNome = $nomeAluno;
        $this->novaTurmaId = null;
        $this->showModalMover = true;
    }

    public function confirmarMover(): void
    {
        $this->exigirPermissaoDeGestao();

        if (! $this->matriculaMoverId || ! $this->novaTurmaId) {
            return;
        }

        try {
            app(EnsalamentoService::class)->alocarAlunosEmTurma([$this->matriculaMoverId], $this->novaTurmaId);

            $this->showModalMover = false;

            Notification::make()
                ->title('Estudante Transferido!')
                ->body("O estudante {$this->alunoMoverNome} foi transferido de turma.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao Transferir')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
