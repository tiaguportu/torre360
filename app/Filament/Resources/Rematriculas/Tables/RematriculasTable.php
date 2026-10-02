<?php

namespace App\Filament\Resources\Rematriculas\Tables;

use App\Enums\StatusRematricula;
use App\Models\Rematricula;
use App\Services\RematriculaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RematriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matriculaOrigem.pessoa.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('periodoRematricula.nome')
                    ->label('Campanha')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serieDestino.nome')
                    ->label('Série Pretendida')
                    ->placeholder('Não selecionada')
                    ->sortable(),

                TextColumn::make('turmaDestino.nome')
                    ->label('Turma Destino')
                    ->placeholder('A definir')
                    ->sortable(),

                TextColumn::make('turnoPretendido.nome')
                    ->label('Turno')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('novaMatricula.id')
                    ->label('Nova Matrícula')
                    ->badge()
                    ->color('success')
                    ->placeholder('Pendente'),

                TextColumn::make('data_confirmacao')
                    ->label('Confirmada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusRematricula::class),

                SelectFilter::make('periodo_rematricula_id')
                    ->label('Campanha')
                    ->relationship('periodoRematricula', 'nome'),
            ])
            ->recordActions([
                Action::make('efetivar')
                    ->label('Efetivar Rematrícula')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Efetivar Rematrícula do Estudante')
                    ->modalDescription('Esta ação criará a nova matrícula definitiva para o próximo período letivo e gerará o contrato correspondente.')
                    ->visible(fn (Rematricula $record) => $record->status !== StatusRematricula::Confirmada && ! $record->nova_matricula_id)
                    ->action(function (Rematricula $record, RematriculaService $service) {
                        try {
                            $novaMatricula = $service->efetivar($record);

                            Notification::make()
                                ->title('Rematrícula Efetivada')
                                ->body("A nova Matrícula #{$novaMatricula->id} foi gerada com sucesso para o aluno {$record->matriculaOrigem?->pessoa?->nome}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao efetivar rematrícula')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('ver_contrato')
                    ->label('Ver Contrato')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->visible(fn (Rematricula $record) => ! empty($record->contrato_id))
                    ->url(fn (Rematricula $record) => route('contratos.visualizar', $record->contrato_id))
                    ->openUrlInNewTab(),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
