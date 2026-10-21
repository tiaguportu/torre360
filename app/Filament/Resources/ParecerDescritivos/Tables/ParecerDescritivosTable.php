<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Tables;

use App\Enums\StatusParecerDescritivo;
use App\Exceptions\ParecerDescritivoException;
use App\Models\ParecerDescritivo;
use App\Services\ParecerDescritivoService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ParecerDescritivosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('matricula.pessoa.nome')->label('Aluno')->searchable()->weight('bold'),
                TextColumn::make('matricula.turma.nome')->label('Turma')->toggleable(),
                TextColumn::make('etapaAvaliativa.nome')->label('Etapa')->sortable(),
                TextColumn::make('status')->label('Situação')->badge(),
                IconColumn::make('gerado_com_ia')->label('IA')->boolean()->toggleable(),
                TextColumn::make('publicado_em')->label('Publicado em')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusParecerDescritivo::class),
                SelectFilter::make('etapa_avaliativa_id')->label('Etapa')->relationship('etapaAvaliativa', 'nome')->searchable()->preload(),
                SelectFilter::make('turma')->label('Turma')->relationship('matricula.turma', 'nome')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('enviarParaRevisao')
                    ->label('Enviar para revisão')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ParecerDescritivo $record): bool => (bool) auth()->user()?->can('enviarParaRevisao', $record))
                    ->action(fn (ParecerDescritivo $record) => self::executar(fn () => app(ParecerDescritivoService::class)->enviarParaRevisao($record, auth()->user()), 'Parecer enviado para revisão')),
                Action::make('publicar')
                    ->label('Publicar para a família')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('A família do aluno passa a ver este parecer no Portal.')
                    ->visible(fn (ParecerDescritivo $record): bool => (bool) auth()->user()?->can('publicar', $record))
                    ->action(fn (ParecerDescritivo $record) => self::executar(fn () => app(ParecerDescritivoService::class)->publicar($record, auth()->user()), 'Parecer publicado')),
                Action::make('reabrir')
                    ->label('Voltar para rascunho')
                    ->icon('heroicon-o-lock-open')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('O parecer deixa de aparecer para a família até ser publicado de novo.')
                    ->visible(fn (ParecerDescritivo $record): bool => (bool) auth()->user()?->can('reabrir', $record))
                    ->action(fn (ParecerDescritivo $record) => self::executar(fn () => app(ParecerDescritivoService::class)->reabrir($record, auth()->user()), 'Parecer reaberto como rascunho')),
                ViewAction::make()->label('Ler'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    private static function executar(callable $acao, string $sucesso): void
    {
        try {
            $acao();
        } catch (ParecerDescritivoException $e) {
            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($sucesso)->success()->send();
    }
}
