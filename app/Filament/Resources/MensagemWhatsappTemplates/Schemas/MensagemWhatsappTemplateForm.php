<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Schemas;

use App\Models\MensagemWhatsappTemplate;
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

                Section::make('Instruções para a IA (opcional)')
                    ->description('Como o Copiloto IA deve tratar este modelo. Somam-se às regras gerais do Copiloto e não afetam o envio direto do modelo.')
                    ->schema([
                        Textarea::make('instrucoes_ia')
                            ->label('Instruções específicas deste modelo')
                            ->placeholder('Ex: Manter o prazo de matrícula exatamente como no texto. Citar o período integral. Não oferecer desconto.')
                            ->helperText('Valem quando o Copiloto IA usa este modelo como base. Para tom, persona e regras que valem para todas as mensagens, use "Comportamento do Copiloto IA".')
                            ->rows(4)
                            ->maxLength(1500)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (?MensagemWhatsappTemplate $record): bool => blank($record?->instrucoes_ia))
                    ->columnSpanFull(),
            ]);
    }
}
