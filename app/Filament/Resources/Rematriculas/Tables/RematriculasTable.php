<?php

namespace App\Filament\Resources\Rematriculas\Tables;

use App\Enums\StatusRematricula;
use App\Exceptions\TurmaIndisponivelException;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula;
use App\Services\RematriculaService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

                // Mesma contagem do contador no menu: famílias que já registraram a intenção e aguardam a turma.
                Filter::make('aguardando_turma')
                    ->label('Aguardando turma (a efetivar)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->aguardandoTurma()),
            ])
            ->recordActions([
                Action::make('efetivar')
                    ->label('Efetivar Rematrícula')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Efetivar Rematrícula do Estudante')
                    ->modalDescription('Escolha a turma de destino. A nova matrícula será criada nessa turma (ocupando uma vaga) e o contrato correspondente será gerado e enviado para assinatura.')
                    ->modalSubmitActionLabel('Efetivar')
                    ->schema(function (Rematricula $record, RematriculaService $service): array {
                        $periodo = PeriodoRematricula::findOrFail($record->periodo_rematricula_id);

                        return [self::campoTurma($service, $periodo, $record->serie_destino_id, $service->turmaSugerida($record, $periodo))];
                    })
                    ->visible(fn (Rematricula $record) => $record->status !== StatusRematricula::Confirmada
                        && ! $record->estaCancelada()
                        && ! $record->nova_matricula_id
                        && auth()->user()?->can('Update:Rematricula'))
                    ->action(function (Rematricula $record, array $data, RematriculaService $service) {
                        try {
                            $novaMatricula = $service->efetivar($record, (int) $data['turma_id']);

                            Notification::make()
                                ->title('Rematrícula Efetivada')
                                ->body("A nova Matrícula #{$novaMatricula->id} foi gerada com sucesso para o aluno {$record->matriculaOrigem?->pessoa?->nome}.")
                                ->success()
                                ->send();
                        } catch (TurmaIndisponivelException|\DomainException $e) {
                            Notification::make()
                                ->title('Não foi possível efetivar nesta turma')
                                ->body($e->getMessage())
                                ->warning()
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

                Action::make('cancelar')
                    ->label('Cancelar Rematrícula')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Cancelar esta rematrícula?')
                    ->modalDescription(fn (Rematricula $record): string => $record->foiEfetivada()
                        ? 'A nova matrícula será cancelada (liberando a vaga na turma) e as faturas em aberto do contrato serão canceladas. Faturas já pagas não são alteradas e precisam de estorno manual. O contrato e o documento enviado para assinatura permanecem registrados, mas uma assinatura posterior não reativa a rematrícula.'
                        : 'A rematrícula será marcada como cancelada. Nenhuma matrícula ou contrato foi gerado, então nada mais é alterado.')
                    ->modalSubmitActionLabel('Cancelar rematrícula')
                    ->schema([
                        Textarea::make('motivo')
                            ->label('Motivo (opcional)')
                            ->placeholder('Ex.: família desistiu, vaga em outra escola…')
                            ->rows(2),
                    ])
                    ->visible(fn (Rematricula $record): bool => ! $record->estaCancelada()
                        && (bool) auth()->user()?->can('Update:Rematricula'))
                    ->action(function (Rematricula $record, array $data, RematriculaService $service): void {
                        try {
                            $resumo = $service->cancelar($record, filled($data['motivo'] ?? null) ? $data['motivo'] : null);

                            $partes = [];
                            if ($resumo['matricula_cancelada']) {
                                $partes[] = 'a matrícula foi cancelada e a vaga liberada';
                            }
                            if ($resumo['faturas_canceladas'] > 0) {
                                $partes[] = "{$resumo['faturas_canceladas']} fatura(s) em aberto cancelada(s)";
                            }

                            $notificacao = Notification::make()
                                ->title('Rematrícula cancelada')
                                ->body($partes === [] ? 'Nenhuma matrícula ou fatura precisou ser alterada.' : ucfirst(implode('; ', $partes)).'.');

                            if ($resumo['faturas_pagas'] > 0) {
                                $notificacao->warning()->persistent()->body(
                                    $notificacao->getBody()." Atenção: {$resumo['faturas_pagas']} fatura(s) já paga(s) não foram alteradas — providencie o estorno, se for o caso."
                                );
                            } else {
                                $notificacao->success();
                            }

                            $notificacao->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao cancelar a rematrícula')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Rematricula $record): bool => $record->podeSerExcluida())
                    ->failureNotificationTitle('Não foi possível excluir: cancele a rematrícula antes'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('efetivarEmLote')
                        ->label('Efetivar na mesma turma')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->modalHeading('Efetivar rematrículas em lote')
                        ->modalDescription('Todas as rematrículas selecionadas serão efetivadas na turma escolhida (da mesma campanha). Se a turma lotar, as restantes continuam pendentes.')
                        ->modalSubmitActionLabel('Efetivar')
                        ->schema(function (Collection $records, RematriculaService $service): array {
                            $campanhas = $records->pluck('periodo_rematricula_id')->unique();

                            if ($campanhas->count() !== 1) {
                                return [];
                            }

                            $periodo = PeriodoRematricula::findOrFail($campanhas->first());
                            $series = $records->pluck('serie_destino_id')->filter()->unique();

                            return [self::campoTurma($service, $periodo, $series->count() === 1 ? (int) $series->first() : null, null)];
                        })
                        ->action(function (Collection $records, array $data, RematriculaService $service) {
                            if (blank($data['turma_id'] ?? null)) {
                                Notification::make()
                                    ->title('Selecione rematrículas de uma única campanha')
                                    ->body('A efetivação em lote usa uma turma só, que precisa ser do período de destino da campanha.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $efetivadas = 0;
                            $falhas = [];
                            $turmaLotou = false;

                            foreach ($records->filter(fn (Rematricula $r) => ! $r->nova_matricula_id && $r->status !== StatusRematricula::Confirmada) as $rematricula) {
                                try {
                                    $service->efetivar($rematricula, (int) $data['turma_id']);
                                    $efetivadas++;
                                } catch (TurmaIndisponivelException $e) {
                                    $falhas[] = "{$rematricula->matriculaOrigem?->pessoa?->nome}: {$e->getMessage()}";

                                    // Turma lotada ou fechada: as próximas falhariam igual.
                                    if ($e->turmaSemVaga()) {
                                        $turmaLotou = true;
                                        break;
                                    }
                                } catch (\Throwable $e) {
                                    $falhas[] = "{$rematricula->matriculaOrigem?->pessoa?->nome}: {$e->getMessage()}";
                                }
                            }

                            $notificacao = Notification::make()
                                ->title("{$efetivadas} rematrícula(s) efetivada(s)")
                                ->body($falhas === [] ? null : implode("\n", array_slice($falhas, 0, 5)).($turmaLotou ? "\nAs demais continuam pendentes." : ''));

                            ($falhas === [] ? $notificacao->success() : $notificacao->warning())->persistent($falhas !== [])->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->can('Update:Rematricula')),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    /**
     * Select de turma de destino: só turmas do período de destino da campanha, abertas para matrícula
     * (e da série pretendida, quando conhecida); as lotadas aparecem, mas não podem ser escolhidas.
     */
    private static function campoTurma(RematriculaService $service, PeriodoRematricula $periodo, ?int $serieId, ?int $sugerida): Select
    {
        $escolha = $service->opcoesDeTurma($periodo, $serieId);

        return Select::make('turma_id')
            ->label('Turma de destino')
            ->options($escolha['opcoes'])
            ->disableOptionWhen(fn (string $value): bool => in_array((int) $value, $escolha['lotadas'], true))
            ->default($sugerida)
            ->searchable()
            ->required()
            ->helperText($escolha['opcoes'] === []
                ? 'Nenhuma turma aberta para matrícula no período de destino. Cadastre ou duplique as turmas em Acadêmico → Turmas.'
                : 'Mostra as turmas do período de destino com a ocupação (ocupadas/vagas). Turmas lotadas não podem ser escolhidas.');
    }
}
