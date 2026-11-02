<?php

namespace App\Filament\Resources\Denuncias\Tables;

use App\Enums\CategoriaDenuncia;
use App\Enums\StatusDenuncia;
use App\Models\Denuncia;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DenunciasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('protocolo')->label('Protocolo')->weight('bold')->searchable()->sortable(),
                TextColumn::make('categoria')->label('Assunto')->badge()->sortable(),
                TextColumn::make('status')->label('Situação')->badge()->sortable(),
                IconColumn::make('suspeita_violencia')->label('Conselho Tutelar')->boolean()->trueIcon('heroicon-o-exclamation-triangle')->falseIcon('')->trueColor('danger'),
                TextColumn::make('anonima')->label('Autoria')->formatStateUsing(fn (bool $state): string => $state ? 'Anônima' : 'Identificada')->badge()->color('gray'),
                TextColumn::make('prazo_em')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->color(fn (Denuncia $record): ?string => $record->atrasada() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('responsavel.name')->label('Responsável')->placeholder('Ninguém assumiu'),
                TextColumn::make('created_at')->label('Recebida em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('categoria')->label('Assunto')->options(CategoriaDenuncia::class),
                SelectFilter::make('status')->label('Situação')->options(StatusDenuncia::class),
                Filter::make('atrasadas')
                    ->label('Prazo vencido')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', [StatusDenuncia::Recebida->value, StatusDenuncia::EmApuracao->value])->where('prazo_em', '<', now())),
            ])
            ->recordActions([ViewAction::make()])
            ->stackedOnMobile();
    }
}
