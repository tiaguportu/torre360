<?php

namespace App\Filament\Resources\DiaNaoLetivos\Schemas;

use App\Models\DiaNaoLetivo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class DiaNaoLetivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('periodo_letivo_id')
                    ->label('Período Letivo')
                    ->relationship('periodoLetivo', 'nome')
                    ->required()
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager),

                DatePicker::make('data')
                    ->label(fn ($get) => $get('data_fim') ? 'Data de Início' : 'Data')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if (! $state) {
                            return;
                        }

                        $date = Carbon::parse($state);
                        $descricao = null;

                        if ($date->isSaturday()) {
                            $descricao = 'Sábado';
                        } elseif ($date->isSunday()) {
                            $descricao = 'Domingo';
                        } else {
                            $feriado = DiaNaoLetivo::getFeriadoNacional($date);
                            if ($feriado) {
                                $descricao = $feriado;
                            }
                        }

                        if ($descricao) {
                            $set('descricao', $descricao);
                        }
                    }),

                DatePicker::make('data_fim')
                    ->label('Data de Fim (opcional)')
                    ->helperText('Se informado, criará um registro para cada dia entre a Data de Início e esta data.')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(fn ($get) => $get('data'))
                    ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                    ->live()
                    ->dehydrated(false),

                TextInput::make('descricao')
                    ->label('Descrição')
                    ->required(),

                Toggle::make('flag_ativo')
                    ->label('Ativo')
                    ->default(true)
                    ->required(),
            ]);
    }
}
