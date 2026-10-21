<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortfolioItems\Schemas;

use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\PortfolioItem;
use App\Support\EscopoProfessor;
use App\Support\OpcoesMatricula;
use App\Support\TiposArquivo;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PortfolioItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Evidência')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Aluno')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search): array => OpcoesMatricula::buscar($search, auth()->user()))
                            ->getOptionLabelUsing(fn ($value): ?string => OpcoesMatricula::rotuloPorId($value))
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! EscopoProfessor::podeVerMatricula(auth()->user(), Matricula::find($value))) {
                                        $fail('Você não tem acesso a este aluno.');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        TextInput::make('titulo')
                            ->label('Título')
                            ->placeholder('Ex.: Maquete do sistema solar')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('data_registro')
                            ->label('Data do registro')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        Textarea::make('descricao')
                            ->label('O que o aluno fez ou demonstrou')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Select::make('habilidades')
                            ->label('Habilidades que a evidência demonstra')
                            ->relationship('habilidades', 'nome')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Habilidade $h): string => ($h->codigo ? "[{$h->codigo}] " : '').$h->nome)
                            ->columnSpanFull(),
                    ]),
                Section::make('Arquivos')
                    ->description('Fotos, PDFs, áudios e vídeos curtos. Ficam em área privada e só são vistos por quem tem acesso a este aluno.')
                    ->schema([
                        Placeholder::make('anexos_atuais')
                            ->label('Arquivos já enviados')
                            ->content(function (?PortfolioItem $record): HtmlString {
                                if (! $record || $record->caminhosDosAnexos() === []) {
                                    return new HtmlString('<span>—</span>');
                                }

                                $links = collect($record->caminhosDosAnexos())->map(fn (string $caminho, int $i): string => sprintf(
                                    '<li><a href="%s" target="_blank" rel="noopener" class="text-primary-600 underline">%s</a></li>',
                                    e($record->urlDoAnexo($i)),
                                    e(basename($caminho)),
                                ))->implode('');

                                return new HtmlString("<ul class=\"list-disc pl-5\">{$links}</ul>");
                            })
                            ->visibleOn('edit'),
                        FileUpload::make('anexos')
                            ->label('Enviar arquivos')
                            ->multiple()
                            ->disk('local')
                            ->directory('portfolio')
                            ->visibility('private')
                            ->acceptedFileTypes(TiposArquivo::portfolio())
                            ->maxSize(20480)
                            ->maxFiles(10)
                            ->previewable(false)
                            ->downloadable(false)
                            ->openable(false)
                            ->helperText('Até 10 arquivos de 20 MB: imagem (JPG, PNG, WebP), PDF, áudio (MP3, WAV) ou vídeo MP4.'),
                    ]),
                Section::make('Compartilhamento com a família')
                    ->columns(2)
                    ->schema([
                        Toggle::make('mostra_outros_alunos')
                            ->label('Aparecem outros alunos (foto, vídeo ou áudio)')
                            ->live()
                            ->afterStateUpdated(fn (bool $state, Set $set) => $state ? $set('visivel_familia', false) : null)
                            ->helperText('Material com outros alunos nunca é compartilhado com a família de um aluno só, porque envolve a imagem de outras crianças.'),
                        Toggle::make('visivel_familia')
                            ->label('Compartilhar com a família no Portal')
                            ->disabled(fn (Get $get): bool => (bool) $get('mostra_outros_alunos'))
                            ->helperText('Só a família deste aluno vê o item.'),
                    ]),
            ]);
    }
}
