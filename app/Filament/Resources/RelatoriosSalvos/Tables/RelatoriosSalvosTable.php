<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos\Tables;

use App\Enums\OrigemExecucaoRelatorio;
use App\Enums\StatusExecucaoRelatorio;
use App\Models\RelatorioSalvo;
use App\Services\Relatorios\CatalogoDeRelatorios;
use App\Services\Relatorios\RelatorioExecutorService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RelatoriosSalvosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('criadoPor'))
            ->columns([
                TextColumn::make('nome')
                    ->label('Relatório')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('fonte')
                    ->label('Dados de')
                    ->formatStateUsing(fn (string $state): string => app(CatalogoDeRelatorios::class)->da($state)?->titulo() ?? $state),
                TextColumn::make('formato')
                    ->label('Arquivo')
                    ->badge(),
                TextColumn::make('agendamento')
                    ->label('Agendamento')
                    ->state(fn (RelatorioSalvo $record): string => $record->descricaoDoAgendamento())
                    ->description(fn (RelatorioSalvo $record): ?string => $record->agendado && $record->proxima_execucao_em ? 'Próximo: '.$record->proxima_execucao_em->format('d/m/Y H:i') : null),
                TextColumn::make('ultima_execucao_em')
                    ->label('Última execução')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->toggleable(),
                TextColumn::make('criadoPor.name')
                    ->label('Criado por')
                    ->placeholder('—')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('VerTodos:RelatorioSalvo')),
            ])
            ->filters([
                SelectFilter::make('fonte')
                    ->label('Dados de')
                    ->options(fn (): array => array_map(fn ($fonte): string => $fonte->titulo(), app(CatalogoDeRelatorios::class)->todas())),
            ])
            ->recordActions([
                self::executar(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->stackedOnMobile();
    }

    /**
     * Gera o arquivo agora. O resultado (ou o motivo de não ter saído) aparece na hora, com o link para baixar.
     */
    public static function executar(): Action
    {
        return Action::make('executar')
            ->label('Gerar agora')
            ->icon('heroicon-o-play')
            ->color('primary')
            ->visible(fn (RelatorioSalvo $record): bool => (bool) auth()->user()?->can('executar', $record))
            ->action(function (RelatorioSalvo $record): void {
                abort_unless(auth()->user()?->can('executar', $record), 403);

                $execucao = app(RelatorioExecutorService::class)->executar($record, OrigemExecucaoRelatorio::Manual, auth()->user());

                if ($execucao->status === StatusExecucaoRelatorio::Erro) {
                    Notification::make()->title('Não foi possível gerar o relatório')->body($execucao->erro)->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('Relatório gerado')
                    ->body("{$execucao->linhas} linha(s)".($execucao->truncado ? ', cortado no limite de linhas' : '').'.')
                    ->success()
                    ->actions([
                        Action::make('baixar')->label('Baixar')->url(route('relatorios.execucoes.download', $execucao))->button(),
                    ])
                    ->send();
            });
    }
}
