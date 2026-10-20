<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos\Tables;

use App\Enums\StatusReservaEspaco;
use App\Exceptions\ReservaEspacoIndisponivelException;
use App\Models\ReservaEspaco;
use App\Services\ReservaEspacoService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservaEspacosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sala.unidade', 'solicitante', 'turma']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('data')->orderBy('hora_inicio'))
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('horario')
                    ->label('Horário')
                    ->state(fn (ReservaEspaco $record): string => $record->intervaloFormatado()),
                TextColumn::make('sala.nome')
                    ->label('Espaço')
                    ->description(fn (ReservaEspaco $record): ?string => $record->sala?->unidade?->nome)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('motivo')
                    ->label('Finalidade')
                    ->limit(40)
                    ->tooltip(fn (ReservaEspaco $record): string => (string) $record->motivo)
                    ->searchable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('solicitante.name')
                    ->label('Solicitante')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
            ])
            ->filters([
                Filter::make('futuras')
                    ->label('Somente reservas de hoje em diante')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereDate('data', '>=', today()->toDateString())),
                Filter::make('minhas')
                    ->label('Somente as minhas reservas')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('solicitante_user_id', auth()->id())),
                SelectFilter::make('sala_id')
                    ->label('Espaço')
                    ->relationship('sala', 'nome')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusReservaEspaco::class),
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('de')->label('De')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('ate')->label('Até')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['de'] ?? null, fn (Builder $q, $de) => $q->whereDate('data', '>=', $de))
                        ->when($data['ate'] ?? null, fn (Builder $q, $ate) => $q->whereDate('data', '<=', $ate))),
            ])
            ->recordActions([
                Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Ao confirmar, o espaço fica reservado neste horário para o solicitante.')
                    ->visible(fn (ReservaEspaco $record): bool => (bool) auth()->user()?->can('confirmar', $record))
                    ->action(function (ReservaEspaco $record): void {
                        try {
                            app(ReservaEspacoService::class)->confirmar($record, auth()->user());
                        } catch (ReservaEspacoIndisponivelException $e) {
                            Notification::make()->title('Não foi possível confirmar')->body(implode("\n", $e->mensagens()))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Reserva confirmada')->success()->send();
                    }),
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Cancelar reserva')
                    ->modalDescription('O horário volta a ficar livre para outras reservas.')
                    ->schema([
                        Textarea::make('justificativa')
                            ->label('Justificativa')
                            ->helperText('Obrigatória ao cancelar ou recusar a reserva de outra pessoa.')
                            ->rows(3),
                    ])
                    ->visible(fn (ReservaEspaco $record): bool => (bool) auth()->user()?->can('cancelar', $record))
                    ->action(function (ReservaEspaco $record, array $data): void {
                        try {
                            app(ReservaEspacoService::class)->cancelar($record, auth()->user(), $data['justificativa'] ?? null);
                        } catch (ReservaEspacoIndisponivelException $e) {
                            Notification::make()->title('Não foi possível cancelar')->body(implode("\n", $e->mensagens()))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Reserva cancelada')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
