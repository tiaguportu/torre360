<?php

namespace App\Filament\Resources\Livros\Pages;

use App\Filament\Resources\Livros\LivroResource;
use App\Models\VideoTutorial;
use App\Services\LivroLookupService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLivro extends CreateRecord
{
    protected static string $resource = LivroResource::class;

    /**
     * Obra nova: todos os exemplares nascem disponíveis (antes a "Quantidade Disponível" tinha padrão 1,
     * independente do total, e cadastrar 10 exemplares deixava só 1 emprestável).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['quantidade_disponivel'] = (int) ($data['quantidade_total'] ?? 1);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buscarIsbn')
                ->label('Buscar por ISBN')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->modalHeading('Consultar Livro por ISBN')
                ->modalDescription('Digite o código ISBN para importar dados completos do livro e foto da capa automaticamente.')
                ->schema([
                    TextInput::make('isbn_busca')
                        ->label('ISBN (10 ou 13 dígitos)')
                        ->placeholder('Ex: 9788576082675')
                        ->required(),
                ])
                ->action(function (array $data, LivroLookupService $service) {
                    $resultado = $service->buscarPorIsbn($data['isbn_busca']);

                    if (! $resultado['sucesso']) {
                        Notification::make()
                            ->title('Livro não encontrado')
                            ->body($resultado['mensagem'])
                            ->warning()
                            ->send();

                        return;
                    }

                    $dados = $resultado['dados'];
                    $preenchimento = array_filter([
                        'isbn' => $dados['isbn'],
                        'titulo' => $dados['titulo'],
                        'autor' => $dados['autor'],
                        'editora' => $dados['editora'],
                        'categoria' => $dados['categoria'],
                        'capa' => $dados['capa'],
                    ], fn ($v) => filled($v));

                    $this->form->fill(array_merge($this->data ?? [], $preenchimento));

                    $detalheCapa = ! empty($dados['capa']) ? ' com foto da capa!' : '!';
                    Notification::make()
                        ->title('Livro encontrado!')
                        ->body("Dados de \"{$dados['titulo']}\" preenchidos com sucesso{$detalheCapa}")
                        ->success()
                        ->send();
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Livro')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'livros-create')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta página permite realizar o cadastro de novos livros no acervo da biblioteca escolar.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">🔍 Busca Automática por ISBN:</h4>';
        $html .= '<p>Você pode utilizar o botão <strong>"Buscar por ISBN"</strong> no cabeçalho ou o ícone de lupa no campo <em>ISBN</em>. O sistema consultará automaticamente bases públicas (Open Library, BrasilAPI e Google Books), preenchendo:</p>';
        $html .= '<ul class="list-disc pl-5 mt-1 space-y-0.5">';
        $html .= '<li>Título da obra</li>';
        $html .= '<li>Autor(es)</li>';
        $html .= '<li>Editora</li>';
        $html .= '<li>Categoria / Assunto</li>';
        $html .= '<li>Foto da capa em alta resolução (salva automaticamente no acervo)</li>';
        $html .= '</ul>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📸 Foto da Capa:</h4>';
        $html .= '<p>Você pode carregar uma imagem manualmente clicando na área <em>Foto da Capa</em> ou deixar que a busca por ISBN faça o download automático da capa oficial.</p>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📦 Controle de Exemplares:</h4>';
        $html .= '<p>Defina a <strong>Quantidade Total</strong> de exemplares físicos que a escola possui e a <strong>Quantidade Disponível</strong> para novos empréstimos.</p>';
        $html .= '</div>';

        if ($user?->can('Create:Livro')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para cadastrar novos livros no acervo.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
