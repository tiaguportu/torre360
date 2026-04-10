<?php

namespace App\Filament\Resources\Contratos\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matriculas.pessoa.nome')
                    ->label('Alunos / Matrículas')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('responsaveisFinanceiros.pessoa.nome')
                    ->label('Responsáveis Financeiros')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('valor_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('data_aceite')
                    ->date()
                    ->sortable(),
                TextColumn::make('assinafy_status')
                    ->label('Assinatura')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'signed', 'completed' => 'success',
                        'enviado', 'pending' => 'warning',
                        'erro_envio', 'rejected', 'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'signed', 'completed' => 'Assinado',
                        'enviado', 'pending' => 'Pendente',
                        'erro_envio' => 'Erro no Envio',
                        'rejected' => 'Recusado',
                        'canceled' => 'Cancelado',
                        default => ucfirst($state),
                    })
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
                SelectFilter::make('aluno')
                    ->relationship('matriculas.pessoa', 'nome')
                    ->multiple()
                    ->label('Filtrar por Aluno')
                    ->searchable(),
                SelectFilter::make('responsavel')
                    ->relationship('responsaveisFinanceiros.pessoa', 'nome')
                    ->multiple()
                    ->label('Filtrar por Responsável')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('visualizar_contrato')
                    ->label(fn ($record) => in_array($record->assinafy_status, ['signed', 'completed']) ? 'Ver Contrato Assinado' : 'Assinar Contrato')
                    ->icon(fn ($record) => in_array($record->assinafy_status, ['signed', 'completed']) ? 'heroicon-o-document-magnifying-glass' : 'heroicon-o-document-check')
                    ->color(fn ($record) => in_array($record->assinafy_status, ['signed', 'completed']) ? 'success' : 'warning')
                    ->url(fn ($record) => route('contratos.visualizar', $record))
                    ->openUrlInNewTab(),
                Action::make('download_contrato')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => route('contratos.download', $record))
                    ->visible(fn ($record) => $record->assinafy_id !== null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
