<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicacaoInteressados\Tables;

use App\Models\IndicacaoInteressado;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IndicacaoInteressadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('indicador.nome')
                    ->label('Quem Indicou')
                    ->searchable()
                    ->sortable()
                    ->description(fn (IndicacaoInteressado $record) => $record->codigo_indicacao ? 'Código: '.$record->codigo_indicacao : null)
                    ->weight('bold'),

                TextColumn::make('interessado.pessoa.nome')
                    ->label('Lead Indicado')
                    ->searchable()
                    ->sortable()
                    ->description(fn (IndicacaoInteressado $record) => $record->interessado?->pessoa?->telefone),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'matriculado' => 'info',
                        'recompensado' => 'success',
                        'cancelado' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'matriculado' => '🎉 Matriculado (Elegível)',
                        'recompensado' => '✅ Recompensado',
                        'cancelado' => '❌ Cancelado',
                        default => '🟡 Em Negociação',
                    }),

                TextColumn::make('recompensa_detalhe')
                    ->label('Recompensa / Bônus')
                    ->placeholder('A definir')
                    ->limit(35),

                TextColumn::make('valor_recompensa')
                    ->label('Valor (R$)')
                    ->money('BRL')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Data Indicação')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_conversao')
                    ->label('Data Matrícula')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'pendente' => 'Em Negociação',
                        'matriculado' => 'Matriculado (Aguardando Recompensa)',
                        'recompensado' => 'Recompensado',
                        'cancelado' => 'Cancelado',
                    ]),
            ])
            ->actions([
                Action::make('marcarRecompensado')
                    ->label('Conceder Recompensa')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (IndicacaoInteressado $record) => $record->status === IndicacaoInteressado::STATUS_MATRICULADO)
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Concessão de Recompensa')
                    ->modalDescription('Confirma que o benefício / desconto foi lançado no financeiro para a família indicadora?')
                    ->action(function (IndicacaoInteressado $record) {
                        $record->marcarRecompensado(auth()->user());

                        Notification::make()
                            ->title('Recompensa concedida com sucesso!')
                            ->success()
                            ->send();
                    }),

                Action::make('linkIndicacao')
                    ->label('Link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->tooltip('Copiar link de indicação da família')
                    ->action(function (IndicacaoInteressado $record) {
                        $url = $record->indicador?->linkIndicacao() ?? url('/quero-matricular');

                        Notification::make()
                            ->title('Link de indicação da família:')
                            ->body($url)
                            ->info()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->stackedOnMobile();
    }
}
