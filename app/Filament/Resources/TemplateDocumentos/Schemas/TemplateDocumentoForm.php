<?php

namespace App\Filament\Resources\TemplateDocumentos\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Enums\TipoTemplateDocumento;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                                            ->required(),

                                        TextInput::make('validade_dias')
                                            ->label('Validade em Dias')
                                            ->numeric()
                                            ->default(30)
                                            ->required()
                                            ->helperText('Período em que a certidão/declaração será considerada válida para autenticação online.'),

                                        Toggle::make('is_ativo')
                                            ->label('Modelo Ativo')
                                            ->default(true)
                                            ->helperText('Se desmarcado, não estará disponível para novas emissões.'),

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
                                            ->content(function () {
                                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg border border-slate-200 dark:border-slate-800">';
                                                $html .= '<div>';
                                                $html .= '<h5 class="font-bold text-slate-800 dark:text-slate-200 mb-1">Dados do Estudante:</h5>';
                                                $html .= '<ul class="space-y-1 font-mono text-slate-600 dark:text-slate-400">';
                                                $html .= '<li><code>{{ALUNO_NOME}}</code> — Nome completo</li>';
                                                $html .= '<li><code>{{ALUNO_CPF}}</code> — CPF formatado</li>';
                                                $html .= '<li><code>{{ALUNO_RG}}</code> — Identidade / RG</li>';
                                                $html .= '<li><code>{{ALUNO_NASCIMENTO}}</code> — Data de nascimento</li>';
                                                $html .= '<li><code>{{ALUNO_MAE}}</code> — Nome da Mãe</li>';
                                                $html .= '<li><code>{{ALUNO_PAI}}</code> — Nome do Pai</li>';
                                                $html .= '<li><code>{{ALUNO_ENDERECO}}</code> — Endereço residencial completo</li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';
                                                $html .= '<div>';
                                                $html .= '<h5 class="font-bold text-slate-800 dark:text-slate-200 mb-1">Dados Acadêmicos e Instituição:</h5>';
                                                $html .= '<ul class="space-y-1 font-mono text-slate-600 dark:text-slate-400">';
                                                $html .= '<li><code>{{MATRICULA_ID}}</code> — Código da matrícula</li>';
                                                $html .= '<li><code>{{CURSO_NOME}}</code> — Nome do curso</li>';
                                                $html .= '<li><code>{{SERIE_NOME}}</code> — Série / Ano escolar</li>';
                                                $html .= '<li><code>{{TURMA_NOME}}</code> — Turma</li>';
                                                $html .= '<li><code>{{TURNO_NOME}}</code> — Turno</li>';
                                                $html .= '<li><code>{{PERIODO_LETIVO}}</code> — Período letivo</li>';
                                                $html .= '<li><code>{{UNIDADE_NOME}}</code> — Unidade escolar</li>';
                                                $html .= '<li><code>{{TABELA_HISTORICO}}</code> — Grade curricular / disciplinas</li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';
                                                $html .= '</div>';

                                                return new HtmlString($html);
                                            }),
                                    ]),
                            ]),

                        Tab::make('Conteúdo da Declaração')
                            ->schema([
                                TinyEditor::make('conteudo')
                                    ->label('Texto Principal do Documento')
                                    ->helperText('Utilize o editor para formatar o texto oficial. O carimbo com QR Code e informações de verificação será inserido automaticamente no rodapé do documento.')
                                    ->required()
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
}
