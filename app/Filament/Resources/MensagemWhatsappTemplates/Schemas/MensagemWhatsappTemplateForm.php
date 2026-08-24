<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class MensagemWhatsappTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Modelo de Mensagem')
                    ->description('Use as variáveis abaixo no texto que serão substituídas automaticamente ao enviar: [Nome do Responsável], [Nome do Aluno], [Horário de Visita Agendada].')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Modelo')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('conteudo')
                            ->label('Mensagem')
                            ->helperText('Variáveis disponíveis: [Nome do Responsável], [Nome do Aluno], [Horário de Visita Agendada].')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->helperText('Modelos inativos não aparecem na lista de envio rápido.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
