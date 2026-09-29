<?php

namespace App\Filament\Widgets;

use App\Services\CrmConversaoService;
use App\Traits\HasCustomWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ConversaoCampanhaWidget extends TableWidget
{
    use HasCustomWidgetShield;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Conversão por Campanha de Marketing')
            ->description('Leads captados por campanha, quantos viraram matrícula e o custo de aquisição.')
            ->records(fn (): array => collect(CrmConversaoService::porCampanha())->keyBy('id')->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('nome')
                    ->label('Campanha')
                    ->weight('bold'),
                TextColumn::make('canal')
                    ->label('Canal')
                    ->placeholder('—'),
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
                TextColumn::make('custo')
                    ->label('Investimento')
                    ->money('BRL'),
                TextColumn::make('custo_por_lead')
                    ->label('Custo/Lead')
                    ->money('BRL')
                    ->placeholder('—'),
                TextColumn::make('custo_por_matricula')
                    ->label('Custo/Matrícula')
                    ->money('BRL')
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Nenhuma campanha cadastrada')
            ->emptyStateDescription('Cadastre campanhas em CRM / Comercial → Campanhas de Marketing.');
    }
}
