<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Tables;

use App\Services\Educacenso\EducacensoInstituicaoExporter;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class InstituicaoEnsinosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->circular(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cnpj')
                    ->searchable(),
                TextColumn::make('codigo_inep')
                    ->label('Código INEP')
                    ->searchable(),
                IconColumn::make('flag_ativo')
                    ->label('Ativo')
                    ->boolean(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportarEducacenso')
                        ->label('Exportar para Educacenso')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Exportar para Educacenso')
                        ->modalDescription('Será gerado um arquivo .txt com os registros 00, 10, 20, 30, 40, 50 e 60 no padrão INEP para as instituições selecionadas. O processo pode demorar alguns segundos dependendo do volume de dados.')
                        ->modalSubmitActionLabel('Gerar Arquivo')
                        ->action(function (Collection $records) {
                            $exporter = new EducacensoInstituicaoExporter;
                            $content = $exporter->export($records);

                            $filename = 'educacenso_completo_'.date('Ymd_His').'.txt';

                            return response()->streamDownload(
                                fn () => print ($content),
                                $filename,
                                ['Content-Type' => 'text/plain; charset=UTF-8']
                            );
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->can('ViewAny:InstituicaoEnsino')),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
