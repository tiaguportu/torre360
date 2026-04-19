<?php

namespace App\Filament\Resources\Avaliacaos\Tables;

use App\Models\Avaliacao;
use App\Models\User;
use App\Notifications\SystemNotification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Notifications\Notification as FilamentUINotification;
use Illuminate\Database\Eloquent\Builder;

class AvaliacaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->sortable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->sortable(),
                TextColumn::make('etapaAvaliativa.nome')
                    ->label('Etapa'),
                TextColumn::make('data_prevista')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('tem_pendencia')
                    ->label('Pendência')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->filters([
                Filter::make('pendentes')
                    ->label('Pendência de Lançamento')
                    ->query(fn (Builder $query) => $query->pendentes())
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('notificar')
                    ->label('Avisar Professor')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Avaliacao $record) => $record->tem_pendencia)
                    ->action(function (Avaliacao $record) {
                        $professor = $record->professor;
                        
                        if (!$professor) {
                            FilamentUINotification::make()
                                ->title('Erro')
                                ->body('Esta avaliação não possui um professor vinculado.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $user = User::whereHas('pessoas', fn($q) => $q->where('pessoa.id', $professor->id))->first();

                        if (!$user) {
                            FilamentUINotification::make()
                                ->title('Erro')
                                ->body('O professor não possui um usuário cadastrado no sistema.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $user->notify(new SystemNotification(
                            title: 'Pendente: Lançamento de Notas',
                            body: "A avaliação \"{$record->label_exibicao}\" possui alunos sem nota. Por favor, regularize o lançamento.",
                            actionUrl: "/admin/avaliacaos/{$record->id}/edit",
                            actionLabel: 'Lançar Notas Now',
                            type: 'warning'
                        ));

                        FilamentUINotification::make()
                            ->title('Sucesso')
                            ->body('O professor foi notificado sobre a pendência.')
                            ->success()
                            ->send();
                    })
                    ->confirm()
                    ->modalHeading('Notificar Professor')
                    ->modalDescription('Deseja enviar um aviso de pendência para o professor desta avaliação?')
                    ->modalSubmitActionLabel('Sim, notificar'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
