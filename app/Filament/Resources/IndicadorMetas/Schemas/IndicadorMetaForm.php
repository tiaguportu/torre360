<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicadorMetas\Schemas;

use App\Contracts\IndicadorDoPainel;
use App\Models\IndicadorMeta;
use App\Models\Unidade;
use App\Services\Painel\PainelDirecaoService;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class IndicadorMetaForm
{
    /**
     * @return array<string, string> chave => "Grupo — Título"
     */
    public static function opcoesDeIndicador(): array
    {
        $opcoes = [];

        foreach (app(PainelDirecaoService::class)->indicadores() as $indicador) {
            /** @var IndicadorDoPainel $indicador */
            $opcoes[$indicador->chave()] = $indicador->grupo().' — '.$indicador->titulo();
        }

        return $opcoes;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Meta')
                ->columns(2)
                ->schema([
                    Select::make('indicador')
                        ->label('Indicador')
                        ->options(fn (): array => self::opcoesDeIndicador())
                        ->native(false)
                        ->searchable()
                        ->required()
                        ->rules([
                            fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $existe = IndicadorMeta::query()
                                    ->where('indicador', $value)
                                    ->where('ano', (int) $get('ano'))
                                    ->where('unidade_id', (int) ($get('unidade_id') ?? 0))
                                    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($existe) {
                                    $fail('Já existe uma meta deste indicador para o mesmo ano e unidade.');
                                }
                            },
                        ])
                        ->columnSpanFull(),
                    TextInput::make('ano')
                        ->label('Ano')
                        ->numeric()
                        ->minValue(2020)
                        ->maxValue(2100)
                        ->default((int) now()->year)
                        ->required(),
                    Select::make('unidade_id')
                        ->label('Unidade')
                        ->options(fn (): array => Unidade::query()->orderBy('nome')->pluck('nome', 'id')->all())
                        ->placeholder('Escola toda')
                        ->native(false)
                        ->helperText('Só indicadores que existem por unidade usam a meta da unidade; sem ela, vale a da escola toda.'),
                    TextInput::make('meta')
                        ->label('Meta')
                        ->numeric()
                        ->required()
                        ->helperText('No mesmo formato do indicador: número de alunos, valor em reais ou percentual (sem o símbolo %).'),
                    TextInput::make('tolerancia_percentual')
                        ->label('Tolerância (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(10)
                        ->required()
                        ->helperText('Faixa amarela: quanto o valor pode ficar pior que a meta, em % da meta, antes de ficar vermelho.'),
                    TextInput::make('observacao')->label('Observação')->maxLength(255)->columnSpanFull(),
                ]),
        ]);
    }
}
