<?php

namespace App\Filament\Resources\AtendimentoSetores\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AtendimentoSetorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do Setor de Atendimento')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Nome do Setor')
                                    ->placeholder('Ex: Secretaria, Financeiro, Coordenação')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('ordem')
                                    ->label('Ordem de Exibição')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1),
                            ]),

                        TextInput::make('email_notificacao')
                            ->label('E-mail para Notificação')
                            ->placeholder('setor@escola.com.br')
                            ->email()
                            ->maxLength(255),

                        Textarea::make('descricao')
                            ->label('Descrição / Orientações para a Família')
                            ->placeholder('Explique quais tipos de assuntos são tratados por este setor...')
                            ->rows(3),

                        Toggle::make('ativo')
                            ->label('Setor Ativo para Abertura de Chamados')
                            ->default(true),

                        Toggle::make('recebe_requerimentos')
                            ->label('Recebe os requerimentos da família')
                            ->helperText('Pedidos como atestado, atualização cadastral e troca de turma caem neste setor. Se nenhum setor estiver marcado, o primeiro setor ativo recebe.')
                            ->default(false),
                    ]),

                Section::make('Prazos de atendimento')
                    ->description('Contados em horas úteis (segunda a sexta, no horário da escola). Deixe em branco para não cobrar prazo deste setor.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('prazo_primeira_resposta_horas')
                                    ->label('Primeira resposta (horas úteis)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->default(24),

                                TextInput::make('prazo_resolucao_horas')
                                    ->label('Resolução (horas úteis)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(2400)
                                    ->default(72),

                                TextInput::make('fecha_aguardando_dias')
                                    ->label('Encerrar sozinho após (dias sem resposta da família)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(90)
                                    ->default(7),
                            ]),
                    ]),
            ]);
    }
}
