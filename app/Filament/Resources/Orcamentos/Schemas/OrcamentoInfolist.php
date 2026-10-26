<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Schemas;

use App\Models\Orcamento;
use App\Services\OrcamentoService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrcamentoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Orçamento')
                ->columns(3)
                ->schema([
                    TextEntry::make('nome')->label('Nome'),
                    TextEntry::make('ano')->label('Ano'),
                    TextEntry::make('versao')->label('Versão')->prefix('v'),
                    TextEntry::make('status')->label('Situação')->badge(),
                    TextEntry::make('aprovadoPor.name')->label('Aprovado por')->placeholder('—'),
                    TextEntry::make('aprovado_em')->label('Aprovado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('origem_rotulo')
                        ->label('Revisão de')
                        ->state(fn (Orcamento $record): ?string => $record->origem?->rotulo())
                        ->placeholder('—'),
                ]),
            Section::make('Totais previstos no ano')
                ->columns(3)
                ->schema([
                    TextEntry::make('total_receita')->label('Receitas')->money('BRL')
                        ->state(fn (Orcamento $record): float => app(OrcamentoService::class)->totais($record)['receita']),
                    TextEntry::make('total_despesa')->label('Despesas')->money('BRL')
                        ->state(fn (Orcamento $record): float => app(OrcamentoService::class)->totais($record)['despesa']),
                    TextEntry::make('total_resultado')->label('Resultado previsto')->money('BRL')
                        ->state(fn (Orcamento $record): float => app(OrcamentoService::class)->totais($record)['resultado']),
                ]),
        ]);
    }
}
