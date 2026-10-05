<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concorrentes\Schemas;

use App\Models\Concorrente;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConcorrenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema([
                        Section::make('Identificação da Escola Concorrente')
                            ->description('Dados cadastrais e localização da instituição')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome da Escola Concorrente')
                                            ->placeholder('Ex: Colégio Santo Agostinho')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('sigla')
                                            ->label('Sigla / Nome Curto')
                                            ->placeholder('Ex: CSA')
                                            ->maxLength(30),
                                    ]),

                                Grid::make(3)
                                    ->schema([
                                        Select::make('cidade_id')
                                            ->label('Cidade')
                                            ->relationship('cidade', 'nome')
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('bairro')
                                            ->label('Bairro / Região')
                                            ->placeholder('Ex: Jardins'),

                                        TextInput::make('proposta_pedagogica')
                                            ->label('Linha Pedagógica Deles')
                                            ->placeholder('Ex: Tradicional, Bilíngue, Construtivista'),
                                    ]),

                                Toggle::make('is_ativo')
                                    ->label('Concorrente Ativo no Radar')
                                    ->default(true)
                                    ->helperText('Concorrentes inativos deixam de aparecer nas opções rápidas de descarte de leads.'),
                            ]),

                        Section::make('Posicionamento Financeiro')
                            ->description('Faixa de mensalidade praticada no mercado')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('faixa_preco')
                                            ->label('Faixa de Mensalidade vs. Nossa Escola')
                                            ->options(Concorrente::FAIXAS_PRECO)
                                            ->placeholder('Selecione o posicionamento'),

                                        TextInput::make('mensalidade_estimada')
                                            ->label('Valor Estimado da Mensalidade (R$)')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->minValue(0),
                                    ]),
                            ]),

                        Section::make('🛡️ Battlecard Comercial (Inteligência Competitiva)')
                            ->description('Diferenciais, pontos fortes e argumentos matadores para os consultores fecharem a matrícula')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TagsInput::make('pontos_fortes')
                                            ->label('Pontos Fortes Deles (O que a família elogia?)')
                                            ->placeholder('Adicionar item (Enter)')
                                            ->helperText('Ex: Piscina semiolímpica, Tradição de 50 anos, Carga horária ampliada'),

                                        TagsInput::make('pontos_fracos')
                                            ->label('Vulnerabilidades Deles (Onde eles pecam?)')
                                            ->placeholder('Adicionar item (Enter)')
                                            ->helperText('Ex: Salas cheias (35 alunos), Rotatividade docente, Pouco acolhimento individual'),
                                    ]),

                                Textarea::make('diferenciais_nossos')
                                    ->label('Nossos Diferenciais Matadores (Por que escolher a nossa escola?)')
                                    ->placeholder("Ex:\n• Turmas reduzidas com atendimento individualizado por preceptor\n• Metodologia ativa com robótica integrada sem custo extra\n• Acolhimento socioemocional diário com a família")
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Textarea::make('estrategia_abordagem')
                                    ->label('Roteiro Estratégico & Postura Recomendada')
                                    ->placeholder("Ex: Nunca desqualifique a outra escola. Diga: 'O Colégio X tem uma bela estrutura física, mas aqui nosso foco é a formação personalizada do Lucas. Como vocês avaliam o acompanhamento individual?'")
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Textarea::make('observacoes')
                                    ->label('Anotações Gerais Internas')
                                    ->placeholder('Informações complementares sobre convênios, bolsas concedidas por eles, etc.')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
