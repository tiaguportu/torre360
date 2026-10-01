<?php

namespace App\Filament\Resources\PeriodoRematriculas\Schemas;

use App\Models\PeriodoLetivo;
use App\Models\TemplateContrato;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PeriodoRematriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuração da Campanha de Rematrícula')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome da Campanha')
                            ->placeholder('Ex: Rematrícula 2027')
                            ->required()
                            ->maxLength(255),

                        Select::make('periodo_letivo_origem_id')
                            ->label('Período Letivo Vigente (Origem)')
                            ->options(PeriodoLetivo::pluck('nome', 'id'))
                            ->required()
                            ->helperText('Estudantes com matrícula ativa neste período serão elegíveis para rematrícula.'),

                        Select::make('periodo_letivo_destino_id')
                            ->label('Período Letivo Alvo (Destino)')
                            ->options(PeriodoLetivo::pluck('nome', 'id'))
                            ->required()
                            ->helperText('Período para o qual as novas matrículas serão geradas.'),

                        Select::make('template_contrato_id')
                            ->label('Modelo de Contrato para o Novo Período')
                            ->options(TemplateContrato::pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Modelo que será gerado automaticamente após a confirmação dos pais.'),

                        DatePicker::make('data_inicio')
                            ->label('Data de Abertura')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        DatePicker::make('data_fim')
                            ->label('Data de Encerramento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        TextInput::make('valor_taxa')
                            ->label('Valor Total do Novo Contrato (R$)')
                            ->numeric()
                            ->default(0)
                            ->prefix('R$')
                            ->helperText('Valor total cobrado no novo contrato gerado pela rematrícula.'),

                        TextInput::make('quantidade_parcelas_padrao')
                            ->label('Quantidade de Parcelas')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(12)
                            ->required()
                            ->helperText('As faturas são geradas automaticamente ao confirmar a rematrícula, com esse parcelamento.'),

                        TextInput::make('valor_entrada_padrao')
                            ->label('Valor de Entrada (R$)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('R$')
                            ->helperText('Informe 0 caso não haja entrada.'),

                        Toggle::make('is_ativo')
                            ->label('Campanha Ativa')
                            ->default(true)
                            ->helperText('Define se o banner e o formulário de rematrícula ficam disponíveis no Portal da Família.'),

                        Textarea::make('mensagem_orientacao')
                            ->label('Mensagem e Orientações para os Pais no Portal')
                            ->placeholder('Ex: Prezada família, o período de rematrícula antecipada com condições especiais está aberto. Confirme os dados cadastrais e garanta a vaga do seu filho.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
