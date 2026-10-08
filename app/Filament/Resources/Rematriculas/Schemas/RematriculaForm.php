<?php

namespace App\Filament\Resources\Rematriculas\Schemas;

use App\Enums\StatusRematricula;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\Turno;
use App\Services\RematriculaService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RematriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Rematrícula')
                    ->schema([
                        Select::make('status')
                            ->label('Status da Rematrícula')
                            ->options(StatusRematricula::class)
                            ->required(),

                        Select::make('serie_destino_id')
                            ->label('Série / Ano de Destino')
                            ->options(Serie::pluck('nome', 'id'))
                            ->searchable()
                            ->preload(),

                        Select::make('turma_destino_id')
                            ->label('Turma de Destino')
                            ->options(function (?Rematricula $record, RematriculaService $service): array {
                                if (! $record) {
                                    return [];
                                }

                                $periodo = PeriodoRematricula::find($record->periodo_rematricula_id);

                                return $periodo ? $service->opcoesDeTurma($periodo, $record->serie_destino_id)['opcoes'] : [];
                            })
                            ->searchable()
                            ->helperText('Só turmas do período de destino da campanha, abertas para matrícula. A turma também é escolhida ao efetivar a rematrícula.'),

                        Select::make('turno_pretendido_id')
                            ->label('Turno Pretendido')
                            ->options(Turno::pluck('nome', 'id')),

                        Textarea::make('observacoes')
                            ->label('Observações da Secretaria / Família')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
