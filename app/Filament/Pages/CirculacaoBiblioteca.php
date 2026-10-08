<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusEmprestimo;
use App\Models\Emprestimo;
use App\Models\Livro;
use App\Models\Matricula;
use App\Models\VideoTutorial;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class CirculacaoBiblioteca extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static \UnitEnum|string|null $navigationGroup = 'Biblioteca';

    protected static ?string $navigationLabel = 'Balcão de Circulação (Rápido)';

    protected static ?string $title = 'Balcão de Circulação Ágil';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.circulacao-biblioteca';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'secretaria']) || $user->can('Create:Emprestimo');
    }

    // Estado do Empréstimo Rápido
    public ?int $matricula_id = null;

    public ?string $codigo_livro_emprestimo = null;

    public ?string $data_prevista_devolucao = null;

    // Estado da Devolução Rápida
    public ?string $codigo_livro_devolucao = null;

    // Histórico de operações da sessão
    public array $historicoSessao = [];

    public function mount(): void
    {
        $this->data_prevista_devolucao = now()->addDays(14)->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Balcão de Circulação Ágil')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'biblioteca-circulacao')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>O <strong>Balcão de Circulação Ágil</strong> foi projetado para agilizar o atendimento de balcão na biblioteca escolar, eliminando cliques desnecessários.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">⚡ Empréstimo Rápido:</h4>';
        $html .= '<p>Como o estudante não possui crachá, digite o nome ou número de matrícula do aluno na busca rápida. Em seguida, basta bipar com o leitor óptico o código de barras ou ISBN do livro. O empréstimo é registrado instantaneamente.</p>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📦 Devolução em 1 Bip:</h4>';
        $html .= '<p>No momento da entrega do livro, o bibliotecário não precisa procurar o aluno. Basta apontar o leitor de código de barras para o livro no campo de devolução e o sistema identifica o empréstimo ativo, dá baixa e recoloca a obra no acervo disponível na hora.</p>';
        $html .= '</div>';

        if ($user?->can('Create:Emprestimo')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para registrar empréstimos e devoluções.</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Executa o empréstimo rápido para o aluno selecionado.
     */
    public function realizarEmprestimo(): void
    {
        if (blank($this->matricula_id)) {
            Notification::make()
                ->title('Aluno não selecionado')
                ->body('Selecione um aluno antes de registrar o empréstimo.')
                ->warning()
                ->send();

            return;
        }

        $codigo = trim((string) $this->codigo_livro_emprestimo);
        if (blank($codigo)) {
            Notification::make()
                ->title('Código do livro não informado')
                ->body('Bipe ou digite o código de barras, tombo ou ISBN do livro.')
                ->warning()
                ->send();

            return;
        }

        // Localiza o livro pelo código de tombo, ISBN ou ID
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $codigo);
        $livro = Livro::query()
            ->where('codigo', $codigo)
            ->orWhere('isbn', $codigo)
            ->orWhere('isbn', $cleanIsbn)
            ->orWhere('id', $codigo)
            ->first();

        if (! $livro) {
            Notification::make()
                ->title('Livro não encontrado')
                ->body("Nenhuma obra localizada com o código \"{$codigo}\".")
                ->danger()
                ->send();

            return;
        }

        if (! $livro->temExemplarDisponivel()) {
            Notification::make()
                ->title('Exemplar indisponível')
                ->body("Todos os exemplares de \"{$livro->titulo}\" estão emprestados no momento.")
                ->danger()
                ->send();

            return;
        }

        $matricula = Matricula::with('pessoa')->find($this->matricula_id);
        if (! $matricula) {
            Notification::make()
                ->title('Matrícula inválida')
                ->danger()
                ->send();

            return;
        }

        $dataPrevista = $this->data_prevista_devolucao ?: now()->addDays(14)->format('Y-m-d');

        $emprestimo = Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->toDateString(),
            'data_prevista_devolucao' => $dataPrevista,
            'status' => StatusEmprestimo::Emprestado,
        ]);

        $livro->decrement('quantidade_disponivel');

        $this->codigo_livro_emprestimo = null;

        array_unshift($this->historicoSessao, [
            'tipo' => 'emprestimo',
            'livro' => $livro->titulo,
            'aluno' => $matricula->pessoa->nome,
            'data' => now()->format('H:i:s'),
            'devolucao' => Carbon::parse($dataPrevista)->format('d/m/Y'),
        ]);

        Notification::make()
            ->title('Empréstimo registrado com sucesso!')
            ->body("\"{$livro->titulo}\" emprestado para {$matricula->pessoa->nome}. Previsão: ".Carbon::parse($dataPrevista)->format('d/m/Y'))
            ->success()
            ->send();
    }

    /**
     * Executa a devolução rápida do livro apenas pelo código bipado.
     */
    public function realizarDevolucao(): void
    {
        $codigo = trim((string) $this->codigo_livro_devolucao);
        if (blank($codigo)) {
            Notification::make()
                ->title('Código do livro não informado')
                ->body('Bipe o código de barras, tombo ou ISBN do livro entregue.')
                ->warning()
                ->send();

            return;
        }

        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $codigo);
        $livro = Livro::query()
            ->where('codigo', $codigo)
            ->orWhere('isbn', $codigo)
            ->orWhere('isbn', $cleanIsbn)
            ->orWhere('id', $codigo)
            ->first();

        if (! $livro) {
            Notification::make()
                ->title('Livro não localizado')
                ->body("Nenhuma obra encontrada para o código \"{$codigo}\".")
                ->danger()
                ->send();

            return;
        }

        // Busca o empréstimo ativo mais antigo deste livro
        $emprestimo = Emprestimo::query()
            ->where('livro_id', $livro->id)
            ->whereIn('status', [StatusEmprestimo::Emprestado, StatusEmprestimo::Atrasado])
            ->with(['matricula.pessoa'])
            ->orderBy('data_emprestimo')
            ->first();

        if (! $emprestimo) {
            Notification::make()
                ->title('Nenhum empréstimo pendente')
                ->body("A obra \"{$livro->titulo}\" não consta como emprestada no sistema.")
                ->warning()
                ->send();

            return;
        }

        $nomeAluno = $emprestimo->matricula->pessoa->nome ?? 'Aluno';
        $emprestimo->registrarDevolucao();

        $this->codigo_livro_devolucao = null;

        array_unshift($this->historicoSessao, [
            'tipo' => 'devolucao',
            'livro' => $livro->titulo,
            'aluno' => $nomeAluno,
            'data' => now()->format('H:i:s'),
        ]);

        Notification::make()
            ->title('Devolução registrada com sucesso!')
            ->body("\"{$livro->titulo}\" devolvido por {$nomeAluno}. Exemplar recolocado no acervo.")
            ->success()
            ->send();
    }

    /**
     * Retorna os livros atualmente com o aluno selecionado.
     */
    public function getLivrosEmprestadosComAlunoProperty(): Collection
    {
        if (blank($this->matricula_id)) {
            return collect();
        }

        return Emprestimo::query()
            ->where('matricula_id', $this->matricula_id)
            ->whereIn('status', [StatusEmprestimo::Emprestado, StatusEmprestimo::Atrasado])
            ->with('livro')
            ->orderBy('data_prevista_devolucao')
            ->get();
    }

    /**
     * Opções de alunos para o campo Select com dados de turma.
     */
    public function getAlunosOptionsProperty(): array
    {
        return Matricula::query()
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->with(['pessoa', 'turma'])
            ->get()
            ->mapWithKeys(function (Matricula $m) {
                $turmaNome = $m->turma ? " ({$m->turma->nome})" : '';

                return [$m->id => "{$m->pessoa->nome}{$turmaNome} - Matrícula #{$m->id}"];
            })
            ->toArray();
    }
}
