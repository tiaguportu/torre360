<?php

namespace App\Filament\Resources\EventosEscolares\Schemas;

use App\Enums\TipoEventoEscolar;
use App\Models\Unidade;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventoEscolarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Evento')
                    ->description('Dados principais e categorização da atividade escolar.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('titulo')
                                    ->label('Título do Evento')
                                    ->placeholder('Ex: Reunião Geral de Pais e Mestres')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Select::make('tipo')
                                    ->label('Tipo de Evento')
                                    ->options(TipoEventoEscolar::class)
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('unidade_id')
                                    ->label('Unidade Escolar')
                                    ->options(Unidade::pluck('nome', 'id'))
                                    ->searchable()
                                    ->placeholder('Todas as Unidades')
                                    ->columnSpan(1),

                                TextInput::make('local')
                                    ->label('Local / Endereço')
                                    ->placeholder('Ex: Auditório Principal ou Parque Zoológico')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),

                        RichEditor::make('descricao')
                            ->label('Descrição e Programação do Evento')
                            ->placeholder('Descreva os detalhes, orientações de vestimenta, cronograma, etc.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Datas, Prazos e Capacidade')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('data_inicio')
                                    ->label('Início do Evento')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i'),

                                DateTimePicker::make('data_fim')
                                    ->label('Término do Evento')
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i'),

                                DateTimePicker::make('prazo_confirmacao')
                                    ->label('Prazo Limite para RSVP')
                                    ->helperText('Data máxima para a família confirmar presença.')
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('limite_vagas')
                                    ->label('Limite de Vagas (Capacidade)')
                                    ->helperText('Deixe em branco se a capacidade for livre.')
                                    ->numeric()
                                    ->minValue(1),

                                TextInput::make('valor_por_pessoa')
                                    ->label('Custo por Participante (R$)')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->default(0.00),

                                Toggle::make('ativo')
                                    ->label('Evento Ativo e Visível')
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ]),

                Section::make('Público Alvo e Autorização de Saída')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('publico_alvo')
                                    ->label('Público Convidado')
                                    ->options([
                                        'todos' => 'Toda a Escola (Todos os Alunos)',
                                        'turmas_especificas' => 'Apenas Turmas Específicas',
                                    ])
                                    ->default('todos')
                                    ->live()
                                    ->required(),

                                Select::make('turmas')
                                    ->label('Selecione as Turmas Participantes')
                                    ->relationship('turmas', 'nome')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('publico_alvo') === 'turmas_especificas'),
                            ]),

                        Toggle::make('exige_autorizacao')
                            ->label('Exige Termo de Autorização dos Pais (Passeio Externo)')
                            ->helperText('Se ativado, os responsáveis deverão assinar digitalmente a autorização pelo Portal.')
                            ->live()
                            ->default(false),

                        Textarea::make('termo_autorizacao')
                            ->label('Minuta do Termo de Autorização de Saída')
                            ->helperText('Texto legal exibido para aceite da família.')
                            ->placeholder('Ex: Autorizo a participação do meu dependente no passeio cultural sob supervisão do corpo docente...')
                            ->rows(4)
                            ->visible(fn (Get $get) => (bool) $get('exige_autorizacao'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
