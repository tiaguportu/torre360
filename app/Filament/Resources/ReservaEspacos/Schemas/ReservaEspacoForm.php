<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos\Schemas;

use App\Models\ReservaEspaco;
use App\Models\Sala;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ReservaEspacoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reserva')
                    ->columns(2)
                    ->schema([
                        Select::make('sala_id')
                            ->label('Espaço')
                            ->options(function (?Model $record): array {
                                return Sala::query()
                                    ->with('unidade')
                                    ->where(fn ($q) => $q->where('ativa', true)->when($record?->sala_id, fn ($qq) => $qq->orWhere('id', $record->sala_id)))
                                    ->orderBy('nome')
                                    ->get()
                                    ->mapWithKeys(fn (Sala $sala): array => [
                                        $sala->id => $sala->nome
                                            .($sala->unidade ? ' — '.$sala->unidade->nome : '')
                                            .($sala->exige_aprovacao_reserva ? ' (reserva com aprovação)' : ''),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('data')
                            ->label('Data')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (?ReservaEspaco $record) => $record?->data && $record->data->isPast() ? $record->data : today())
                            ->required(),
                        TextInput::make('motivo')
                            ->label('Finalidade')
                            ->placeholder('Ex.: Ensaio de formatura, palestra, aula extra de Educação Física')
                            ->required()
                            ->maxLength(255),
                        TimePicker::make('hora_inicio')
                            ->label('Início')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('hora_fim')
                            ->label('Fim')
                            ->seconds(false)
                            ->required(),
                        Select::make('turma_id')
                            ->label('Turma (opcional)')
                            ->relationship('turma', 'nome', fn ($query, ?ReservaEspaco $record) => $query->vigentes($record?->turma_id))
                            ->searchable()
                            ->preload()
                            ->helperText('Informe quando a reserva é para uma turma específica.'),
                        Placeholder::make('status_atual')
                            ->label('Situação')
                            ->content(fn (?ReservaEspaco $record): string => $record?->status?->getLabel() ?? '—')
                            ->visibleOn('edit'),
                        Textarea::make('descricao')
                            ->label('Detalhes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
