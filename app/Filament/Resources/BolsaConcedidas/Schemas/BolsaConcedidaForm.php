<?php

namespace App\Filament\Resources\BolsaConcedidas\Schemas;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Models\TipoBolsa;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BolsaConcedidaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bolsa Concedida')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Aluno')
                            ->options(fn () => Matricula::where('situacao', SituacaoMatricula::ATIVA)->with('pessoa')->get()->mapWithKeys(fn (Matricula $m) => [$m->id => $m->label_exibicao]))
                            ->searchable()
                            ->required(),
                        Select::make('tipo_bolsa_id')
                            ->label('Tipo de Bolsa')
                            ->relationship('tipoBolsa', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        TextInput::make('percentual')
                            ->label('Percentual (%)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText(fn ($get) => ($maximo = TipoBolsa::find($get('tipo_bolsa_id'))?->percentual_maximo)
                                ? "Máximo para este tipo: {$maximo}%"
                                : null),
                        DatePicker::make('data_inicio')
                            ->label('Início da Vigência')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        DatePicker::make('data_fim')
                            ->label('Fim da Vigência')
                            ->native(false)
                            ->helperText('Deixe em branco para vigorar enquanto a matrícula durar.'),
                        Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
