<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\RelationManagers;

use App\Exceptions\ReajusteMensalidadeException;
use App\Models\CenarioReajuste;
use App\Models\CenarioReajusteItem;
use App\Services\ReajusteMensalidadeService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Séries do cenário: valor atual (média dos contratos, editável), percentual efetivo e valor novo.
 */
class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Séries';

    private function cenario(): CenarioReajuste
    {
        /** @var CenarioReajuste $cenario */
        $cenario = $this->getOwnerRecord();

        return $cenario;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['serie', 'cenario']))
            ->recordTitleAttribute('serie_id')
            ->columns([
                TextColumn::make('serie.nome')->label('Série')->weight('bold'),
                TextColumn::make('alunos')->label('Alunos')->numeric(),
                TextColumn::make('valor_atual')->label('Valor atual (anual)')->money('BRL'),
                TextColumn::make('percentual_efetivo')
                    ->label('Percentual')
                    ->state(fn (CenarioReajusteItem $record): string => number_format($record->percentualEfetivo(), 2, ',', '.').'%'.($record->percentual !== null ? ' (próprio)' : '')),
                TextColumn::make('valor_novo')->label('Valor novo (anual)')->money('BRL')->weight('bold'),
                TextColumn::make('variacao')
                    ->label('Variação por aluno')
                    ->state(fn (CenarioReajusteItem $record): float => $record->variacaoPorAluno())
                    ->money('BRL'),
                TextColumn::make('bolsa_media')->label('Bolsa média')->suffix('%')->numeric(1)->toggleable(),
            ])
            ->recordActions([
                Action::make('ajustar')
                    ->label('Ajustar')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->cenario()))
                    ->authorize(fn (): bool => (bool) auth()->user()?->can('update', $this->cenario()))
                    ->fillForm(fn (CenarioReajusteItem $record): array => ['percentual' => $record->percentual, 'valor_atual' => $record->valor_atual])
                    ->schema([
                        TextInput::make('percentual')
                            ->label('Percentual próprio desta série')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(ReajusteMensalidadeService::PERCENTUAL_MINIMO)
                            ->maxValue(ReajusteMensalidadeService::PERCENTUAL_MAXIMO)
                            ->suffix('%')
                            ->helperText('Deixe em branco para usar o percentual padrão do cenário.'),
                        TextInput::make('valor_atual')
                            ->label('Valor atual (anual)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('R$')
                            ->required()
                            ->helperText('Calculado pela média dos contratos; corrija se a média não representa o preço de tabela.'),
                    ])
                    ->action(function (array $data, CenarioReajusteItem $record): void {
                        try {
                            app(ReajusteMensalidadeService::class)->atualizarItem(
                                $record,
                                filled($data['percentual'] ?? null) ? (float) $data['percentual'] : null,
                                (float) $data['valor_atual'],
                            );
                        } catch (ReajusteMensalidadeException $e) {
                            Notification::make()->title('Não foi possível ajustar')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Série recalculada')->success()->send();
                    }),
            ])
            ->stackedOnMobile();
    }
}
