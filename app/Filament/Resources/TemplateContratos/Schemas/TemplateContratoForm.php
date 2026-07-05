<?php

namespace App\Filament\Resources\TemplateContratos\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TemplateContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Template')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Template')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_padrao')
                            ->label('Template Padrão')
                            ->helperText('Se marcado, este template será usado automaticamente para novos contratos.')
                            ->default(false),
                    ])->columns(2)
                    ->columnSpanFull(),

                Section::make('Conteúdo do Contrato')
                    ->schema([
                        TinyEditor::make('conteudo')
                            ->label('Conteúdo do Contrato')
                            ->required()
                            ->columnSpanFull(),

                        Placeholder::make('ajuda_blade')
                            ->label('Ajuda do Editor e Variáveis')
                            ->columnSpanFull()
                            ->content(function () {
                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm bg-gray-50 dark:bg-gray-900/50 p-5 rounded-lg border border-gray-100 dark:border-gray-800">';
                                $html .= '<div>';
                                $html .= '<h4 class="font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2"><svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg> Como usar o Editor</h4>';
                                $html .= '<p class="text-gray-600 dark:text-gray-400 mb-3 leading-relaxed">Para criar <strong>tabelas dinâmicas, loops (@foreach) ou condições (@if)</strong>, clique no botão de <strong>Código Fonte (ícone &lt;&gt; ou no menu Ferramentas)</strong> na barra do editor acima e insira o HTML bruto de sua preferência.</p>';
                                $html .= '</div>';
                                $html .= '<div>';
                                $html .= '<h4 class="font-bold text-gray-900 dark:text-white mb-2">Principais Atributos e Variáveis</h4>';
                                $html .= '<ul class="space-y-1.5 text-gray-600 dark:text-gray-400 font-mono text-xs">';
                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $aluno->nome }}</code> - Nome do aluno</li>';
                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $aluno->cpf }}</code> - CPF do aluno</li>';
                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $contrato->matricula->turma->nome }}</code> - Turma do aluno</li>';
                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $contrato->matricula->turma->serie->nome }}</code> - Série do aluno</li>';
                                $html .= '<li><code class="text-primary-600 dark:text-primary-400 font-bold">{{ $unidade->nome }}</code> - Unidade de Ensino</li>';
                                $html .= '</ul>';
                                $html .= '</div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])->columnSpanFull(),
            ]);
    }
}
