<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MensagemWhatsappTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Modelo de Comunicação Oficial')
                    ->description('Modelos padronizados para comunicados oficiais, rotinas operacionais (0s de latência) e links transacionais (pesquisa de satisfação). Também podem servir como base para o Copiloto IA.')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Modelo')
                            ->placeholder('Ex: Pesquisa de Satisfação Pós-Visita, Lembrete de Matrícula, Confirmação de Agendamento')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('conteudo')
                            ->label('Mensagem Padrão')
                            ->helperText('Variáveis substituídas automaticamente: [Nome do Responsável], [Primeiro Nome], [Nome do Aluno], [Horário de Visita Agendada], [Link da Pesquisa da Visita], [Nome da Escola].')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),
                        Toggle::make('ativo')
                            ->label('Ativo para Atendimento')
                            ->default(true)
                            ->helperText('Modelos ativos aparecem no disparo rápido e como referência no Copiloto IA.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
