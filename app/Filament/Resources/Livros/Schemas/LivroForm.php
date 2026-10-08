<?php

namespace App\Filament\Resources\Livros\Schemas;

use App\Services\LivroLookupService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LivroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Livro')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('codigo')
                                    ->label('Código de Tombo / Acervo')
                                    ->placeholder('Ex: LIV-00001 (Automático)')
                                    ->maxLength(50)
                                    ->helperText('Identificador do exemplar para leitor de código de barras. Gerado automaticamente se vazio.')
                                    ->columnSpan(1),
                                TextInput::make('isbn')
                                    ->label('ISBN')
                                    ->placeholder('Ex: 9788576082675')
                                    ->maxLength(255)
                                    ->helperText('Digite o ISBN e clique na lupa para buscar título, autor, editora e foto da capa.')
                                    ->columnSpan(2)
                                    ->suffixAction(
                                        Action::make('buscarPorIsbn')
                                            ->label('Buscar por ISBN')
                                            ->icon('heroicon-m-magnifying-glass')
                                            ->color('primary')
                                            ->tooltip('Buscar dados e foto da capa automaticamente pelo ISBN')
                                            ->action(function (Get $get, Set $set, LivroLookupService $service) {
                                                $isbn = (string) $get('isbn');
                                                if (blank($isbn)) {
                                                    Notification::make()
                                                        ->title('ISBN não informado')
                                                        ->body('Digite um código ISBN no campo antes de buscar.')
                                                        ->warning()
                                                        ->send();

                                                    return;
                                                }

                                                $resultado = $service->buscarPorIsbn($isbn);
                                                if (! $resultado['sucesso']) {
                                                    Notification::make()
                                                        ->title('Livro não encontrado')
                                                        ->body($resultado['mensagem'])
                                                        ->warning()
                                                        ->send();

                                                    return;
                                                }

                                                $dados = $resultado['dados'];
                                                if (! empty($dados['titulo'])) {
                                                    $set('titulo', $dados['titulo']);
                                                }
                                                if (! empty($dados['autor'])) {
                                                    $set('autor', $dados['autor']);
                                                }
                                                if (! empty($dados['editora'])) {
                                                    $set('editora', $dados['editora']);
                                                }
                                                if (! empty($dados['categoria'])) {
                                                    $set('categoria', $dados['categoria']);
                                                }
                                                if (! empty($dados['capa'])) {
                                                    $set('capa', $dados['capa']);
                                                }

                                                $detalheCapa = ! empty($dados['capa']) ? ' com foto da capa!' : '!';
                                                Notification::make()
                                                    ->title('Livro encontrado!')
                                                    ->body("Dados de \"{$dados['titulo']}\" preenchidos com sucesso{$detalheCapa}")
                                                    ->success()
                                                    ->send();
                                            })
                                    ),
                            ]),
                        FileUpload::make('capa')
                            ->label('Foto da Capa')
                            ->image()
                            ->directory('livros/capas')
                            ->disk('public')
                            ->visibility('public')
                            ->imageResizeMode('cover')
                            ->maxSize(5120)
                            ->imageEditor()
                            ->helperText('Envie uma foto da capa ou utilize a busca por ISBN para preencher automaticamente.')
                            ->columnSpan(['default' => 1, 'md' => 1]),
                        Grid::make(['default' => 1, 'md' => 2])
                            ->columnSpan(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('titulo')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('autor')
                                    ->label('Autor')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('editora')
                                    ->label('Editora')
                                    ->maxLength(255),
                                TextInput::make('categoria')
                                    ->label('Categoria / Gênero')
                                    ->placeholder('Ex: Literatura Infantil, Ficção, Ciências')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('faixa_etaria')
                                    ->label('Faixa Etária Recomendada')
                                    ->placeholder('Selecione a faixa etária...')
                                    ->options([
                                        'Livre' => 'Livre (Todas as Idades)',
                                        '0 a 3 anos' => '0 a 3 anos (Primeira Infância)',
                                        '4 a 6 anos' => '4 a 6 anos (Educação Infantil)',
                                        '7 a 9 anos' => '7 a 9 anos (Anos Iniciais / Alfabetização)',
                                        '10 a 12 anos' => '10 a 12 anos (Fundamental Anos Iniciais / Finais)',
                                        '13 a 15 anos' => '13 a 15 anos (Fundamental Anos Finais)',
                                        '16+ anos' => '16+ anos (Ensino Médio / Jovem Adulto)',
                                    ]),
                                Select::make('segmentos')
                                    ->label('Segmentos Escolares Indicados')
                                    ->multiple()
                                    ->placeholder('Selecione os segmentos...')
                                    ->options([
                                        'educacao_infantil' => 'Educação Infantil',
                                        'fundamental_1' => 'Ensino Fundamental I',
                                        'fundamental_2' => 'Ensino Fundamental II',
                                        'ensino_medio' => 'Ensino Médio',
                                    ]),
                                TextInput::make('quantidade_total')
                                    ->label('Quantidade Total de Exemplares')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('quantidade_disponivel')
                                    ->label('Quantidade Disponível')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(1)
                                    ->required()
                                    ->helperText('Diminui a cada empréstimo e volta ao normal quando o livro é devolvido.'),
                            ]),
                    ]),
            ]);
    }
}
