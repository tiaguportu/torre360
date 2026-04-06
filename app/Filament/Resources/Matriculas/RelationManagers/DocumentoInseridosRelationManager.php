<?php

namespace App\Filament\Resources\Matriculas\RelationManagers;

use App\Filament\Resources\DocumentoInseridos\Schemas\DocumentoInseridoForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentoInseridosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentoInseridos';

    protected static ?string $title = 'Documentos';

    public function form(Schema $schema): Schema
    {
        return DocumentoInseridoForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('documentoObrigatorio.nome')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('situacao.nome')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aprovado' => 'success',
                        'Recusado' => 'danger',
                        'Pendente' => 'warning',
                        'Em Análise' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Envio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
