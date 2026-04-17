<?php

namespace App\Filament\Resources\Matriculas\RelationManagers;

use App\Filament\Resources\DocumentoInseridos\Schemas\DocumentoInseridoForm;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                Tables\Columns\TextColumn::make('tipoDocumento.nome')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
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
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($record) {
                        $extension = pathinfo($record->arquivo_path, PATHINFO_EXTENSION);
                        $studentName = $record->matricula?->pessoa?->nome ?? 'aluno';
                        $typeName = $record->tipoDocumento?->nome ?? 'documento';

                        $safeStudentName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $studentName);
                        $safeTypeName = Str::replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $typeName);
                        $codigo = $record->matricula?->codigo ?? '000';

                        $newFileName = "{$codigo} - {$safeStudentName} - {$safeTypeName}.{$extension}";

                        return Storage::disk('public')->download($record->arquivo_path, $newFileName);
                    }),
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
