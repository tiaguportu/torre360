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
                    ])->columns(2),

                Section::make('Conteúdo e Macros')
                    ->schema([
                        TinyEditor::make('conteudo')
                            ->label('Conteúdo do Contrato')
                            ->required()
                            ->columnSpanFull(),

                        Placeholder::make('macros_disponiveis')
                            ->label('Macros Disponíveis')
                            ->content(function () {
                                return new HtmlString('
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div><strong>{{CONTRATO_ID}}</strong>: ID do contrato</div>
                                        <div><strong>{{CONTRATO_VALOR}}</strong>: Valor total</div>
                                        <div><strong>{{CONTRATO_DATA}}</strong>: Data atual formatada</div>
                                        <div><strong>{{UNIDADE_NOME}}</strong>: Nome da unidade</div>
                                        <div><strong>{{UNIDADE_CNPJ}}</strong>: CNPJ da unidade</div>
                                        <div><strong>{{ALUNOS_TABELA}}</strong>: Tabela de alunos/turmas</div>
                                        <div><strong>{{RESPONSAVEIS_INFO}}</strong>: Texto dos responsáveis</div>
                                        <div><strong>{{FATURAS_TABELA}}</strong>: Tabela de parcelas</div>
                                    </div>
                                ');
                            }),
                    ]),
            ]);
    }
}
