<?php

namespace App\Filament\Resources\Turmas\Schemas;

use App\Models\EtapaEnsino;
use App\Models\Turma;
use App\Models\TurmaHorario;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TurmaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('codigo')
                    ->label('Código')
                    ->maxLength(255),
                Select::make('serie_id')
                    ->relationship('serie', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('turno_id')
                    ->relationship('turno', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('professor_conselheiro_id')
                    ->relationship('professorConselheiro', 'nome')
                    ->searchable(['nome', 'cpf'])
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                    ->preload(),
                TextInput::make('vagas_maximas')
                    ->numeric()
                    ->default(30),
                TextInput::make('carga_horaria_total')
                    ->label('Carga Horária Total (horas)')
                    ->numeric()
                    ->suffix('horas'),
                ColorPicker::make('cor')
                    ->label('Cor da Turma')
                    ->default(fn () => '#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT)),
                Select::make('tipo_avaliacao')
                    ->options([
                        'notas' => 'Notas',
                        'habilidades' => 'Habilidades',
                        'hibrido' => 'Híbrido',
                    ])
                    ->required()
                    ->default('notas'),
                Select::make('etapa_ensino_agregada_id')
                    ->label('Etapa de Ensino Agregada')
                    ->relationship('etapaEnsinoAgregada', 'nome')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('etapa_ensino_id', null)),
                Select::make('etapa_ensino_id')
                    ->label('Etapa de Ensino')
                    ->options(function (Get $get) {
                        $agregadaId = $get('etapa_ensino_agregada_id');
                        if (! $agregadaId) {
                            return [];
                        }

                        return EtapaEnsino::where('etapa_ensino_agregada_id', $agregadaId)
                            ->get()
                            ->pluck('nome', 'id');
                    })
                    ->searchable()
                    ->preload(),
                Select::make('tipo_mediacao_didatico_pedagogica')
                    ->label('Tipo de Mediação Didático-Pedagógica')
                    ->options([
                        1 => 'Presencial',
                        2 => 'Semipresencial',
                        3 => 'Educação a distância – EAD',
                    ])
                    ->default(1),
                Select::make('tipo_turma')
                    ->label('Tipo de Turma')
                    ->options([
                        4 => 'Atividade complementar',
                        5 => 'Atendimento educacional especializado (AEE)',
                        6 => 'Curricular (etapa de ensino)',
                        9 => 'Curricular (etapa de ensino) com Atividade Complementar',
                    ])
                    ->default(6)
                    ->live(),
                Select::make('local_funcionamento_diferenciado')
                    ->label('Local de Funcionamento Diferenciado da Turma')
                    ->options([
                        0 => 'A turma não está em local de funcionamento diferenciado',
                        1 => 'Sala anexa',
                        2 => 'Unidade de atendimento socioeducativo',
                        3 => 'Unidade prisional',
                    ])
                    ->default(0),
                Toggle::make('turma_educacao_especial')
                    ->label('Turma de Educação Especial (Classe Especial)')
                    ->default(false)
                    ->live(),
                Select::make('forma_organizacao')
                    ->label('Forma de Organização da Turma')
                    ->options([
                        1 => 'Série/Ano (Série Anual)',
                        2 => 'Períodos semestrais',
                        3 => 'Ciclos',
                        4 => 'Grupos não seriados com base na idade ou competência',
                        5 => 'Módulos',
                        6 => 'Alternância regular de períodos de estudos',
                    ]),
                Select::make('modalidade_ensino')
                    ->label('Modalidade de Ensino')
                    ->options([
                        1 => 'Ensino Regular',
                        2 => 'Educação Especial',
                        3 => 'Educação de Jovens e Adultos (EJA)',
                        4 => 'Educação Profissional',
                    ]),
                Select::make('tipo_lingua_ministrada')
                    ->label('Língua em que o Ensino é Ministrado')
                    ->options([
                        1 => 'Somente em Língua Portuguesa',
                        2 => 'Em Língua Indígena e Língua Portuguesa',
                        3 => 'Somente em Língua Indígena',
                    ])
                    ->default(1)
                    ->live(),
                TextInput::make('codigo_lingua_indigena')
                    ->label('Código da Língua Indígena (INEP)')
                    ->visible(fn (Get $get) => in_array((int) $get('tipo_lingua_ministrada'), [2, 3]))
                    ->maxLength(10),
                Toggle::make('turma_educacao_bilingue_surdos')
                    ->label('Turma de Educação Bilíngue de Surdos')
                    ->default(false),
                Section::make('Educacenso 2026 - Atendimento Educacional Especializado (AEE)')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get) => (int) $get('tipo_turma') === 5 || (bool) $get('turma_educacao_especial'))
                    ->schema([
                        Toggle::make('flag_aee_ensino_libras')
                            ->label('Ensino de Libras')
                            ->default(false),
                        Toggle::make('flag_aee_ensino_soroba')
                            ->label('Ensino de Sorobã')
                            ->default(false),
                        Toggle::make('flag_aee_ensino_informatica_acessivel')
                            ->label('Ensino de Informática Acessível')
                            ->default(false),
                        Toggle::make('flag_aee_ensino_caa')
                            ->label('Comunicação Alternativa e Aumentativa (CAA)')
                            ->default(false),
                        Toggle::make('flag_aee_tecnologia_assistiva')
                            ->label('Técnicas para Uso de Tecnologia Assistiva')
                            ->default(false),
                        Toggle::make('flag_aee_processos_cognitivos')
                            ->label('Desenvolvimento de Processos Cognitivos')
                            ->default(false),
                        Toggle::make('flag_aee_enriquecimento_curricular')
                            ->label('Enriquecimento Curricular')
                            ->default(false),
                        Toggle::make('flag_aee_portugues_segunda_lingua')
                            ->label('Ensino de Língua Portuguesa como 2ª Língua')
                            ->default(false),
                        Toggle::make('flag_aee_orientacao_mobilidade')
                            ->label('Técnicas de Orientação e Mobilidade')
                            ->default(false),
                    ])
                    ->columns(3),
                Section::make('Horário de Funcionamento (Dias da Semana)')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('horariosFuncionamento')
                            ->relationship('horariosFuncionamento')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('dia_semana')
                                    ->label('Dia da Semana')
                                    ->options([
                                        0 => 'Domingo',
                                        1 => 'Segunda-feira',
                                        2 => 'Terça-feira',
                                        3 => 'Quarta-feira',
                                        4 => 'Quinta-feira',
                                        5 => 'Sexta-feira',
                                        6 => 'Sábado',
                                    ])
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                                TimePicker::make('hora_inicio')
                                    ->label('Hora de Início')
                                    ->seconds(false),
                                TimePicker::make('hora_fim')
                                    ->label('Hora de Término')
                                    ->seconds(false),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default(fn () => array_map(fn ($dia) => [
                                'dia_semana' => $dia,
                                'hora_inicio' => null,
                                'hora_fim' => null,
                            ], range(0, 6)))
                            ->loadStateFromRelationshipsUsing(function (Repeater $component, ?Turma $record) {
                                if (! $record) {
                                    return;
                                }

                                $existing = $record->horariosFuncionamento->keyBy('dia_semana');
                                $items = [];

                                for ($dia = 0; $dia <= 6; $dia++) {
                                    $horario = $existing->get($dia);
                                    $items[] = [
                                        'dia_semana' => $dia,
                                        'hora_inicio' => $horario?->hora_inicio,
                                        'hora_fim' => $horario?->hora_fim,
                                    ];
                                }

                                $component->state($items);
                            })
                            ->saveRelationshipsUsing(function (Turma $record, array $state) {
                                foreach ($state as $item) {
                                    if (! empty($item['hora_inicio']) || ! empty($item['hora_fim'])) {
                                        TurmaHorario::updateOrCreate(
                                            [
                                                'turma_id' => $record->id,
                                                'dia_semana' => $item['dia_semana'],
                                            ],
                                            [
                                                'hora_inicio' => $item['hora_inicio'] ?: null,
                                                'hora_fim' => $item['hora_fim'] ?: null,
                                            ]
                                        );
                                    } else {
                                        TurmaHorario::where('turma_id', $record->id)
                                            ->where('dia_semana', $item['dia_semana'])
                                            ->delete();
                                    }
                                }
                            }),
                    ]),
            ]);
    }
}
