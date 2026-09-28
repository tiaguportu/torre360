<?php

namespace App\Filament\Widgets;

use App\Services\CrmConversaoService;
use App\Traits\HasCustomWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ConversaoOrigemWidget extends TableWidget
{
    use HasCustomWidgetShield;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Conversão por Origem do Lead')
            ->description('Quais origens geram mais leads e quais realmente viram matrícula.')
            ->records(fn (): array => collect(CrmConversaoService::porOrigem())->keyBy('id')->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('nome')
                    ->label('Origem')
                    ->weight('bold'),
                TextColumn::make('leads')
                    ->label('Leads')
                    ->badge()
                    ->color('info'),
                TextColumn::make('matriculados')
                    ->label('Matrículas')
                    ->badge()
                    ->color('success'),
                TextColumn::make('taxa')
                    ->label('Conversão')
                    ->suffix('%')
                    ->badge()
                    ->color(fn (float|int|string|null $state): string => match (true) {
                        (float) $state >= 20 => 'success',
                        (float) $state >= 10 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Nenhum lead captado ainda');
    }
}
