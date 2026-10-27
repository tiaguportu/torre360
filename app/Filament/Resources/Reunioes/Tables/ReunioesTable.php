<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Tables;

use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\Reuniao;
use App\Services\ReuniaoService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReunioesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['turma', 'organizador']))
            ->defaultSort('inicio_em', 'desc')
            ->columns([
                TextColumn::make('inicio_em')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('titulo')
                    ->label('Reunião')
                    ->description(fn (Reuniao $record): string => $record->tipo->getLabel())
                    ->searchable()
                    ->weight('bold')
                    ->limit(60),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('organizador.name')
                    ->label('Organizador')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sigilo')
                    ->label('Sigilo')
                    ->state(fn (Reuniao $record): ?string => $record->ehSigilosa() ? 'Sigilosa' : null)
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-lock-closed')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->description(fn (Reuniao $record): ?string => app(ReuniaoService::class)->ataAtrasada($record) ? 'Ata atrasada' : null),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options(TipoReuniao::class),
                SelectFilter::make('status')->label('Situação')->options(StatusReuniao::class),
                Filter::make('minhas')
                    ->label('Somente as minhas (organizo ou participo)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
                        ->where('organizador_user_id', auth()->id())
                        ->orWhereHas('participantes', fn (Builder $p) => $p->where('user_id', auth()->id())))),
                Filter::make('ata_aberta')
                    ->label('Com ata em aberto')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status', StatusReuniao::Realizada->value)),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (Reuniao $record): string => ReuniaoResource::getUrl('view', ['record' => $record]))
            ->stackedOnMobile();
    }
}
