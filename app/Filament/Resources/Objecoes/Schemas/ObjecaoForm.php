<?php

declare(strict_types=1);

namespace App\Filament\Resources\Objecoes\Schemas;

use App\Models\Objecao;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ObjecaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema([
                        Section::make('Classificação da Objeção')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('titulo')
                                            ->label('Título da Objeção')
                                            ->placeholder('Ex: Mensalidade / Preço Elevado')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        Select::make('categoria')
                                            ->label('Categoria')
                                            ->options(Objecao::CATEGORIAS)
                                            ->required()
                                            ->default('preco'),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('ordem')
                                            ->label('Ordem de Exibição')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Objeções com menor número aparecem no topo da matriz.'),

                                        Toggle::make('is_ativo')
                                            ->label('Objeção Ativa na Matriz')
                                            ->default(true),
                                    ]),
                            ]),

                        Section::make('Roteiro Comercial & Argumentos de Valor')
                            ->description('Como acolher a família e responder com autoridade e empatia')
                            ->schema([
                                Textarea::make('descricao')
                                    ->label('Como a família verbaliza essa dúvida?')
                                    ->placeholder("Ex: 'Gostei muito da escola, mas achei o valor da mensalidade um pouco salgado para o nosso orçamento deste ano...'")
                                    ->required()
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Textarea::make('resposta_sugerida')
                                    ->label('Roteiro Sugerido (O que o consultor deve falar)')
                                    ->placeholder("Ex:\n'Entendo perfeitamente, Sra. Maria. A educação do Lucas é um investimento muito sério. Quando olhamos para a nossa proposta, o valor já inclui robótica, material de apoio e o acompanhamento diário com o preceptor, sem surpresas no meio do ano. Se compararmos com o que você gastaria contratando esses cursos por fora, nossa solução é mais econômica e segura.'")
                                    ->required()
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Textarea::make('pergunta_virada')
                                    ->label('Pergunta de Ouro (Para virar a conversa)')
                                    ->placeholder("Ex: 'Quando a senhora pensa no desenvolvimento do Lucas para os próximos anos, o que pesa mais: o valor da parcela mensal ou a certeza de que ele terá apoio individual para passar no vestibular?'")
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Textarea::make('dicas_postura')
                                    ->label('Dicas de Postura & Gatilhos Mentais')
                                    ->placeholder("Ex: 'Nunca confronte ou diga que a família está errada. Valide a dor financeira primeiro (acolhimento), demonstre a economia oculta e reforce a segurança do ambiente.'")
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
