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

                Section::make('Conteúdo e Macros')
                    ->schema([
                        TinyEditor::make('conteudo')
                            ->label('Conteúdo do Contrato')
                            ->required()
                            ->columnSpanFull(),

                        Placeholder::make('macros_disponiveis')
                            ->label('Macros Disponíveis')
                            ->columnSpanFull()
                            ->content(function () {
                                $macros = [
                                    'CONTRATO.ID' => ['desc' => 'Identificador único do contrato.', 'ex' => '123'],
                                    'CONTRATO.VALOR' => ['desc' => 'Valor total do contrato.', 'ex' => 'R$ 1.500,00'],
                                    'CONTRATO.DATA' => ['desc' => 'Cidade e data atual por extenso.', 'ex' => 'Rio de Janeiro, 19 de Abril de 2026'],
                                    'UNIDADE.NOME' => ['desc' => 'Nome da unidade escolar.', 'ex' => 'Unidade Centro'],
                                    'UNIDADE.CNPJ' => ['desc' => 'CNPJ da unidade.', 'ex' => '00.000.000/0001-00'],
                                    'UNIDADE.REPRESENTANTES' => ['desc' => 'Qualificação dos representantes legais da unidade conforme vínculo.', 'ex' => 'seu Diretor(a), João da Silva'],
                                    'ALUNOS.TABELA' => ['desc' => 'Tabela com Nome do Aluno, Turma e Série/Ano.', 'ex' => '[Tabela Gerada]'],
                                    'RESPONSAVEIS.INFO' => ['desc' => 'Texto qualificando os responsáveis financeiros.', 'ex' => 'João da Silva, CPF 000..., residente em...'],
                                    'FATURAS.TABELA' => ['desc' => 'Tabela com Parcela, Vencimento, Valor Original e Valor com Desconto.', 'ex' => '[Tabela Gerada]'],
                                    'ASSINATURA.REPRESENTANTES' => ['desc' => 'Blocos de assinatura dos representantes legais da escola.', 'ex' => '[Blocos Gerados]'],
                                    'ASSINATURA.RESPONSAVEIS' => ['desc' => 'Blocos de assinatura dos responsáveis financeiros do contrato.', 'ex' => '[Blocos Gerados]'],
                                    'ASSINATURA.PAI' => ['desc' => 'Bloco de assinatura específico do Pai.', 'ex' => '[Bloco Gerado]'],
                                    'ASSINATURA.MAE' => ['desc' => 'Bloco de assinatura específico da Mãe.', 'ex' => '[Bloco Gerado]'],
                                ];

                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">';
                                foreach ($macros as $macro => $info) {
                                    $html .= '
                                        <div x-data="{ open: false }" class="border-b border-gray-100 dark:border-gray-800 pb-1">
                                            <div class="flex items-center justify-between py-1">
                                                <div class="flex items-center space-x-2">
                                                    <code class="text-primary-600 dark:text-primary-400 font-bold">{{'.$macro.'}}</code>
                                                    <span class="text-gray-600 dark:text-gray-400 text-xs truncate max-w-[150px]">'.$info['desc'].'</span>
                                                </div>
                                                <button type="button" @click="open = !open" class="text-gray-400 hover:text-primary-500 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </button>
                                            </div>
                                            <div x-show="open" x-collapse x-cloak class="mt-1 p-2 bg-gray-50 dark:bg-gray-900 rounded text-xs text-gray-500">
                                                <p><strong>Descrição:</strong> '.$info['desc'].'</p>
                                                <p class="mt-1 italic"><strong>Exemplo:</strong> '.$info['ex'].'</p>
                                            </div>
                                        </div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])->columnSpanFull(),
            ]);
    }
}
