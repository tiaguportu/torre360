<?php

namespace App\Filament\Resources\Interessados\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoricosRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';

    protected static ?string $title = 'Histórico de Contatos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo_contato_interessado_id')
                    ->label('Tipo de Contato')
                    ->relationship('tipoContato', 'nome')
                    ->required(),
                DateTimePicker::make('data_contato')
                    ->label('Data do Contato')
                    ->default(now())
                    ->required(),
                Textarea::make('relato')
                    ->label('Relato do Atendimento')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('relato')
            ->columns([
                TextColumn::make('tipoContato.nome')
                    ->label('Tipo'),
                TextColumn::make('data_contato')
                    ->label('Data')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('relato')
                    ->label('Relato')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar Contato'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
