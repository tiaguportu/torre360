<?php

namespace App\Filament\Resources\TemplateContratos\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TemplateContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('TemplateTabs')
                    ->tabs([
                        Tab::make('Informações')
                            ->schema([
                                Section::make('Informações do Template')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do Template')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('versao')
                                            ->label('Versão do Template')
                                            ->options([
                                                1 => 'Versão 1 - Editor Visual (TinyMCE)',
                                                2 => 'Versão 2 - Upload de Arquivo .odt (LibreOffice)',
                                            ])
                                            ->required()
                                            ->default(1)
                                            ->live(),
                                        Toggle::make('is_padrao')
                                            ->label('Template Padrão')
                                            ->helperText('Se marcado, este template será usado automaticamente para novos contratos.')
                                            ->default(false),
                                    ])->columns(3),

                                Section::make('Arquivo de Template ODT')
                                    ->schema([
                                        FileUpload::make('arquivo_odt')
                                            ->label('Arquivo de Template (.odt)')
                                            ->acceptedFileTypes(['application/vnd.oasis.opendocument.text'])
                                            ->directory('contratos/templates')
                                            ->visibility('private')
                                            ->required(fn ($get) => $get('versao') == 2)
                                            ->helperText('Faça o upload do arquivo .odt contendo as variáveis de substituição.'),
                                    ])
                                    ->visible(fn ($get) => $get('versao') == 2),

                                Section::make('Variáveis e Instruções')
                                    ->schema([
                                        Placeholder::make('ajuda_blade')
                                            ->label('Ajuda do Editor e Variáveis')
                                            ->columnSpanFull()
                                            ->content(function ($get) {
                                                $versao = $get('versao') ?? 1;

                                                if ($versao == 2) {
                                                    $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm bg-gray-50 dark:bg-gray-900/50 p-5 rounded-lg border border-gray-100 dark:border-gray-800">';
                                                    $html .= '<div>';
                                                    $html .= '<h4 class="font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2"><svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg> Variáveis no ODT</h4>';
                                                    $html .= '<p class="text-gray-600 dark:text-gray-400 mb-2 leading-relaxed">No seu editor externo (ex: LibreOffice), escreva as variáveis exatamente nos locais que deseja preencher. O sistema substituirá as chaves pelo valor correspondente:</p>';
                                                    $html .= '<ul class="space-y-1 text-xs font-mono text-gray-600 dark:text-gray-400">';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $aluno->nome }}</code> ou <code class="text-primary-600 dark:text-primary-400 font-bold">${aluno.nome}</code> - Nome do aluno</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $aluno->cpf }}</code> ou <code class="text-primary-600 dark:text-primary-400 font-bold">${aluno.cpf}</code> - CPF do aluno</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $responsavel->nome }}</code> ou <code class="text-primary-600 dark:text-primary-400 font-bold">${responsavel.nome}</code> - Nome do Responsável</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $responsavel->cpf }}</code> ou <code class="text-primary-600 dark:text-primary-400 font-bold">${responsavel.cpf}</code> - CPF do Responsável</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $unidade->nome }}</code> ou <code class="text-primary-600 dark:text-primary-400 font-bold">${unidade.nome}</code> - Unidade de Ensino</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $contrato->valor_total }}</code> ou <code class="text-primary-600 dark:text-primary-400 font-bold">${contrato.valor_total}</code> - Valor do Contrato</li>';
                                                    $html .= '</ul>';
                                                    $html .= '</div>';
                                                    $html .= '<div>';
                                                    $html .= '<h4 class="font-bold text-gray-900 dark:text-white mb-2">Tabela Dinâmica de Faturas</h4>';
                                                    $html .= '<p class="text-gray-600 dark:text-gray-400 mb-2 leading-relaxed">Para criar uma tabela de faturas no ODT que se repita para cada parcela:</p>';
                                                    $html .= '<p class="text-gray-600 dark:text-gray-400 mb-2 text-xs">Desenhe uma tabela no ODT e crie uma linha de dados contendo exatamente os placeholders abaixo. O sistema duplicará essa linha de tabela automaticamente:</p>';
                                                    $html .= '<ul class="space-y-1.5 text-gray-600 dark:text-gray-400 font-mono text-xs">';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">[fatura.parcela]</code> - Número da parcela</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">[fatura.vencimento]</code> - Data de vencimento</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">[fatura.valor]</code> - Valor com Desconto</li>';
                                                    $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">[fatura.valor_original]</code> - Valor Original (Bruto)</li>';
                                                    $html .= '</ul>';
                                                    $html .= '</div>';
                                                    $html .= '</div>';

                                                    return new HtmlString($html);
                                                }

                                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm bg-gray-50 dark:bg-gray-900/50 p-5 rounded-lg border border-gray-100 dark:border-gray-800">';
                                                $html .= '<div>';
                                                $html .= '<h4 class="font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2"><svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg> Macros Customizáveis (Sem Código Fonte)</h4>';
                                                $html .= '<p class="text-gray-600 dark:text-gray-400 mb-2 leading-relaxed">Para desenhar tabelas prontas ou linhas de assinatura diretamente pelo editor visual, digite as macros abaixo exatamente como texto plano. Os templates delas podem ser editados no menu <strong>Configurações</strong> do painel:</p>';
                                                $html .= '<ul class="space-y-1 text-xs font-mono text-gray-600 dark:text-gray-400">';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $tabelaFatura !!}}</code> - Tabela de faturas</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $tabelaAluno !!}}</code> - Tabela com dados do Aluno</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $assinaturasResponsaveis !!}}</code> - Assinatura genérica dos responsáveis</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $assinaturaPai !!}}</code> - Assinatura do Pai</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $assinaturaMae !!}}</code> - Assinatura da Mãe</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $assinaturaResponsavelFinanceiro !!}}</code> - Assinatura do Responsável Financeiro</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{!! $assinaturaResponsavelLegalUnidade !!}}</code> - Assinatura do Resp. Legal da Unidade</li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';
                                                $html .= '<div>';
                                                $html .= '<h4 class="font-bold text-gray-900 dark:text-white mb-2">Principais Atributos e Variáveis</h4>';
                                                $html .= '<ul class="space-y-1.5 text-gray-600 dark:text-gray-400 font-mono text-xs">';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $aluno->nome }}</code> - Nome do aluno</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $aluno->cpf }}</code> - CPF do aluno</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $contrato->matricula->turma->nome }}</code> - Turma do aluno</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $contrato->matricula->turma->serie->nome }}</code> - Série do aluno</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $unidade->nome }}</code> - Unidade de Ensino</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{PAGINA_ATUAL}</code> - Página atual (exclusivo para cabeçalho/rodapé)</li>';
                                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{TOTAL_PAGINAS}</code> - Total de páginas (exclusivo para cabeçalho/rodapé)</li>';
                                                $html .= '</ul>';
                                                $html .= '</div>';
                                                $html .= '</div>';

                                                return new HtmlString($html);
                                            }),
                                    ]),
                            ]),
                        Tab::make('Cabeçalho')
                            ->visible(fn ($get) => ($get('versao') ?? 1) == 1)
                            ->schema([
                                TinyEditor::make('cabecalho')
                                    ->label('Cabeçalho do Contrato')
                                    ->helperText('Variáveis disponíveis: {PAGINA_ATUAL} ou {PAGE_NUM} para página atual, {TOTAL_PAGINAS} ou {PAGE_COUNT} para total de páginas.')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Conteúdo Principal')
                            ->visible(fn ($get) => ($get('versao') ?? 1) == 1)
                            ->schema([
                                TinyEditor::make('conteudo')
                                    ->label('Conteúdo do Contrato')
                                    ->required(fn ($get) => ($get('versao') ?? 1) == 1)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Rodapé')
                            ->visible(fn ($get) => ($get('versao') ?? 1) == 1)
                            ->schema([
                                TinyEditor::make('rodape')
                                    ->label('Rodapé do Contrato')
                                    ->helperText('Variáveis disponíveis: {PAGINA_ATUAL} ou {PAGE_NUM} para página atual, {TOTAL_PAGINAS} ou {PAGE_COUNT} para total de páginas.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
