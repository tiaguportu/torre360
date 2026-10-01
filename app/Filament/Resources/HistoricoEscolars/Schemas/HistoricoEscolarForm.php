<?php

namespace App\Filament\Resources\HistoricoEscolars\Schemas;

use App\Models\Curso;
use App\Models\HistoricoEscolar;
use App\Models\Pessoa;
use App\Models\Unidade;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class HistoricoEscolarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('historico_tabs')
                    ->tabs([
                        Tab::make('Dados Gerais')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Identificação do Documento e do Estudante')
                                    ->schema([
                                        Select::make('pessoa_id')
                                            ->label('Estudante (Aluno)')
                                            ->options(fn () => Pessoa::query()
                                                ->whereHas('matriculas')
                                                ->orderBy('nome')
                                                ->pluck('nome', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->disabled(fn (?string $operation) => $operation === 'edit'),

                                        Select::make('curso_id')
                                            ->label('Etapa / Curso')
                                            ->options(fn () => Curso::pluck('nome_interno', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Ex: Ensino Fundamental, Ensino Médio.'),

                                        Select::make('unidade_id')
                                            ->label('Unidade Escolar Expedidora')
                                            ->options(fn () => Unidade::pluck('nome', 'id'))
                                            ->default(fn () => Unidade::first()?->id)
                                            ->required(),

                                        Select::make('situacao')
                                            ->label('Situação do Aluno no Ciclo')
                                            ->options([
                                                'em_curso' => 'Em Curso',
                                                'concluido' => 'Concluído',
                                                'transferido' => 'Transferido',
                                            ])
                                            ->default('em_curso')
                                            ->live()
                                            ->required(),

                                        DatePicker::make('data_emissao')
                                            ->label('Data de Expedição')
                                            ->default(now())
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('data_conclusao')
                                            ->label('Data de Conclusão de Curso')
                                            ->visible(fn (Get $get) => $get('situacao') === 'concluido')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('codigo_autenticidade')
                                            ->label('Código de Autenticidade (QR Code)')
                                            ->default(fn () => HistoricoEscolar::gerarCodigoAutenticidade())
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Código único e imutável para validação pública.'),
                                    ])->columns(2),
                            ]),

                        Tab::make('Anos e Séries (Multi-Ano)')
                            ->icon('heroicon-o-table-cells')
                            ->schema([
                                Section::make('Matriz de Desempenho Curricular Multi-Ano')
                                    ->description('Gerencie cada ano letivo cursado pelo estudante (tanto no Torre360 quanto em outras escolas).')
                                    ->schema([
                                        Repeater::make('anos')
                                            ->relationship('anos')
                                            ->label('Anos / Séries Cursadas (Colunas)')
                                            ->collapsible()
                                            ->cloneable()
                                            ->itemLabel(fn (array $state): ?string => ($state['serie_nome'] ?? 'Série').' ('.($state['ano_letivo'] ?? 'Ano').') — '.(($state['tipo'] ?? 'interno') === 'externo' ? 'Externo: '.($state['escola_nome'] ?? '') : 'Interno (Torre360)'))
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        TextInput::make('ano_letivo')
                                                            ->label('Ano Letivo')
                                                            ->numeric()
                                                            ->default(now()->year)
                                                            ->required(),

                                                        TextInput::make('serie_nome')
                                                            ->label('Série / Ano')
                                                            ->placeholder('Ex: 6º Ano, 1ª Série')
                                                            ->required(),

                                                        Select::make('tipo')
                                                            ->label('Origem dos Estudos')
                                                            ->options([
                                                                'interno' => 'Interno (Torre360)',
                                                                'externo' => 'Externo (Outra Escola)',
                                                            ])
                                                            ->default('interno')
                                                            ->live()
                                                            ->required(),

                                                        TextInput::make('escola_nome')
                                                            ->label('Estabelecimento de Ensino')
                                                            ->default(config('app.name', 'Torre de Marfim'))
                                                            ->required(),
                                                    ]),

                                                Grid::make(5)
                                                    ->schema([
                                                        TextInput::make('escola_cidade')
                                                            ->label('Município da Escola')
                                                            ->placeholder('Ex: São Paulo'),

                                                        TextInput::make('escola_uf')
                                                            ->label('UF')
                                                            ->maxLength(2)
                                                            ->placeholder('SP'),

                                                        TextInput::make('carga_horaria_total')
                                                            ->label('C.H. Total (horas)')
                                                            ->numeric()
                                                            ->default(800),

                                                        TextInput::make('dias_letivos')
                                                            ->label('Dias Letivos')
                                                            ->numeric()
                                                            ->default(200),

                                                        TextInput::make('frequencia_percentual')
                                                            ->label('Frequência (%)')
                                                            ->numeric()
                                                            ->default(100.00),
                                                    ]),

                                                Grid::make(3)
                                                    ->schema([
                                                        Select::make('situacao_ano')
                                                            ->label('Resultado do Ano')
                                                            ->options([
                                                                'Aprovado' => 'Aprovado',
                                                                'Reprovado' => 'Reprovado',
                                                                'Classificado' => 'Classificado',
                                                                'Transferido' => 'Transferido',
                                                                'Cursando' => 'Cursando',
                                                            ])
                                                            ->default('Aprovado')
                                                            ->required(),

                                                        TextInput::make('ordem')
                                                            ->label('Ordem da Coluna')
                                                            ->numeric()
                                                            ->default(1),

                                                        TextInput::make('observacoes')
                                                            ->label('Anotações do Ano')
                                                            ->placeholder('Ex: Progressão parcial em...'),
                                                    ]),

                                                // Sub-repeater de Disciplinas
                                                Repeater::make('disciplinas')
                                                    ->relationship('disciplinas')
                                                    ->label('Componentes Curriculares / Disciplinas Deste Ano')
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->itemLabel(fn (array $state): ?string => ($state['disciplina_nome'] ?? 'Disciplina').' — Nota: '.($state['nota_final'] ?? ($state['conceito'] ?? '-')).' (CH: '.($state['carga_horaria'] ?? '-').'h)')
                                                    ->schema([
                                                        Grid::make(6)
                                                            ->schema([
                                                                TextInput::make('disciplina_nome')
                                                                    ->label('Disciplina')
                                                                    ->placeholder('Ex: Língua Portuguesa')
                                                                    ->required()
                                                                    ->columnSpan(2),

                                                                TextInput::make('area_conhecimento')
                                                                    ->label('Área do Conhecimento')
                                                                    ->placeholder('Ex: Linguagens, Matemática')
                                                                    ->columnSpan(1),

                                                                TextInput::make('carga_horaria')
                                                                    ->label('C.H. (h)')
                                                                    ->numeric()
                                                                    ->placeholder('160')
                                                                    ->columnSpan(1),

                                                                TextInput::make('nota_final')
                                                                    ->label('Nota Final')
                                                                    ->numeric()
                                                                    ->step(0.1)
                                                                    ->placeholder('8.5')
                                                                    ->columnSpan(1),

                                                                Select::make('situacao')
                                                                    ->label('Situação')
                                                                    ->options([
                                                                        'Aprovado' => 'Aprovado',
                                                                        'Reprovado' => 'Reprovado',
                                                                        'Dispensado' => 'Dispensado',
                                                                    ])
                                                                    ->default('Aprovado')
                                                                    ->columnSpan(1),
                                                            ]),
                                                    ])
                                                    ->defaultItems(0)
                                                    ->columnSpanFull(),
                                            ])
                                            ->defaultItems(0),
                                    ]),
                            ]),

                        Tab::make('Certificação e Observações')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Section::make('Textos Oficiais de Certificação e Amparo Legal')
                                    ->schema([
                                        TextInput::make('titulo_certificacao')
                                            ->label('Título da Certificação')
                                            ->placeholder('Ex: CERTIFICADO DE CONCLUSÃO DO ENSINO FUNDAMENTAL')
                                            ->helperText('Exibido no quadro de certificação quando a situação for "Concluído".'),

                                        Textarea::make('texto_certificacao')
                                            ->label('Texto Oficial da Certidão')
                                            ->placeholder('Certificamos que o(a) aluno(a) concluiu os estudos da etapa...')
                                            ->rows(4)
                                            ->columnSpanFull(),

                                        Textarea::make('observacoes')
                                            ->label('Observações Gerais e Amparo Legal')
                                            ->placeholder('Disposições sobre a Lei Federal 9.394/1996, Pareceres do CEE, observações de adaptação curricular ou transferências...')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])->columns(1),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
