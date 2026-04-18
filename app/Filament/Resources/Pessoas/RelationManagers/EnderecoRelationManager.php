<?php

namespace App\Filament\Resources\Pessoas\RelationManagers;

use App\Filament\Resources\Enderecos\Schemas\EnderecoForm;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnderecoRelationManager extends RelationManager
{
    protected static string $relationship = 'enderecos';

    protected static ?string $title = 'Endereço';

    public function form(Schema $schema): Schema
    {
        return EnderecoForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('logradouro')
            ->columns([
                TextColumn::make('tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'residencial' => 'info',
                        'comercial' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('logradouro')
                    ->searchable(),
                TextColumn::make('numero'),
                TextColumn::make('complemento'),
                TextColumn::make('bairro'),
                TextColumn::make('cidade.nome')
                    ->label('Cidade'),
                TextColumn::make('cep'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AttachAction::make()
                    ->authorize('attachEndereco'),
            ])
            ->actions([
                EditAction::make(),
                DetachAction::make()
                    ->authorize('detachEndereco'),
                DeleteAction::make(),
            ]);
    }
}
