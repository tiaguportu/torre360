<?php

namespace App\Filament\Resources\Avaliacaos\Tables;

use App\Models\Avaliacao;
use App\Models\Pessoa;
use App\Models\User;
use App\Notifications\SystemNotification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
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
                        // Busca o professor específico da Disciplina nesta Turma (Pivot)
                        $professorPivot = $record->turma?->disciplinas()
                            ->where('disciplina.id', $record->disciplina_id)
                            ->first()?->pivot?->professor_id;

                        // Fallback para o professor vinculado na Avaliação ou Regente da Turma
                        $professorId = $professorPivot ?? $record->professor_id ?? $record->turma?->professor_conselheiro_id;
                        $professor = Pessoa::find($professorId);
                        
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
                    ->requiresConfirmation()
                    ->modalHeading('Notificar Professor')
                    ->modalDescription(function (Avaliacao $record) {
                        // Mesma lógica de busca para o modal
                        $professorPivot = $record->turma?->disciplinas()
                            ->where('disciplina.id', $record->disciplina_id)
                            ->first()?->pivot?->professor_id;
                        
                        $professorId = $professorPivot ?? $record->professor_id ?? $record->turma?->professor_conselheiro_id;
                        $professor = Pessoa::find($professorId);

                        if (!$professor) return 'Deseja notificar o professor regente?';

                        $user = User::whereHas('pessoas', fn($q) => $q->where('pessoa.id', $professor->id))->first();
                        $email = $user?->email ?? 'E-mail não cadastrado';

                        return "Confirmar envio de notificação de pendência para o professor {$professor->nome} ({$email})?";
                    })
                    ->modalSubmitActionLabel('Sim, enviar aviso'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
