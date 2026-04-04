<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Models\Matricula;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bg-danger-500/10 dark:bg-danger-500/20' : null)
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bold' : null)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : null),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('situacaoMatricula.nome')
                    ->label('Situação')
                    ->sortable(),
                TextColumn::make('data_matricula')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('inserir_documentos')
                    ->label('Documentos')
                    ->tooltip('Gerenciar Documentos Obrigatórios')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : 'primary')
                    ->badge(fn (Matricula $record) => $record->getMissingMandatoryDocumentsCount() ?: null)
                    ->badgeColor('danger')
                    ->url(fn (Matricula $record) => DocumentosMatricula::getUrl(['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
