<?php

namespace App\Filament\Resources\BemPatrimonials\Tables;

use App\Enums\StatusBemPatrimonial;
use App\Models\BemPatrimonial;
use App\Models\Sala;
use App\Models\Unidade;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BemPatrimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('numero_patrimonio')
                    ->label('Nº Patrimônio')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('unidade.nome')
                    ->label('Unidade')
                    ->placeholder('—'),
                TextColumn::make('sala.nome')
                    ->label('Sala')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusBemPatrimonial::class),
                SelectFilter::make('unidade_id')
                    ->label('Unidade')
                    ->relationship('unidade', 'nome')
                    ->searchable(),
            ])
            ->recordActions([
                self::transferirAction(),
                self::mudarStatusAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('descricao')
            ->stackedOnMobile();
    }

    public static function transferirAction(): Action
    {
        return Action::make('transferir')
            ->label('Transferir')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->schema([
                Select::make('unidade_id')
                    ->label('Nova Unidade')
                    ->options(fn () => Unidade::pluck('nome', 'id'))
                    ->searchable(),
                Select::make('sala_id')
                    ->label('Nova Sala')
                    ->options(fn () => Sala::pluck('nome', 'id'))
                    ->searchable(),
                Textarea::make('observacao')
                    ->label('Observação')
                    ->rows(2),
            ])
            ->fillForm(fn (BemPatrimonial $record): array => [
                'unidade_id' => $record->unidade_id,
                'sala_id' => $record->sala_id,
            ])
            ->action(function (array $data, BemPatrimonial $record): void {
                $record->transferir($data['unidade_id'] ?? null, $data['sala_id'] ?? null, $data['observacao'] ?? null);

                Notification::make()->title('Bem transferido com sucesso!')->success()->send();
            });
    }

    public static function mudarStatusAction(): Action
    {
        return Action::make('mudar_status')
            ->label('Mudar Status')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->schema([
                Select::make('status')
                    ->label('Novo Status')
                    ->options(StatusBemPatrimonial::class)
                    ->required(),
                Textarea::make('observacao')
                    ->label('Observação')
                    ->rows(2),
            ])
            ->action(function (array $data, BemPatrimonial $record): void {
                $novoStatus = $data['status'] instanceof StatusBemPatrimonial
                    ? $data['status']
                    : StatusBemPatrimonial::from($data['status']);

                $record->mudarStatus($novoStatus, $data['observacao'] ?? null);

                Notification::make()->title('Status atualizado com sucesso!')->success()->send();
            });
    }
}
