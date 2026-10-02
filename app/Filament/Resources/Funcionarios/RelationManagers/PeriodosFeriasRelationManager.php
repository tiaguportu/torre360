<?php

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Enums\StatusPeriodoFerias;
use App\Models\PeriodoFerias;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeriodosFeriasRelationManager extends RelationManager
{
    protected static string $relationship = 'periodosFerias';

    protected static ?string $title = 'Férias';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('periodo_aquisitivo_inicio')
                    ->label('Início do Período Aquisitivo')
                    ->native(false)
                    ->required(),
                DatePicker::make('periodo_aquisitivo_fim')
                    ->label('Fim do Período Aquisitivo')
                    ->native(false)
                    ->required(),
                TextInput::make('dias_direito')
                    ->label('Dias de Direito')
                    ->numeric()
                    ->default(30)
                    ->minValue(1)
                    ->maxValue(30)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('periodo_aquisitivo_inicio')
            ->defaultSort('periodo_aquisitivo_inicio', 'desc')
            ->columns([
                TextColumn::make('periodo_aquisitivo_inicio')
                    ->label('Período Aquisitivo')
                    ->date('d/m/Y')
                    ->formatStateUsing(fn (PeriodoFerias $record) => $record->periodo_aquisitivo_inicio->format('d/m/Y').' – '.$record->periodo_aquisitivo_fim->format('d/m/Y')),
                TextColumn::make('dias_direito')
                    ->label('Dias de Direito'),
                TextColumn::make('dias_gozados')
                    ->label('Dias Gozados'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo Período'),
            ])
            ->actions([
                Action::make('registrar_gozo')
                    ->label('Registrar Gozo')
                    ->icon('heroicon-o-sun')
                    ->color('success')
                    ->visible(fn (PeriodoFerias $record): bool => $record->status !== StatusPeriodoFerias::Gozado)
                    ->schema([
                        DatePicker::make('data_inicio_gozo')
                            ->label('Início do Gozo')
                            ->native(false)
                            ->required(),
                        DatePicker::make('data_fim_gozo')
                            ->label('Fim do Gozo')
                            ->native(false)
                            ->required(),
                        TextInput::make('dias')
                            ->label('Dias Gozados Nesse Período')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function (array $data, PeriodoFerias $record): void {
                        $diasGozados = $record->dias_gozados + (int) $data['dias'];

                        $record->update([
                            'data_inicio_gozo' => $data['data_inicio_gozo'],
                            'data_fim_gozo' => $data['data_fim_gozo'],
                            'dias_gozados' => $diasGozados,
                            'status' => $diasGozados >= $record->dias_direito ? StatusPeriodoFerias::Gozado : StatusPeriodoFerias::Parcial,
                        ]);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
