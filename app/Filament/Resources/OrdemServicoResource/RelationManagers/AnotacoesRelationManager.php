<?php

namespace App\Filament\Resources\OrdemServicoResource\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnotacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'anotacoes';

    protected static ?string $title = 'Anotações e Acompanhamento';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('texto')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('fotos')
                    ->multiple()
                    ->image()
                    ->directory('anotacoes-os')
                    ->columnSpanFull(),
                Hidden::make('user_id')
                    ->default(auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('texto')
            ->columns([
                TextColumn::make('texto')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Usuário'),
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
