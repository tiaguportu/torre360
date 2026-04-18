<?php

namespace App\Filament\Resources\Questionarios\Schemas;

use App\Models\Curso;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;

class QuestionarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('QuestionarioTabs')
                    ->tabs([
                        Tabs\Tab::make('Geral')
                            ->icon(Heroicon::OutlinedClipboardDocument)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('titulo')
                                            ->label('Título do Questionário')
                                            ->required()
                                            ->columnSpan(2),
                                        Textarea::make('descricao')
                                            ->label('Descrição/Instruções')
                                            ->columnSpanFull(),
                                        DateTimePicker::make('inicio_aplicacao')
                                            ->label('Início da Aplicação'),
                                        DateTimePicker::make('fim_aplicacao')
                                            ->label('Fim da Aplicação'),
                                        Toggle::make('is_anonimo')
                                            ->label('Respostas Anônimas')
                                            ->default(false),
                                        Toggle::make('is_ativo')
                                            ->label('Ativo')
                                            ->default(true),
                                    ]),
                            ]),

                        Tabs\Tab::make('Público-Alvo')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema([
                                Section::make('Definição de Público')
                                    ->description('Associe este questionário a unidades, cursos, séries ou turmas específicas.')
                                    ->schema([
                                        Repeater::make('alvos')
                                            ->relationship('alvos')
                                            ->schema([
                                                Select::make('alvo_type')
                                                    ->label('Tipo de Filtro')
                                                    ->options([
                                                        'Unidade' => 'Unidade',
                                                        'Curso' => 'Curso',
                                                        'Serie' => 'Série',
                                                        'Turma' => 'Turma',
                                                        'Role' => 'Perfil/Role',
                                                        'User' => 'Usuário Específico',
                                                    ])
                                                    ->required()
                                                    ->live(),
                                                Select::make('alvo_id')
                                                    ->label('Vínculo')
                                                    ->options(function (callable $get) {
                                                        $type = $get('alvo_type');

                                                        return match ($type) {
                                                            'Unidade' => Unidade::all()->pluck('nome', 'id'),
                                                            'Curso' => Curso::all()->pluck('nome', 'id'),
                                                            'Serie' => Serie::all()->pluck('nome', 'id'),
                                                            'Turma' => Turma::all()->pluck('nome', 'id'),
                                                            'Role' => Role::all()->pluck('name', 'id'),
                                                            'User' => User::all()->pluck('name', 'id'),
                                                            default => [],
                                                        };
                                                    })
                                                    ->required()
                                                    ->searchable(),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Adicionar Filtro de Público'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Estrutura e Perguntas')
                            ->icon(Heroicon::OutlinedQuestionMarkCircle)
                            ->schema([
                                Section::make('Blocos e Perguntas')
                                    ->description('Organize seu questionário em blocos temáticos.')
                                    ->schema([
                                        Repeater::make('blocos')
                                            ->relationship('blocos')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('titulo')
                                                            ->label('Título do Bloco')
                                                            ->required(),
                                                        TextInput::make('ordem')
                                                            ->label('Ordem')
                                                            ->numeric()
                                                            ->default(0),
                                                        Textarea::make('descricao')
                                                            ->label('Descrição do Bloco')
                                                            ->columnSpanFull(),
                                                    ]),
                                                Repeater::make('perguntas')
                                                    ->relationship('perguntas')
                                                    ->schema([
                                                        TextInput::make('enunciado')
                                                            ->label('Pergunta')
                                                            ->required(),
                                                        Select::make('tipo')
                                                            ->label('Tipo de Pergunta')
                                                            ->options([
                                                                'discursiva' => 'Discursiva (Texto Livre)',
                                                                'objetiva' => 'Objetiva (Única Escolha)',
                                                                'multipla_escolha' => 'Múltipla Escolha',
                                                                'likert' => 'Escala Likert (1-5)',
                                                            ])
                                                            ->required()
                                                            ->live(),
                                                        Toggle::make('is_obrigatoria')
                                                            ->label('Obrigatória')
                                                            ->default(true),
                                                        TextInput::make('ordem')
                                                            ->label('Ordem')
                                                            ->numeric()
                                                            ->default(0),
                                                        Grid::make(1)
                                                            ->visible(fn ($get) => in_array($get('tipo'), ['objetiva', 'multipla_escolha']))
                                                            ->schema([
                                                                Repeater::make('opcoes')
                                                                    ->label('Opções de Resposta')
                                                                    ->schema([
                                                                        TextInput::make('label')
                                                                            ->label('Rótulo da Opção')
                                                                            ->required(),
                                                                    ])
                                                                    ->addActionLabel('Adicionar Opção'),
                                                            ]),
                                                    ])
                                                    ->columns(2)
                                                    ->addActionLabel('Adicionar Pergunta')
                                                    ->itemLabel(fn (array $state): ?string => $state['enunciado'] ?? null),
                                            ])
                                            ->addActionLabel('Adicionar Bloco Temático')
                                            ->itemLabel(fn (array $state): ?string => $state['titulo'] ?? null),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
