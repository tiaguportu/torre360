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

class EnsalamentoTurmas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $title = 'Ensalamento em Lote Assistido';

    protected static ?string $slug = 'ensalamento';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.ensalamento-turmas';

    // Filtros
    public ?int $periodoLetivoId = null;

    public ?int $cursoId = null;

    public ?int $serieId = null;

    public ?int $turnoId = null;

    // Alocação Manual em Massa
    public array $selecionados = [];

    public ?int $turmaDestinoManualId = null;

    // Modal de Distribuição Automática Inteligente
    public bool $showModalDistribuicao = false;

    public array $turmasSelecionadasDistribuicao = [];

    public string $criterioDistribuicao = 'equilibrio_genero';

    public bool $redistribuirTodos = false;

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

    public function mount(): void
    {
        $this->periodoLetivoId = PeriodoLetivo::latest('data_inicio')->first()?->id;

        $primeiroCurso = Curso::first();
        $this->cursoId = $primeiroCurso?->id;

        $primeiraSerie = Serie::where('curso_id', $this->cursoId)->first() ?: Serie::first();
        $this->serieId = $primeiraSerie?->id;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('distribuir_automatico')
                ->label('Distribuição Automática Inteligente')
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
            ->modalHeading('Ajuda: Ensalamento em Lote Assistido')
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
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">O módulo de <strong>Ensalamento em Lote Assistido</strong> permite organizar a alocação de estudantes nas turmas no início ou durante o período letivo de forma visual, balanceada e ágil.</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Principais Recursos:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Cards Visuais de Turmas:</strong> Acompanhe a ocupação em tempo real, capacidade máxima das salas e equilíbrio de gênero (meninos vs. meninas).</li>';
        $html .= '<li><strong>Alunos Não Ensalados:</strong> Lista de estudantes matriculados aguardando definição de turma.</li>';

        if ($canManage) {
            $html .= '<li><strong>Distribuição Automática Inteligente:</strong> Algoritmo que divide os alunos entre as turmas equilibrando quantidade de meninos e meninas ou por ordem alfabética.</li>';
            $html .= '<li><strong>Alocação em Lote:</strong> Selecione vários alunos com checkboxes e coloque-os em uma turma com 1 clique.</li>';
            $html .= '<li><strong>Remanejamento Rápido:</strong> Mova alunos entre turmas ou desensale-os a qualquer momento.</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    public function updatedCursoId($value): void
    {
        $this->serieId = Serie::where('curso_id', $value)->first()?->id;
        $this->selecionados = [];
    }

    public function updatedSerieId(): void
    {
        $this->selecionados = [];
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

    public function getAlunosNaoEnsaladosProperty(): Collection
    {
        if (! $this->periodoLetivoId || ! $this->serieId) {
            return collect();
        }

        return app(EnsalamentoService::class)->obterAlunosNaoEnsalados(
            $this->periodoLetivoId,
            $this->serieId,
            $this->turnoId
        );
    }

    public function alocarAlunosEmTurma(array $matriculaIds, int $turmaId): void
    {
        try {
            app(EnsalamentoService::class)->alocarAlunosEmTurma($matriculaIds, $turmaId);

            $this->selecionados = array_values(array_diff($this->selecionados, $matriculaIds));

            Notification::make()
                ->title('Ensalamento Realizado!')
                ->body(count($matriculaIds).' estudante(s) alocado(s) na turma com sucesso.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao Alocar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function alocarSelecionados(): void
    {
        if (empty($this->selecionados)) {
            Notification::make()->title('Selecione pelo menos um estudante.')->warning()->send();

            return;
        }

        if (! $this->turmaDestinoManualId) {
            Notification::make()->title('Selecione a turma de destino.')->warning()->send();

            return;
        }

        $this->alocarAlunosEmTurma($this->selecionados, $this->turmaDestinoManualId);
        $this->turmaDestinoManualId = null;
    }

    public function abrirModalDistribuicao(): void
    {
        if ($this->turmasCenario->isEmpty()) {
            Notification::make()
                ->title('Nenhuma turma cadastrada')
                ->body('Cadastre turmas para esta série antes de executar a distribuição automática.')
                ->warning()
                ->send();

            return;
        }

        $this->turmasSelecionadasDistribuicao = $this->turmasCenario->pluck('id')->toArray();
        $this->criterioDistribuicao = 'equilibrio_genero';
        $this->redistribuirTodos = false;
        $this->respeitarLimiteVagas = true;
        $this->showModalDistribuicao = true;
    }

    public function executarDistribuicaoAutomatica(): void
    {
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
                $this->redistribuirTodos,
                $this->respeitarLimiteVagas
            );

            $this->showModalDistribuicao = false;
            $this->selecionados = [];

            Notification::make()
                ->title('Distribuição Concluída!')
                ->body("Total de {$resultado['total_distribuidos']} estudante(s) distribuído(s) harmonicamente.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro na Distribuição')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function abrirModalMover(int $matriculaId, string $nomeAluno): void
    {
        $this->matriculaMoverId = $matriculaId;
        $this->alunoMoverNome = $nomeAluno;
        $this->novaTurmaId = null;
        $this->showModalMover = true;
    }

    public function confirmarMover(): void
    {
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

    public function desensalar(int $matriculaId): void
    {
        app(EnsalamentoService::class)->removerDeTurma([$matriculaId]);

        Notification::make()
            ->title('Estudante Desensalado')
            ->body('O estudante foi movido para a lista de aguardando ensalamento.')
            ->success()
            ->send();
    }
}
