<?php

namespace App\Filament\Resources\Interessados\Tables;

use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InteressadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Interessado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pessoa.telefone')
                    ->label('Telefone')
                    ->searchable(),
                TextColumn::make('usuario.name')
                    ->label('Consultor')
                    ->searchable(),
                TextColumn::make('origem.nome')
                    ->label('Origem')
                    ->sortable(),
                TextColumn::make('status.nome')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state, $record) => $record->status?->cor ?? 'gray')
                    ->sortable(),
                TextColumn::make('data_proximo_contato')
                    ->label('Prox. Contato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'nome'),
                SelectFilter::make('origem')
                    ->relationship('origem', 'nome'),
            ])
            ->actions([
                Action::make('registrarAtendimento')
                    ->label('Atendimento')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->modalHeading('Registrar Atendimento')
                    ->form([
                        Select::make('tipo_contato_interessado_id')
                            ->label('Tipo de Contato')
                            ->options(TipoContatoInteressado::pluck('nome', 'id'))
                            ->required(),
                        Textarea::make('relato')
                            ->label('Relato')
                            ->required(),
                        DateTimePicker::make('data_proximo_contato')
                            ->label('Data Próximo Contato')
                            ->default(now()->addDays(2)),
                    ])
                    ->action(function (array $data, Interessado $record) {
                        $record->historicos()->create([
                            'tipo_contato_interessado_id' => $data['tipo_contato_interessado_id'],
                            'relato' => $data['relato'],
                            'data_contato' => now(),
                        ]);

                        $record->update([
                            'data_proximo_contato' => $data['data_proximo_contato'],
                        ]);

                        Notification::make()
                            ->title('Atendimento registrado com sucesso!')
                            ->success()
                            ->send();
                    }),

                Action::make('finalizarMatricula')
                    ->label('Matricular')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status?->nome !== 'Matriculado')
                    ->action(function (Interessado $record) {
                        $statusMatriculado = StatusInteressado::where('nome', 'Matriculado')->first();

                        $record->update([
                            'status_interessado_id' => $statusMatriculado?->id,
                        ]);

                        Notification::make()
                            ->title('Matrícula finalizada!')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
