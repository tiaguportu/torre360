<?php

namespace App\Filament\Resources\Inventarios\Pages;

use App\Enums\StatusEmprestimo;
use App\Filament\Resources\Inventarios\InventarioResource;
use App\Models\Emprestimo;
use App\Models\InventarioAcervo;
use App\Models\InventarioItem;
use App\Models\Livro;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class ConferenciaInventario extends Page
{
    protected static string $resource = InventarioResource::class;

    protected string $view = 'filament.pages.conferencia-inventario';

    protected static ?string $title = 'Conferência e Bipagem de Acervo';

    public InventarioAcervo $record;

    public ?string $codigo_bipado = null;

    public function mount(InventarioAcervo $record): void
    {
        $this->record = $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('concluir')
                ->label('Concluir Auditoria')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->record->isAberto())
                ->requiresConfirmation()
                ->modalHeading('Concluir Inventário de Acervo?')
                ->modalDescription('A auditoria será marcada como finalizada com a data de hoje. Você ainda poderá consultar o relatório a qualquer momento.')
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'concluido',
                        'data_fim' => now()->toDateString(),
                    ]);

                    Notification::make()
                        ->title('Inventário concluído com sucesso!')
                        ->success()
                        ->send();
                }),

            Action::make('reabrir')
                ->label('Reabrir Auditoria')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->isConcluido())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'em_andamento',
                        'data_fim' => null,
                    ]);

                    Notification::make()
                        ->title('Inventário reaberto para novas bipagens!')
                        ->warning()
                        ->send();
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Conferência e Inventário')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'inventario-conferencia')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta tela permite realizar a auditoria física do acervo passando o leitor de código de barras nas estantes da biblioteca.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📟 Como Auditar:</h4>';
        $html .= '<p>Com o leitor de código de barras conectado (ou digitando manualmente), aponte para o código de barras, tombo ou ISBN de cada livro. O sistema confirma a presença física imediatamente e atualiza os contadores em tempo real.</p>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">🔍 Detecção de Divergências:</h4>';
        $html .= '<p>A tabela de <strong>Livros Faltantes</strong> lista automaticamente todos os livros cadastrados na escola que não foram encontrados nas estantes e também não constam como emprestados a nenhum aluno no momento.</p>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📱 Leitura com Celular nas Estantes:</h4>';
        $html .= '<p>Você pode caminhar pelas prateleiras usando seu smartphone. Clique no botão de câmera para escanear os livros em sequência com o modo de leitura contínua ativado.</p>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Processa o código do livro lido através da câmera do celular durante o inventário.
     */
    public function processarLeituraInventario(string $codigo): void
    {
        $this->codigo_bipado = $codigo;
        $this->biparLivro();
    }

    /**
     * Bipa o código do livro na auditoria física.
     */
    public function biparLivro(): void
    {
        if (! $this->record->isAberto()) {
            Notification::make()
                ->title('Auditoria finalizada')
                ->body('Este inventário já foi concluído. Reabra-o para bipar novos livros.')
                ->warning()
                ->send();

            return;
        }

        $codigo = trim((string) $this->codigo_bipado);
        if (blank($codigo)) {
            Notification::make()
                ->title('Código não informado')
                ->body('Bipe o código de barras ou ISBN do livro.')
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
                ->title('Obra não cadastrada')
                ->body("Nenhum livro cadastrado no sistema com o código \"{$codigo}\".")
                ->danger()
                ->send();

            return;
        }

        $jaConferido = InventarioItem::where('inventario_id', $this->record->id)
            ->where('livro_id', $livro->id)
            ->first();

        if ($jaConferido) {
            $jaConferido->increment('quantidade_conferida');
            $this->codigo_bipado = null;

            Notification::make()
                ->title('Exemplar adicional registrado!')
                ->body("\"{$livro->titulo}\" já estava conferido. Contagem atualizada para {$jaConferido->quantidade_conferida} exemplar(es).")
                ->info()
                ->send();

            return;
        }

        InventarioItem::create([
            'inventario_id' => $this->record->id,
            'livro_id' => $livro->id,
            'bipado_em' => now(),
            'quantidade_conferida' => 1,
            'user_id' => auth()->id(),
        ]);

        $this->codigo_bipado = null;

        Notification::make()
            ->title('Livro conferido com sucesso!')
            ->body("\"{$livro->titulo}\" registrado na auditoria!")
            ->success()
            ->send();
    }

    public function getTotalAcervoProperty(): int
    {
        return Livro::count();
    }

    public function getTotalConferidosProperty(): int
    {
        return InventarioItem::where('inventario_id', $this->record->id)->count();
    }

    public function getTotalEmprestadosProperty(): int
    {
        return Emprestimo::whereIn('status', [StatusEmprestimo::Emprestado, StatusEmprestimo::Atrasado])
            ->distinct('livro_id')
            ->count('livro_id');
    }

    public function getLivrosFaltantesProperty(): Collection
    {
        $conferidosIds = InventarioItem::where('inventario_id', $this->record->id)->pluck('livro_id')->toArray();
        $emprestadosIds = Emprestimo::whereIn('status', [StatusEmprestimo::Emprestado, StatusEmprestimo::Atrasado])->pluck('livro_id')->toArray();

        $excluirIds = array_unique(array_merge($conferidosIds, $emprestadosIds));

        return Livro::whereNotIn('id', $excluirIds)->orderBy('titulo')->get();
    }

    public function getUltimosBipadosProperty(): Collection
    {
        return InventarioItem::where('inventario_id', $this->record->id)
            ->with('livro')
            ->latest('bipado_em')
            ->limit(10)
            ->get();
    }
}
