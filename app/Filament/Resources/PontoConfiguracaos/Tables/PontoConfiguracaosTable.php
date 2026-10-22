<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Tables;

use App\Models\PontoConfiguracao;
use App\Services\Ponto\ConformidadePontoService;
use App\Services\Ponto\PontoIntegridadeService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PontoConfiguracaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unidade.nome')->label('Unidade')->weight('bold')->searchable(),
                TextColumn::make('modo')->label('Modo')->badge(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
                TextColumn::make('ultimo_nsr')->label('Último NSR')->numeric(),
                TextColumn::make('conformidade')
                    ->label('Conformidade')
                    ->state(function (PontoConfiguracao $record): string {
                        if (! $record->modo->ehOficial()) {
                            return 'Não declara conformidade';
                        }

                        $pendencias = count(app(ConformidadePontoService::class)->pendencias($record));

                        return $pendencias === 0 ? 'Conforme' : "{$pendencias} pendência(s)";
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Conforme' => 'success',
                        str_contains($state, 'pendência') => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('verificarIntegridade')
                    ->label('Verificar integridade')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->action(function (PontoConfiguracao $record): void {
                        $resultado = app(PontoIntegridadeService::class)->verificar($record->unidade_id);

                        if ($resultado['ok']) {
                            Notification::make()->title('Cadeia íntegra')->body("{$resultado['total']} registro(s) verificados, sem nenhuma quebra.")->success()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Integridade comprometida')
                            ->body(collect($resultado['quebras'])->take(5)->map(fn (array $q): string => ($q['nsr'] !== null ? "NSR {$q['nsr']}: " : '').$q['detalhe'])->implode(' | '))
                            ->danger()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->stackedOnMobile();
    }
}
