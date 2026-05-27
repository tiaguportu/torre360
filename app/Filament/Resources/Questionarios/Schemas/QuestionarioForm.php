<?php

namespace App\Filament\Resources\Questionarios\Schemas;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Curso;
use App\Models\Questionario;
use App\Models\QuestionarioPergunta;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\QuestionarioService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                                        TextInput::make('max_respostas_por_usuario')
                                            ->label('Máximo de Respostas por Usuário')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->placeholder('Sem limite (infinito)')
                                            ->helperText('Deixe em branco para permitir respostas ilimitadas (infinito).'),
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

                        Tabs\Tab::make('Permissões e Acesso')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                Section::make('Donos e Observadores')
                                    ->description('Defina quem gerencia (Dono) e quem apenas acompanha os resultados (Observador).')
                                    ->schema([
                                        Repeater::make('responsaveis')
                                            ->relationship('responsaveis')
                                            ->schema([
                                                Select::make('nivel')
                                                    ->label('Nível de Acesso')
                                                    ->options([
                                                        'dono' => 'Dono (Edita, Visualiza, Exclui)',
                                                        'observador' => 'Observador (Apenas Visualiza)',
                                                    ])
                                                    ->required(),
                                                Select::make('responsavel_type')
                                                    ->label('Tipo de Vínculo')
                                                    ->options([
                                                        'Role' => 'Perfil/Role',
                                                        'User' => 'Usuário Específico',
                                                    ])
                                                    ->required()
                                                    ->live(),
                                                Select::make('responsavel_id')
                                                    ->label('Vínculo')
                                                    ->options(function (callable $get) {
                                                        $type = $get('responsavel_type');

                                                        return match ($type) {
                                                            'Role' => Role::all()->pluck('name', 'id'),
                                                            'User' => User::all()->pluck('name', 'id'),
                                                            default => [],
                                                        };
                                                    })
                                                    ->required()
                                                    ->searchable(),
                                            ])
                                            ->columns(3)
                                            ->addActionLabel('Adicionar Responsável'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Estrutura e Perguntas')
                            ->icon(Heroicon::OutlinedQuestionMarkCircle)
                            ->schema([
                                Actions::make([
                                    Action::make('exportar_csv')
                                        ->label('Exportar CSV')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('info')
                                        ->action(function (Questionario $record, QuestionarioService $service) {
                                            $csv = $service->exportToCsv($record);
                                            $filename = 'questionario_'.Str::slug($record->titulo).'_'.date('YmdHis').'.csv';

                                            return response()->streamDownload(function () use ($csv) {
                                                echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
                                                echo $csv;
                                            }, $filename, [
                                                'Content-Type' => 'text/csv; charset=utf-8',
                                            ]);
                                        }),
                                    Action::make('importar_csv')
                                        ->label('Importar CSV')
                                        ->icon('heroicon-o-arrow-up-tray')
                                        ->color('warning')
                                        ->form([
                                            FileUpload::make('arquivo_csv')
                                                ->label('Arquivo CSV')
                                                ->required()
                                                ->disk('local')
                                                ->directory('temp_imports')
                                                ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
                                        ])
                                        ->action(function (array $data, Questionario $record, QuestionarioService $service, $livewire) {
                                            $path = Storage::disk('local')->path($data['arquivo_csv']);

                                            try {
                                                $service->importFromCsv($record, $path);

                                                Notification::make()
                                                    ->title('Estrutura importada com sucesso!')
                                                    ->success()
                                                    ->send();

                                                $livewire->redirect(QuestionarioResource::getUrl('edit', ['record' => $record]));
                                            } catch (\Exception $e) {
                                                Notification::make()
                                                    ->title('Erro ao importar CSV: '.$e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            } finally {
                                                Storage::disk('local')->delete($data['arquivo_csv']);
                                            }
                                        }),
                                ])
                                    ->columnSpanFull()
                                    ->visible(fn (?Questionario $record) => $record !== null && $record->exists),

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
                                                            ->default(0)
                                                            ->hidden(),
                                                        Textarea::make('descricao')
                                                            ->label('Descrição do Bloco')
                                                            ->columnSpanFull(),
                                                        TextInput::make('identificador')
                                                            ->label('ID Textual (Importação/Exportação)')
                                                            ->placeholder('ex: bloco_01')
                                                            ->columnSpanFull(),
                                                    ]),
                                                Repeater::make('perguntas')
                                                    ->relationship('perguntas')
                                                    ->schema([
                                                        RichEditor::make('enunciado')
                                                            ->label('Pergunta')
                                                            ->required()
                                                            ->columnSpanFull(),
                                                        Select::make('tipo')
                                                            ->label('Tipo de Pergunta')
                                                            ->options([
                                                                'discursiva' => 'Discursiva (Texto Livre)',
                                                                'objetiva' => 'Objetiva (Única Escolha)',
                                                                'multipla_escolha' => 'Múltipla Escolha',
                                                                'likert' => 'Escala Likert (1-5)',
                                                                'usuarios' => 'Lista de Usuários do Sistema',
                                                                'alunos_turma' => 'Lista de Alunos de uma Turma',
                                                                'pessoas' => 'Lista de Pessoas Cadastradas',
                                                            ])
                                                            ->required()
                                                            ->live(),
                                                        Toggle::make('is_obrigatoria')
                                                            ->label('Obrigatória')
                                                            ->default(true),
                                                        TextInput::make('ordem')
                                                            ->label('Ordem')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->hidden(),
                                                        TextInput::make('identificador')
                                                            ->label('ID Textual (Importação/Exportação)')
                                                            ->placeholder('ex: perg_01')
                                                            ->columnSpanFull(),
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
                                                                    ->reorderableWithButtons()
                                                                    ->addActionLabel('Adicionar Opção'),
                                                            ]),

                                                        // ───── Condição de Exibição ─────
                                                        Section::make('Condição de Exibição')
                                                            ->description('Configure quando esta pergunta deve aparecer ao respondente, com base na resposta de outra pergunta.')
                                                            ->icon(Heroicon::OutlinedEye)
                                                            ->collapsed()
                                                            ->collapsible()
                                                            ->columnSpanFull()
                                                            ->schema([
                                                                Select::make('condicao_exibicao.pergunta_id')
                                                                    ->label('Pergunta de Referência')
                                                                    ->placeholder('Nenhuma (sempre exibida)')
                                                                    ->options(function (callable $get) {
                                                                        // Tenta pegar o ID do questionário do bloco pai
                                                                        $questionarioId = $get('../../questionario_id');

                                                                        // Se não encontrou no bloco (pode ser novo), tenta subir mais níveis até o form principal
                                                                        if (! $questionarioId) {
                                                                            $questionarioId = $get('../../../../id');
                                                                        }

                                                                        if (! $questionarioId) {
                                                                            return [];
                                                                        }

                                                                        // Pegar o ID da pergunta atual para evitar auto-referência
                                                                        $currentId = $get('id');

                                                                        return QuestionarioPergunta::query()
                                                                            ->select('questionario_perguntas.*')
                                                                            ->join('questionario_blocos', 'questionario_perguntas.questionario_bloco_id', '=', 'questionario_blocos.id')
                                                                            ->where('questionario_blocos.questionario_id', $questionarioId)
                                                                            ->when($currentId, fn ($q) => $q->where('questionario_perguntas.id', '!=', $currentId))
                                                                            ->orderBy('questionario_blocos.ordem')
                                                                            ->orderBy('questionario_perguntas.ordem')
                                                                            ->get()
                                                                            ->mapWithKeys(function ($p) {
                                                                                $label = strip_tags($p->enunciado);
                                                                                if ($p->identificador) {
                                                                                    $label = "[{$p->identificador}] {$label}";
                                                                                }

                                                                                return [$p->id => $label];
                                                                            })
                                                                            ->toArray();
                                                                    })
                                                                    ->searchable()
                                                                    ->live()
                                                                    ->nullable()
                                                                    ->exists('questionario_perguntas', 'id'),
                                                                Select::make('condicao_exibicao.operador')
                                                                    ->label('Operador / Condição')
                                                                    ->options([
                                                                        'igual' => 'É igual a',
                                                                        'diferente' => 'É diferente de',
                                                                        'contem' => 'Contém',
                                                                        'nao_contem' => 'Não contém',
                                                                        'preenchido' => 'Foi preenchida (qualquer valor)',
                                                                        'nao_preenchido' => 'Não foi preenchida',
                                                                        'maior_que' => 'É maior que',
                                                                        'menor_que' => 'É menor que',
                                                                    ])
                                                                    ->default('igual')
                                                                    ->live()
                                                                    ->visible(fn ($get) => ! empty($get('condicao_exibicao.pergunta_id'))),
                                                                TextInput::make('condicao_exibicao.valor')
                                                                    ->label('Valor Esperado')
                                                                    ->placeholder('Ex: Sim, Não, 3...')
                                                                    ->helperText('Para perguntas de múltipla escolha, digite exatamente o rótulo da opção.')
                                                                    ->visible(fn ($get) => ! empty($get('condicao_exibicao.pergunta_id')) && ! in_array($get('condicao_exibicao.operador'), ['preenchido', 'nao_preenchido'])),
                                                            ]),
                                                    ])
                                                    ->columns(2)
                                                    ->reorderable('ordem')
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->addActionLabel('Adicionar Pergunta')
                                                    ->itemLabel(fn (array $state): ?string => isset($state['enunciado']) ? strip_tags($state['enunciado']) : null)
                                                    ->extraItemActions([
                                                        Action::make('mover_bloco')
                                                            ->label('Mover de Bloco')
                                                            ->icon('heroicon-o-arrows-right-left')
                                                            ->color('warning')
                                                            ->form([
                                                                Select::make('novo_bloco_index')
                                                                    ->label('Escolha o Bloco de Destino')
                                                                    ->options(function ($get, $livewire) {
                                                                        $blocos = $livewire->data['blocos'] ?? [];
                                                                        $options = [];
                                                                        foreach ($blocos as $idx => $bloco) {
                                                                            $options[$idx] = $bloco['titulo'] ?? 'Bloco '.($idx + 1);
                                                                        }

                                                                        return $options;
                                                                    })
                                                                    ->required(),
                                                            ])
                                                            ->action(function (array $data, $component, $livewire) {
                                                                $itemPath = $component->getContainer()->getStatePath();

                                                                if (preg_match('/(?:data\.)?blocos\.([\w\-]+)\.perguntas\.([\w\-]+)/', $itemPath, $matches)) {
                                                                    $blocoOrigemKey = $matches[1];
                                                                    $perguntaOrigemKey = $matches[2];
                                                                    $blocoDestinoKey = $data['novo_bloco_index'];

                                                                    if ($blocoOrigemKey !== $blocoDestinoKey) {
                                                                        $formData = $livewire->data;

                                                                        $pergunta = $formData['blocos'][$blocoOrigemKey]['perguntas'][$perguntaOrigemKey];

                                                                        // Remover do bloco de origem mantendo as chaves de string/UUIDs intactas
                                                                        unset($formData['blocos'][$blocoOrigemKey]['perguntas'][$perguntaOrigemKey]);

                                                                        // Adicionar no bloco de destino
                                                                        if (! isset($formData['blocos'][$blocoDestinoKey]['perguntas'])) {
                                                                            $formData['blocos'][$blocoDestinoKey]['perguntas'] = [];
                                                                        }

                                                                        // Preservar o UUID original da pergunta para manter o rastreamento do componente pelo Livewire
                                                                        $formData['blocos'][$blocoDestinoKey]['perguntas'][$perguntaOrigemKey] = $pergunta;

                                                                        // Atualizar dados no Livewire
                                                                        $livewire->data = $formData;

                                                                        Notification::make()
                                                                            ->title('Pergunta movida de bloco com sucesso!')
                                                                            ->success()
                                                                            ->send();
                                                                    }
                                                                }
                                                            }),
                                                    ]),
                                            ])
                                            ->reorderable('ordem')
                                            ->collapsible()
                                            ->collapsed()
                                            ->addActionLabel('Adicionar Bloco Temático')
                                            ->itemLabel(fn (array $state): ?string => $state['titulo'] ?? null),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
