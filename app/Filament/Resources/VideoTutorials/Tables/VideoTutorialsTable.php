<?php

namespace App\Filament\Resources\VideoTutorials\Tables;

use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideoTutorialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('categoria')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('duracao_formatada')
                    ->label('Duração')
                    ->toggleable(),
                IconColumn::make('ativo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('ordem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('assistir')
                    ->label('Assistir')
                    ->icon('heroicon-o-play-circle')
                    ->color('info')
                    ->modalHeading(fn (VideoTutorial $record) => $record->titulo)
                    ->modalContent(fn (VideoTutorial $record) => view('filament.components.video-player', ['video' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('3xl')
                    ->visible(fn (VideoTutorial $record) => $record->arquivo || $record->url_externo),
                Action::make('baixarOuAbrir')
                    ->label(fn (VideoTutorial $record) => $record->arquivo ? 'Baixar' : 'Abrir Link')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (VideoTutorial $record) => $record->url_assistir)
                    ->openUrlInNewTab()
                    ->visible(fn (VideoTutorial $record) => $record->arquivo || $record->url_externo),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('ordem')
            ->stackedOnMobile();
    }
}
