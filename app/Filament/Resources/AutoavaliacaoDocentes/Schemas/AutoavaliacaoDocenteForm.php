<?php

declare(strict_types=1);

namespace App\Filament\Resources\AutoavaliacaoDocentes\Schemas;

use App\Models\AutoavaliacaoDocente;
use App\Models\CriterioAvaliacaoDocente;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AutoavaliacaoDocenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Autoavaliação')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('professor')
                            ->label('Professor')
                            ->content(fn (?AutoavaliacaoDocente $record): string => $record?->funcionario?->pessoa?->nome ?? '—'),
                        Placeholder::make('ciclo_nome')
                            ->label('Ciclo')
                            ->content(fn (?AutoavaliacaoDocente $record): string => $record?->ciclo?->nome ?? '—'),
                        Placeholder::make('situacao')
                            ->label('Situação')
                            ->content(fn (?AutoavaliacaoDocente $record): string => $record?->status?->getLabel() ?? '—'),
                        Placeholder::make('pontuacao')
                            ->label('Pontuação')
                            ->content(fn (?AutoavaliacaoDocente $record): string => $record?->pontuacao_percentual !== null
                                ? number_format((float) $record->pontuacao_percentual, 1, ',', '.').'%'
                                : 'Calculada ao enviar'),
                    ]),
                Section::make('Critérios')
                    ->description('Dê a si mesmo uma nota em cada critério, do mesmo instrumento que a coordenação usa. Seja realista: a comparação com as observações serve para planejar o seu desenvolvimento. Notas e observações ficam guardadas cifradas.')
                    ->schema([
                        Repeater::make('notas')
                            ->relationship('notas')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                                $criterio = CriterioAvaliacaoDocente::find($data['criterio_id'] ?? null);

                                $data['criterio_nome'] = $criterio?->nome ?? '—';
                                $data['criterio_descricao'] = $criterio?->descricao;

                                return $data;
                            })
                            ->columns(3)
                            ->schema([
                                Hidden::make('criterio_id'),
                                TextInput::make('criterio_nome')
                                    ->label('Critério')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                Select::make('nota')
                                    ->label('Minha nota')
                                    ->options(function ($livewire): array {
                                        $escala = (int) ($livewire->getRecord()?->ciclo?->instrumento?->escala_maxima ?? 5);

                                        return collect(range(1, max(1, $escala)))->mapWithKeys(fn (int $n): array => [$n => (string) $n])->all();
                                    })
                                    ->helperText(fn ($livewire): string => '1 = muito abaixo do esperado; '.((int) ($livewire->getRecord()?->ciclo?->instrumento?->escala_maxima ?? 5)).' = excelente.'),
                                Textarea::make('criterio_descricao')
                                    ->label('O que observar')
                                    ->rows(2)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull()
                                    ->visible(fn ($state): bool => filled($state)),
                                Textarea::make('observacao')
                                    ->label('Por que essa nota? (opcional)')
                                    ->rows(2)
                                    ->maxLength(3000)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Comentário')
                    ->schema([
                        Textarea::make('comentario')
                            ->label('Comentário geral')
                            ->helperText('Conquistas, dificuldades e o apoio que você gostaria de ter. Guardado cifrado.')
                            ->rows(5)
                            ->maxLength(10000),
                    ]),
            ]);
    }
}
