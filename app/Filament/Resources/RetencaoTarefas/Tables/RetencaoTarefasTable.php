<?php

namespace App\Filament\Resources\RetencaoTarefas\Tables;

use App\Enums\StatusRetencaoTarefa;
use App\Models\RetencaoTarefa;
use App\Services\RiscoEvasaoService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RetencaoTarefasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('aluno.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (RetencaoTarefa $record): string => "Matrícula #{$record->matricula_id}"),

                TextColumn::make('score_risco')
                    ->label('Score de Risco')
                    ->badge()
                    ->color(fn (RetencaoTarefa $record): string => RiscoEvasaoService::cor($record->score_risco))
                    ->formatStateUsing(fn ($state): string => "{$state}%")
                    ->sortable(),

                TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critica' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),

                TextColumn::make('rematricula.status')
                    ->label('Fluxo de Rematrícula')
                    ->badge()
                    ->placeholder('Não iniciada')
                    ->description(fn (RetencaoTarefa $record): ?string => $record->rematricula?->periodoRematricula?->nome),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),

                TextColumn::make('responsavel.name')
                    ->label('Responsável')
                    ->placeholder('Não atribuído')
                    ->searchable(),

                TextColumn::make('alertado_coordenacao_em')
                    ->label('Alertado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusRetencaoTarefa::class),

                Filter::make('alto_risco')
                    ->label('Risco Crítico (≥ 80%)')
                    ->query(fn (Builder $query): Builder => $query->where('score_risco', '>=', 80)),

                Filter::make('com_rematricula')
                    ->label('Com Rematrícula Registrada')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('rematricula_id')),
            ])
            ->recordActions([
                Action::make('iniciar')
                    ->label('Iniciar')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (RetencaoTarefa $record): bool => $record->status === StatusRetencaoTarefa::Pendente)
                    ->action(function (RetencaoTarefa $record): void {
                        $record->update([
                            'status' => StatusRetencaoTarefa::EmAndamento,
                            'responsavel_user_id' => $record->responsavel_user_id ?? auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Acompanhamento iniciado')
                            ->body('A tarefa agora está em andamento sob sua responsabilidade.')
                            ->success()
                            ->send();
                    }),

                Action::make('concluir')
                    ->label('Concluir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RetencaoTarefa $record): bool => in_array($record->status, [StatusRetencaoTarefa::Pendente, StatusRetencaoTarefa::EmAndamento]))
                    ->form([
                        Textarea::make('observacoes_resolucao')
                            ->label('Parecer da Ação de Retenção')
                            ->placeholder('Descreva as ações pedagógicas, acordos financeiros ou desfecho com a família...')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (RetencaoTarefa $record, array $data): void {
                        $record->update([
                            'status' => StatusRetencaoTarefa::Concluido,
                            'observacoes_resolucao' => $data['observacoes_resolucao'],
                            'resolvido_em' => now(),
                        ]);

                        Notification::make()
                            ->title('Acompanhamento concluído')
                            ->body('Ação de retenção finalizada com sucesso.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->stackedOnMobile();
    }
}
