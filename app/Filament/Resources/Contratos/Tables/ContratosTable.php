<?php

namespace App\Filament\Resources\Contratos\Tables;

use App\Services\AssinafyService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                Action::make('enviar_assinafy')
                    ->label('Enviar Assinafy')
                    ->icon('heroicon-o-document-check')
                    ->color('warning')
                    ->hidden(fn ($record) => $record->assinafy_status === 'signed' || $record->assinafy_status === 'completed')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $service = app(AssinafyService::class);
                        if ($service->enviarContrato($record)) {
                            Notification::make()
                                ->title('Contrato enviado com sucesso!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Erro ao enviar contrato.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
