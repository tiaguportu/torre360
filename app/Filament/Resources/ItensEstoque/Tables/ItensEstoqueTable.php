<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque\Tables;

use App\Models\CentroCusto;
use App\Models\ItemEstoque;
use App\Services\EstoqueService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItensEstoqueTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Item')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (ItemEstoque $record): ?string => $record->codigo ?: $record->categoria),
                TextColumn::make('quantidade_atual')
                    ->label('Saldo')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(fn (ItemEstoque $record): string => ' '.$record->unidade_medida)
                    ->color(fn (ItemEstoque $record): ?string => $record->abaixoDoMinimo() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('estoque_minimo')
                    ->label('Mínimo')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->toggleable(),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (ItemEstoque $record): string => ! $record->is_active ? 'Inativo' : ($record->abaixoDoMinimo() ? ((float) $record->quantidade_atual <= 0 ? 'Zerado' : 'Abaixo do mínimo') : 'Em dia'))
                    ->color(fn (string $state): string => match ($state) {
                        'Zerado' => 'danger',
                        'Abaixo do mínimo' => 'warning',
                        'Inativo' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('custo_medio')->label('Custo médio')->money('BRL')->toggleable(),
                TextColumn::make('valor')
                    ->label('Valor em estoque')
                    ->state(fn (ItemEstoque $record): float => $record->valorEmEstoque())
                    ->money('BRL')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('abaixo_do_minimo')->label('Abaixo do mínimo')->query(fn ($query) => $query->abaixoDoMinimo()),
                TernaryFilter::make('is_active')->label('Ativo')->default(true),
            ])
            ->recordActions([
                self::entrada(),
                self::saida(),
                ActionGroup::make([self::ajuste(), EditAction::make()]),
            ])
            ->defaultSort('nome')
            ->stackedOnMobile();
    }

    /**
     * @param  callable(): void  $acao
     */
    private static function executar(callable $acao, string $sucesso): void
    {
        try {
            $acao();
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível movimentar')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()->title($sucesso)->success()->send();
    }

    public static function entrada(): Action
    {
        return Action::make('entrada')
            ->label('Entrada')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->visible(fn (ItemEstoque $record): bool => (bool) auth()->user()?->can('movimentar', $record))
            ->modalHeading(fn (ItemEstoque $record): string => 'Entrada: '.$record->nome)
            ->modalDescription('Compra recebida fora do fluxo de Compras, doação ou devolução. O que vem de um pedido de compra entra sozinho no recebimento.')
            ->schema(fn (ItemEstoque $record): array => [
                TextInput::make('quantidade')->label('Quantidade ('.$record->unidade_medida.')')->numeric()->minValue(0.001)->required(),
                TextInput::make('custo')->label('Custo unitário (R$, opcional)')->numeric()->minValue(0)->helperText('Com custo, o custo médio do item é recalculado.'),
                TextInput::make('motivo')->label('Origem / nota fiscal')->maxLength(160),
            ])
            ->action(function (ItemEstoque $record, array $data): void {
                abort_unless(auth()->user()?->can('movimentar', $record), 403);

                self::executar(fn () => app(EstoqueService::class)->entrada($record, (float) $data['quantidade'], filled($data['custo'] ?? null) ? (float) $data['custo'] : null, null, auth()->user(), $data['motivo'] ?? null), 'Entrada registrada');
            });
    }

    public static function saida(): Action
    {
        return Action::make('saida')
            ->label('Saída')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->visible(fn (ItemEstoque $record): bool => (bool) auth()->user()?->can('movimentar', $record))
            ->modalHeading(fn (ItemEstoque $record): string => 'Saída: '.$record->nome)
            ->modalDescription(fn (ItemEstoque $record): string => 'Saldo atual: '.rtrim(rtrim(number_format((float) $record->quantidade_atual, 3, ',', ''), '0'), ',').' '.$record->unidade_medida.'.')
            ->schema(fn (ItemEstoque $record): array => [
                TextInput::make('quantidade')->label('Quantidade ('.$record->unidade_medida.')')->numeric()->minValue(0.001)->required(),
                Select::make('centro_custo_id')
                    ->label('Para qual setor / centro de custo')
                    ->options(fn (): array => CentroCusto::query()->where('is_active', true)->orderBy('nome')->pluck('nome', 'id')->all())
                    ->searchable()
                    ->native(false),
                TextInput::make('motivo')->label('Motivo (opcional)')->maxLength(160),
            ])
            ->action(function (ItemEstoque $record, array $data): void {
                abort_unless(auth()->user()?->can('movimentar', $record), 403);

                $centro = filled($data['centro_custo_id'] ?? null) ? CentroCusto::query()->find($data['centro_custo_id']) : null;

                self::executar(fn () => app(EstoqueService::class)->saida($record, (float) $data['quantidade'], $centro, $data['motivo'] ?? null, auth()->user()), 'Saída registrada');
            });
    }

    public static function ajuste(): Action
    {
        return Action::make('ajuste')
            ->label('Ajuste de inventário')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (ItemEstoque $record): bool => (bool) auth()->user()?->can('movimentar', $record))
            ->modalHeading(fn (ItemEstoque $record): string => 'Ajuste: '.$record->nome)
            ->modalDescription('Informe o que foi contado. O sistema registra a diferença como ajuste, com o seu motivo.')
            ->schema(fn (ItemEstoque $record): array => [
                TextInput::make('contado')->label('Quantidade contada ('.$record->unidade_medida.')')->numeric()->minValue(0)->required(),
                Textarea::make('motivo')->label('Motivo')->placeholder('Contagem de inventário, quebra, perda...')->required()->rows(2)->maxLength(160),
            ])
            ->action(function (ItemEstoque $record, array $data): void {
                abort_unless(auth()->user()?->can('movimentar', $record), 403);

                self::executar(fn () => app(EstoqueService::class)->ajustar($record, (float) $data['contado'], (string) $data['motivo'], auth()->user()), 'Ajuste registrado');
            });
    }
}
