<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortfolioItems\Tables;

use App\Models\PortfolioItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PortfolioItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_registro', 'desc')
            ->columns([
                TextColumn::make('data_registro')->label('Data')->date('d/m/Y')->sortable(),
                TextColumn::make('matricula.pessoa.nome')->label('Aluno')->searchable()->weight('bold'),
                TextColumn::make('matricula.turma.nome')->label('Turma')->toggleable(),
                TextColumn::make('titulo')->label('Evidência')->searchable()->limit(50),
                TextColumn::make('habilidades.codigo')->label('Habilidades')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('anexos')
                    ->label('Arquivos')
                    ->state(fn (PortfolioItem $record): int => count($record->caminhosDosAnexos()))
                    ->alignCenter(),
                IconColumn::make('visivel_familia')->label('Família vê')->boolean(),
            ])
            ->filters([
                SelectFilter::make('turma')
                    ->label('Turma')
                    ->relationship('matricula.turma', 'nome')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('habilidades')
                    ->label('Habilidade')
                    ->relationship('habilidades', 'nome')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('visivel_familia')->label('Compartilhado com a família'),
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('de')->label('De')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('ate')->label('Até')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['de'] ?? null, fn (Builder $q, $de) => $q->whereDate('data_registro', '>=', $de))
                        ->when($data['ate'] ?? null, fn (Builder $q, $ate) => $q->whereDate('data_registro', '<=', $ate))),
            ])
            ->recordActions([
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
