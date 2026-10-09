<?php

namespace App\Filament\Resources\SacolasLeitura\Pages;

use App\Enums\StatusSacolaLeitura;
use App\Filament\Resources\SacolasLeitura\SacolaLeituraResource;
use App\Models\Livro;
use App\Models\SacolaLeitura;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class GerenciarSacolaLeitura extends Page
{
    protected static string $resource = SacolaLeituraResource::class;

    protected string $view = 'filament.pages.gerenciar-sacola-leitura';

    protected static ?string $title = 'Gerenciar e Bipar Sacola de Leitura';

    public SacolaLeitura $record;

    public ?string $codigo_livro_adicionar = null;

    public ?string $codigo_livro_devolver = null;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('Update:SacolaLeitura') ?? false;
    }

    public function mount(SacolaLeitura $record): void
    {
        abort_unless(auth()->user()?->can('Update:SacolaLeitura'), 403);
        $this->record = $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ficha')
                ->label('Ficha de Controle')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('biblioteca.sacolas.ficha', $this->record))
                ->openUrlInNewTab(),

            Action::make('devolver_todos')
                ->label('Devolver Sacola Completa')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== StatusSacolaLeitura::Devolvida && $this->record->totalPendentes() > 0)
                ->requiresConfirmation()
                ->modalHeading('Devolver todos os livros restantes?')
                ->modalDescription(fn (): string => "Todos os {$this->record->totalPendentes()} livro(s) pendentes retornarão imediatamente ao acervo disponível.")
                ->action(function (): void {
                    $this->record->devolverTodosItens();
                    $this->record->refresh();

                    Notification::make()
                        ->title('Sacola devolvida com sucesso!')
                        ->body('Todos os livros foram recolocados no acervo da biblioteca.')
                        ->success()
                        ->send();
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gerenciar Sacola de Leitura')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'sacola-leitura-gerenciar')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta tela permite gerenciar o ciclo de vida completo da sacola de leitura:</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">➕ Adicionar Livros à Sacola:</h4>';
        $html .= '<p>Bipe os livros no campo superior com leitor de código de barras ou use a câmera do celular. O exemplar é reservado automaticamente.</p>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📦 Devolução e Conferência:</h4>';
        $html .= '<p>Quando a professora devolver a sacola, bipe os livros retirados no campo de devolução. O sistema confere um a um e recoloca no acervo na hora.</p>';
        $html .= '</div>';

        if ($user?->can('Update:SacolaLeitura')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para editar e dar baixa nesta sacola de leitura.</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Adiciona um livro à sacola pelo código bipado.
     */
    public function adicionarLivroPorCodigo(): void
    {
        abort_unless(auth()->user()?->can('Update:SacolaLeitura'), 403);

        $codigo = trim((string) $this->codigo_livro_adicionar);
        if (blank($codigo)) {
            Notification::make()
                ->title('Código do livro não informado')
                ->body('Bipe o código de barras, tombo ou ISBN do livro para colocar na sacola.')
                ->warning()
                ->send();

            return;
        }

        $livro = $this->localizarLivroPorCodigo($codigo);

        if (! $livro) {
            Notification::make()
                ->title('Livro não localizado')
                ->body("Nenhuma obra encontrada para o código \"{$codigo}\".")
                ->danger()
                ->send();

            $this->dispatch('livro-processado', tipo: 'adicionar');

            return;
        }

        if (! $livro->temExemplarDisponivel()) {
            Notification::make()
                ->title('Exemplar indisponível')
                ->body("A obra \"{$livro->titulo}\" não possui exemplares disponíveis no acervo.")
                ->danger()
                ->send();

            $this->dispatch('livro-processado', tipo: 'adicionar');

            return;
        }

        try {
            $this->record->adicionarLivro($livro);
            $this->codigo_livro_adicionar = null;
            $this->record->refresh();

            Notification::make()
                ->title('Livro adicionado à sacola!')
                ->body("\"{$livro->titulo}\" foi incluído com sucesso na sacola.")
                ->success()
                ->send();

            $this->dispatch('livro-processado', tipo: 'adicionar');
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao adicionar')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->dispatch('livro-processado', tipo: 'adicionar');
        }
    }

    public function processarLeituraCameraAdicionar(string $codigo): void
    {
        $this->codigo_livro_adicionar = $codigo;
        $this->adicionarLivroPorCodigo();
    }

    /**
     * Confere e dá baixa num livro devolvido da sacola pelo código bipado.
     */
    public function devolverLivroPorCodigo(): void
    {
        abort_unless(auth()->user()?->can('Update:SacolaLeitura'), 403);

        $codigo = trim((string) $this->codigo_livro_devolver);
        if (blank($codigo)) {
            Notification::make()
                ->title('Código não informado')
                ->warning()
                ->send();

            $this->dispatch('livro-processado', tipo: 'devolver');

            return;
        }

        $livro = $this->localizarLivroPorCodigo($codigo);

        if (! $livro) {
            Notification::make()
                ->title('Livro não localizado no acervo')
                ->body("Nenhuma obra corresponde a \"{$codigo}\".")
                ->danger()
                ->send();

            $this->dispatch('livro-processado', tipo: 'devolver');

            return;
        }

        // Localiza o item pendente desta sacola
        $item = $this->record->itens()
            ->where('livro_id', $livro->id)
            ->where('devolvido', false)
            ->first();

        if (! $item) {
            Notification::make()
                ->title('Livro não consta como pendente nesta sacola')
                ->body("A obra \"{$livro->titulo}\" não está na lista de pendências desta sacola.")
                ->warning()
                ->send();

            $this->dispatch('livro-processado', tipo: 'devolver');

            return;
        }

        $this->record->devolverItem($item);
        $this->codigo_livro_devolver = null;
        $this->record->refresh();

        Notification::make()
            ->title('Livro devolvido ao acervo!')
            ->body("\"{$livro->titulo}\" conferido e devolvido com sucesso.")
            ->success()
            ->send();

        $this->dispatch('livro-processado', tipo: 'devolver');
    }

    private function localizarLivroPorCodigo(string $codigo): ?Livro
    {
        $cleanCodigo = trim($codigo);
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $cleanCodigo);

        return Livro::query()
            ->where('codigo', $cleanCodigo)
            ->orWhere('isbn', $cleanCodigo)
            ->when(filled($cleanIsbn), function ($q) use ($cleanIsbn) {
                $q->orWhere('isbn', $cleanIsbn)
                    ->orWhereRaw("REPLACE(REPLACE(isbn, '-', ''), ' ', '') = ?", [$cleanIsbn]);
            })
            ->when(is_numeric($cleanCodigo), fn ($q) => $q->orWhere('id', (int) $cleanCodigo))
            ->first();
    }

    public function processarLeituraCameraDevolver(string $codigo): void
    {
        $this->codigo_livro_devolver = $codigo;
        $this->devolverLivroPorCodigo();
    }

    /**
     * Dá baixa manual em um item da lista.
     */
    public function devolverItemIndividual(int $itemId): void
    {
        abort_unless(auth()->user()?->can('Update:SacolaLeitura'), 403);

        $item = $this->record->itens()->find($itemId);
        if (! $item || $item->devolvido) {
            return;
        }

        $this->record->devolverItem($item);
        $this->record->refresh();

        Notification::make()
            ->title('Livro devolvido!')
            ->body("\"{$item->livro->titulo}\" retornou ao acervo.")
            ->success()
            ->send();
    }

    /**
     * Remove livro da sacola antes da entrega ou inserido por engano.
     */
    public function removerItem(int $itemId): void
    {
        abort_unless(auth()->user()?->can('Update:SacolaLeitura'), 403);

        $item = $this->record->itens()->find($itemId);
        if (! $item) {
            return;
        }

        if (! $item->devolvido) {
            Livro::devolverExemplar((int) $item->livro_id);
        }

        $item->delete();
        $this->record->atualizarStatusAposDevolucao();
        $this->record->refresh();

        Notification::make()
            ->title('Livro removido da sacola')
            ->warning()
            ->send();
    }

    public function getItensSacolaProperty(): Collection
    {
        return $this->record->itens()
            ->with('livro')
            ->latest('id')
            ->get();
    }
}
