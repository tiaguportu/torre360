<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Financeiro extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Financeiro';

    protected static ?string $slug = 'financeiro';

    protected string $view = 'filament.portal.pages.financeiro';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFaturasQuery())
            ->columns([
                TextColumn::make('contrato.matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Fatura $record) => $record->status === StatusFatura::Atrasado ? 'danger' : null),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL'),
                TextColumn::make('valor_pago')
                    ->label('Pago')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('valor_restante')
                    ->label('Saldo')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusFatura::class)
                    ->multiple(),
            ])
            ->defaultSort('vencimento', 'desc')
            ->stackedOnMobile();
    }

    protected function getFaturasQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Fatura::query()
            ->whereHas('contrato', function (Builder $query) use ($idsAcessiveis) {
                $query->whereHas('matricula', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis))
                    ->orWhereHas('responsaveisFinanceiros', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis));
            })
            ->with(['itens', 'transacoes', 'contrato.matricula.pessoa']);
    }
}
