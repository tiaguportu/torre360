<?php

namespace App\Filament\Resources\TemplateDocumentos\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Enums\TipoTemplateDocumento;
use App\Services\Documentos\MacrosDocumentoService;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TemplateDocumentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('TemplateDocumentoTabs')
                    ->tabs([
                        Tab::make('Identificação e Configurações')
                            ->schema([
                                Section::make('Dados Básicos')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do Modelo')
                                            ->placeholder('Ex: Declaração de Matrícula Regular')
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('tipo')
                                            ->label('Tipo de Documento')
                                            ->options(TipoTemplateDocumento::class)
                                            ->default(TipoTemplateDocumento::DeclaracaoMatricula)
                                            ->required()
                                            ->live()
                                            ->helperText(fn (Get $get): string => 'Os dados do documento vêm de: '.(self::tipo($get('tipo'))?->contexto()->getLabel() ?? '—').'. As variáveis abaixo mudam conforme o tipo.'),

                                        TextInput::make('validade_dias')
                                            ->label('Validade em Dias')
                                            ->numeric()
                                            ->default(30)
                                            ->required()
                                            ->helperText('Só vale para a declaração emitida com QR Code: período em que ela é considerada válida para autenticação online. Para documentos que vão para assinatura eletrônica, o valor é ignorado.'),

                                        Toggle::make('is_ativo')
                                            ->label('Modelo Ativo')
                                            ->default(true)
                                            ->helperText('Se desmarcado, não estará disponível para novas emissões nem para assinatura.'),

                                        Textarea::make('descricao')
                                            ->label('Descrição / Finalidade do Documento')
                                            ->placeholder('Ex: Documento para comprovação perante empresas, clubes, órgãos previdenciários e bancos.')
                                            ->columnSpanFull()
                                            ->rows(2),
                                    ])->columns(2),

                                Section::make('Guia de Variáveis Dinâmicas (Macros)')
                                    ->schema([
                                        Placeholder::make('ajuda_macros')
                                            ->label('')
                                            ->columnSpanFull()
                                            ->content(fn (Get $get) => view('filament.components.guia-variaveis', [
                                                'catalogo' => app(MacrosDocumentoService::class)->catalogo(
                                                    (self::tipo($get('tipo')) ?? TipoTemplateDocumento::DeclaracaoMatricula)->contexto(),
                                                ),
                                            ])),
                                    ]),
                            ]),

                        Tab::make('Conteúdo da Declaração')
                            ->schema([
                                TinyEditor::make('conteudo')
                                    ->label('Texto Principal do Documento')
                                    ->helperText('Utilize o editor para formatar o texto oficial. Na declaração emitida, o carimbo com QR Code e as informações de verificação entram automaticamente no rodapé. Use "Prévia", no alto da página, para ver o documento com os dados de um registro.')
                                    ->required()
                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        $tipo = self::tipo($get('tipo'));

                                        if (! $tipo) {
                                            return;
                                        }

                                        $desconhecidas = app(MacrosDocumentoService::class)->desconhecidas(is_string($value) ? $value : null, $tipo->contexto());

                                        if ($desconhecidas !== []) {
                                            $fail('Estas variáveis não existem para "'.$tipo->getLabel().'" e sairiam impressas no documento: '.implode(', ', $desconhecidas).'. Use só as do guia, escritas sem espaços dentro das chaves.');
                                        }
                                    })
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Cabeçalho Personalizado (Opcional)')
                            ->schema([
                                TinyEditor::make('cabecalho')
                                    ->label('Cabeçalho Customizado')
                                    ->helperText('Caso preenchido, complementa o timbre institucional da escola no topo da página.')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Rodapé Personalizado (Opcional)')
                            ->schema([
                                TinyEditor::make('rodape')
                                    ->label('Rodapé Customizado')
                                    ->helperText('Texto adicional ao final do documento antes do carimbo de autenticidade.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * O estado de um Select com enum pode chegar como o enum ou como o texto.
     */
    public static function tipo(mixed $valor): ?TipoTemplateDocumento
    {
        return $valor instanceof TipoTemplateDocumento ? $valor : TipoTemplateDocumento::tryFrom((string) $valor);
    }
}
