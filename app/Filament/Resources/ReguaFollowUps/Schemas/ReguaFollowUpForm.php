<?php

namespace App\Filament\Resources\ReguaFollowUps\Schemas;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ReguaFollowUpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Definição da Automação e Gatilho')
                    ->description('Defina o momento e o canal pelo qual esta mensagem deve ser disparada.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Nome da Automação')
                                    ->placeholder('Ex: Lembrete de Visita à Escola (1 dia antes)')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('gatilho')
                                    ->label('Evento de Disparo (Gatilho)')
                                    ->options(GatilhoReguaFollowUp::class)
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('dias_offset')
                                    ->label('Intervalo em Dias (Offset)')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->helperText(fn (Get $get): string => match ($get('gatilho')) {
                                        'visita_lembrete' => 'Ex: 1 para avisar 1 dia antes da visita marcada.',
                                        'visita_realizada' => 'Ex: 1 para agradecer 1 dia após a visita realizada.',
                                        'visita_faltou' => 'Ex: 1 para reengajar 1 dia após a falta na visita.',
                                        'lead_criado' => 'Ex: 0 para disparar no mesmo dia do cadastro.',
                                        'lead_estagnado' => 'Ex: 7 para alertar quando estiver 7 dias sem contato.',
                                        'contato_atrasado' => 'Ex: 1 para alertar 1 dia após a data de contato vencer.',
                                        default => 'Dias em relação ao momento do evento.',
                                    }),

                                Select::make('canal')
                                    ->label('Canal de Envio')
                                    ->options(CanalReguaFollowUp::class)
                                    ->required()
                                    ->default(CanalReguaFollowUp::Email->value),

                                TimePicker::make('horario_envio')
                                    ->label('Horário Preferencial de Disparo')
                                    ->default('08:00:00')
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('ordem')
                                    ->label('Ordem de Prioridade')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_ativo')
                                    ->label('Automação Ativa')
                                    ->helperText('Desative para pausar os disparos automáticos desta regra.')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Filtros Opcionais de Segmentação')
                    ->description('Deixe em branco para aplicar a todos os leads do funil.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('origem_interessado_id')
                                    ->label('Origem Específica do Lead')
                                    ->options(OrigemInteressado::orderBy('nome')->pluck('nome', 'id'))
                                    ->placeholder('Todas as origens (Site, Instagram, Indicação...)')
                                    ->searchable()
                                    ->preload(),

                                Select::make('status_interessado_id')
                                    ->label('Etapa / Status Específico do Lead')
                                    ->options(StatusInteressado::where('is_final', false)->orderBy('ordem')->pluck('nome', 'id'))
                                    ->placeholder('Todas as etapas ativas em andamento')
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ])
                    ->collapsed(),

                Section::make('Conteúdo da Mensagem')
                    ->description('Personalize o assunto e a mensagem usando as tags automáticas do lead e da visita.')
                    ->schema([
                        TextInput::make('assunto')
                            ->label('Assunto / Título da Notificação')
                            ->placeholder('Ex: Lembrete: Sua visita ao {{ESCOLA_NOME}} é amanhã!')
                            ->required()
                            ->maxLength(255),

                        RichEditor::make('mensagem')
                            ->label('Corpo da Mensagem')
                            ->required()
                            ->helperText('Tags disponíveis: {{NOME_RESPONSAVEL}} ou [Nome], {{NOME_ALUNO}} ou [Aluno], {{SERIE_INTERESSE}} ou [Serie], {{NOME_CONSULTOR}} ou [Consultor], {{DATA_VISITA}} ou [DataVisita], {{HORARIO_VISITA}} ou [HorarioVisita], {{ESCOLA_NOME}} ou [Escola].'),
                    ]),
            ]);
    }
}
