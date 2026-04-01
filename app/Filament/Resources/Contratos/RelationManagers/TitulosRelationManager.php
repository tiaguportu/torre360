<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class TitulosRelationManager extends RelationManager
{
    protected static string $relationship = 'titulos';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\DatePicker::make('vencimento')
                    ->required(),
                Forms\Components\TextInput::make('valor')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'pago' => 'Pago',
                        'vencido' => 'Vencido',
                        'cancelado' => 'Cancelado',
                    ])
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vencimento')
                    ->date(),
                Tables\Columns\TextColumn::make('valor')
                    ->money('BRL'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'pago' => 'success',
                        'vencido' => 'danger',
                        'cancelado' => 'gray',
                        default => 'primary',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
