<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario\Schemas;

use App\Enums\MotivoDesligamentoFuncionario;
use App\Enums\StatusProcessoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Models\ProcessoFuncionario;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Cabeçalho do processo. A tela de um processo só consulta: ele nasce pela ação "Iniciar" e anda pelas providências.
 */
class ProcessoFuncionarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Processo')
                    ->columns(3)
                    ->schema([
                        Placeholder::make('funcionario')
                            ->label('Funcionário')
                            ->content(fn (?ProcessoFuncionario $record): string => trim(($record?->funcionario?->pessoa?->nome ?? '—').($record?->funcionario?->cargo ? ' — '.$record->funcionario->cargo : ''))),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoProcessoFuncionario::class)
                            ->disabled(),
                        Select::make('status')
                            ->label('Situação')
                            ->options(StatusProcessoFuncionario::class)
                            ->disabled(),
                        DatePicker::make('data_prevista')
                            ->label(fn (?ProcessoFuncionario $record): string => $record?->ehDesligamento() ? 'Último dia de trabalho' : 'Primeiro dia de trabalho')
                            ->native(false)
                            ->disabled(),
                        Select::make('motivo')
                            ->label('Motivo')
                            ->options(MotivoDesligamentoFuncionario::class)
                            ->disabled()
                            ->visible(fn (?ProcessoFuncionario $record): bool => (bool) $record?->ehDesligamento()),
                        Placeholder::make('andamento')
                            ->label('Andamento')
                            ->content(fn (?ProcessoFuncionario $record): string => $record ? sprintf('%d de %d providências resolvidas', $record->itens_resolvidos ?? 0, $record->itens_total ?? 0) : '—'),
                        Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->disabled()
                            ->columnSpanFull()
                            ->visible(fn (?ProcessoFuncionario $record): bool => filled($record?->observacao)),
                    ]),
            ]);
    }
}
