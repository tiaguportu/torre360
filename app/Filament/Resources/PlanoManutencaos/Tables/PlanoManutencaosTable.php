<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Tables;

use App\Enums\SituacaoPlanoManutencao;
use App\Exceptions\ManutencaoPreventivaException;
use App\Models\PlanoManutencao;
use App\Services\ManutencaoPreventivaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanoManutencaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['bemPatrimonial', 'sala']))
            ->defaultSort('proxima_execucao')
            ->columns([
                TextColumn::make('titulo')
                    ->label('Plano')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('alvo')
                    ->label('Alvo')
                    ->state(fn (PlanoManutencao $record): string => $record->alvoDescricao())
                    ->wrap(),
                TextColumn::make('periodicidade_dias')
                    ->label('Periodicidade')
                    ->formatStateUsing(fn ($state): string => "a cada {$state} dias")
                    ->toggleable(),
                TextColumn::make('proxima_execucao')
                    ->label('Próxima')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (PlanoManutencao $record): ?string => match ($record->situacao()) {
                        SituacaoPlanoManutencao::Vencido => 'danger',
                        SituacaoPlanoManutencao::AVencer => 'warning',
                        default => null,
                    }),
                TextColumn::make('ultima_execucao')
                    ->label('Última')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->state(fn (PlanoManutencao $record): SituacaoPlanoManutencao => $record->situacao())
                    ->badge(),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('situacao')
                    ->label('Situação')
                    ->options(SituacaoPlanoManutencao::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $situacao = SituacaoPlanoManutencao::tryFrom((string) ($data['value'] ?? ''));
                        $hoje = today()->toDateString();
                        $limite = today()->addDays(PlanoManutencao::DIAS_A_VENCER)->toDateString();

                        return match ($situacao) {
                            SituacaoPlanoManutencao::Vencido => $query->where('ativo', true)->whereDate('proxima_execucao', '<', $hoje),
                            SituacaoPlanoManutencao::AVencer => $query->where('ativo', true)->whereDate('proxima_execucao', '>=', $hoje)->whereDate('proxima_execucao', '<=', $limite),
                            SituacaoPlanoManutencao::EmDia => $query->where('ativo', true)->whereDate('proxima_execucao', '>', $limite),
                            SituacaoPlanoManutencao::Inativo => $query->where('ativo', false),
                            default => $query,
                        };
                    }),
                SelectFilter::make('sala_id')->label('Espaço')->relationship('sala', 'nome')->searchable()->preload(),
                TernaryFilter::make('ativo')->label('Plano ativo'),
            ])
            ->recordActions([
                Action::make('gerarOrdem')
                    ->label('Gerar OS agora')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Abre agora a ordem de serviço do próximo vencimento, sem esperar a janela de antecedência.')
                    ->visible(fn (PlanoManutencao $record): bool => (bool) auth()->user()?->can('gerarOrdem', $record))
                    ->action(function (PlanoManutencao $record): void {
                        try {
                            $execucao = app(ManutencaoPreventivaService::class)->gerarOrdem($record);
                        } catch (ManutencaoPreventivaException $e) {
                            Notification::make()->title('Não foi possível gerar a ordem')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Ordem de serviço #{$execucao->ordem_servico_id} aberta")->success()->send();
                    }),
                Action::make('registrarExecucao')
                    ->label('Registrar execução')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Registrar a manutenção feita')
                    ->modalDescription('Conclui a execução e a ordem de serviço, e o plano avança: a próxima manutenção é a data da execução somada à periodicidade.')
                    ->schema(fn (PlanoManutencao $record): array => [
                        DatePicker::make('data')
                            ->label('Data da execução')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        Repeater::make('checklist')
                            ->label('Checklist')
                            ->schema([
                                TextInput::make('item')->label('Item')->disabled()->dehydrated(),
                                Toggle::make('ok')->label('Conforme')->default(true),
                                TextInput::make('observacao')->label('Observação')->maxLength(255),
                            ])
                            ->default(collect((array) $record->checklist)->filter()->map(fn ($item): array => ['item' => (string) $item, 'ok' => true, 'observacao' => null])->values()->all())
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(3)
                            ->visible(fn (): bool => filled(array_filter((array) $record->checklist))),
                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->visible(fn (PlanoManutencao $record): bool => (bool) auth()->user()?->can('registrarExecucao', $record))
                    ->action(function (PlanoManutencao $record, array $data): void {
                        try {
                            app(ManutencaoPreventivaService::class)->registrarExecucao(
                                $record,
                                Carbon::parse($data['data']),
                                array_values($data['checklist'] ?? []),
                                $data['observacoes'] ?? null,
                                auth()->user(),
                            );
                        } catch (ManutencaoPreventivaException $e) {
                            Notification::make()->title('Não foi possível registrar')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Execução registrada')->body('Próxima manutenção: '.$record->fresh()->proxima_execucao->format('d/m/Y').'.')->success()->send();
                    }),
                Action::make('reprogramar')
                    ->label('Reprogramar')
                    ->icon('heroicon-o-calendar')
                    ->color('gray')
                    ->modalDescription('Move o próximo vencimento. A execução prevista em aberto é cancelada.')
                    ->schema([
                        DatePicker::make('nova_data')->label('Nova data')->native(false)->displayFormat('d/m/Y')->minDate(today())->required(),
                    ])
                    ->visible(fn (PlanoManutencao $record): bool => (bool) auth()->user()?->can('update', $record))
                    ->action(function (PlanoManutencao $record, array $data): void {
                        try {
                            app(ManutencaoPreventivaService::class)->reprogramar($record, Carbon::parse($data['nova_data']));
                        } catch (ManutencaoPreventivaException $e) {
                            Notification::make()->title('Não foi possível reprogramar')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Plano reprogramado')->success()->send();
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
