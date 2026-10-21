<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Schemas;

use App\Enums\Alergeno;
use App\Enums\TipoRefeicaoCardapio;
use App\Models\Cardapio;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CardapioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Refeição')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('data')
                            ->label('Data')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required()
                            ->live(),
                        Select::make('tipo_refeicao')
                            ->label('Refeição')
                            ->options(TipoRefeicaoCardapio::class)
                            ->required()
                            ->live()
                            ->rules([
                                fn (Get $get, ?Cardapio $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $existe = Cardapio::query()
                                        ->whereDate('data', (string) $get('data'))
                                        ->where('tipo_refeicao', $value)
                                        ->where('unidade_chave', (int) ($get('unidade_id') ?: 0))
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->exists();

                                    if ($existe) {
                                        $fail('Já existe essa refeição cadastrada nesta data (e unidade). Edite a existente.');
                                    }
                                },
                            ]),
                        Textarea::make('descricao')
                            ->label('Cardápio')
                            ->placeholder('Ex.: Arroz, feijão, frango grelhado, salada de alface e fruta')
                            ->required()
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        CheckboxList::make('alergenos')
                            ->label('Contém (alérgenos declarados)')
                            ->options(Alergeno::class)
                            ->columns(3)
                            ->helperText('Marque tudo o que a refeição contém ou pode conter (inclusive por contaminação cruzada). O sistema usa isto para alertar as famílias e a cozinha.')
                            ->columnSpanFull(),
                        Select::make('unidade_id')
                            ->label('Unidade')
                            ->relationship('unidade', 'nome')
                            ->searchable()
                            ->preload()
                            ->placeholder('Todas as unidades')
                            ->live(),
                        Toggle::make('publicado')
                            ->label('Publicado no Portal da Família')
                            ->default(false)
                            ->disabled(fn (): bool => ! auth()->user()?->can('Publicar:Cardapio'))
                            ->dehydrated(fn (): bool => (bool) auth()->user()?->can('Publicar:Cardapio'))
                            ->helperText('Só quem tem permissão de publicar altera esta opção.'),
                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
