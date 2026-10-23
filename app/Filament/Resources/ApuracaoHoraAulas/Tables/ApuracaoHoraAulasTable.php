<?php

namespace App\Filament\Resources\ApuracaoHoraAulas\Tables;

use App\Models\ApuracaoHoraAula;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApuracaoHoraAulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('competencia')
                    ->label('Competência')
                    ->weight('bold')
                    ->sortable(['ano', 'mes']),

                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_aulas_previstas')
                    ->label('Previstas')
                    ->alignCenter(),

                TextColumn::make('total_aulas_ministradas')
                    ->label('Ministradas')
                    ->alignCenter()
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('total_aulas_reposicao')
                    ->label('Reposições')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('total_aulas_faltadas')
                    ->label('Faltas')
                    ->alignCenter()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('valor_hora_aula')
                    ->label('Hora-Aula')
                    ->money('BRL')
                    ->alignRight()
                    ->toggleable(),

                TextColumn::make('valor_total_bruto')
                    ->label('Total Bruto')
                    ->money('BRL')
                    ->alignRight()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'homologado' => 'success',
                        'conferido' => 'info',
                        'contestado' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'homologado' => 'Homologado',
                        'conferido' => 'Conferido',
                        'contestado' => 'Contestado',
                        'aberto' => 'Em Aberto',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('ano')
                    ->label('Ano')
                    ->options([
                        2025 => '2025',
                        2026 => '2026',
                        2027 => '2027',
                    ]),

                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'aberto' => 'Em Aberto',
                        'conferido' => 'Conferido',
                        'homologado' => 'Homologado',
                        'contestado' => 'Contestado',
                    ]),
            ])
            ->actions([
                Action::make('homologar')
                    ->label('Homologar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ApuracaoHoraAula $record): bool => $record->status !== 'homologado')
                    ->action(function (ApuracaoHoraAula $record): void {
                        $record->update([
                            'status' => 'homologado',
                            'conferido_em' => now(),
                        ]);

                        Notification::make()
                            ->title('Apuração homologada com sucesso para a folha.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->stackedOnMobile();
    }
}
