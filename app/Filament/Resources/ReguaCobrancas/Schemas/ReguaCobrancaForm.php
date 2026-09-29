<?php

namespace App\Filament\Resources\ReguaCobrancas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReguaCobrancaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Definição da Regra e Gatilho')
                    ->description('Configure quando e por quais canais este lembrete deve ser enviado.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Nome da Régua')
                                    ->placeholder('Ex: Lembrete Preventivo (5 dias antes)')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('tipo_gatilho')
                                    ->label('Momento do Gatilho')
                                    ->options([
                                        'antes_vencimento' => 'Antes do Vencimento (Preventivo)',
                                        'no_vencimento' => 'No Dia do Vencimento (Dia D)',
                                        'apos_vencimento' => 'Após o Vencimento (Inadimplência / Atraso)',
                                    ])
                                    ->required()
                                    ->default('antes_vencimento'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('dias_offset')
                                    ->label('Dias em relação ao Vencimento')
                                    ->helperText('Negativo para antes (ex: -5), 0 para o dia, positivo para atraso (ex: 3)')
                                    ->numeric()
                                    ->required()
                                    ->default(0),

                                Select::make('canal')
                                    ->label('Canal de Envio')
                                    ->options([
                                        'todos' => 'Todos os Canais (E-mail, Portal e Push)',
                                        'email' => 'Apenas E-mail',
                                        'portal' => 'Apenas Notificação no Portal',
                                        'push' => 'Apenas Push Notification',
                                    ])
                                    ->required()
                                    ->default('todos'),

                                TimePicker::make('horario_envio')
                                    ->label('Horário de Execução Diária')
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
                                    ->label('Régua Ativa')
                                    ->helperText('Desative para pausar os envios automáticos deste gatilho.')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Conteúdo da Notificação')
                    ->description('Escreva o modelo da mensagem utilizando as macros dinâmicas.')
                    ->schema([
                        TextInput::make('assunto')
                            ->label('Assunto / Título do Alerta')
                            ->placeholder('Ex: Lembrete de Vencimento: Fatura #{{NUMERO_FATURA}} - {{ALUNO_NOME}}')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('mensagem')
                            ->label('Mensagem')
                            ->rows(5)
                            ->required()
                            ->helperText('Macros disponíveis: {{RESPONSAVEL_NOME}}, {{ALUNO_NOME}}, {{NUMERO_FATURA}}, {{VALOR}}, {{DATA_VENCIMENTO}}, {{DIAS_ATRASO}}, {{LINK_PAGAMENTO}}, {{PIX_COPIA_COLA}}'),
                    ]),
            ]);
    }
}
