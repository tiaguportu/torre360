<?php

declare(strict_types=1);

namespace App\Filament\Resources\AtestadosAluno\Tables;

use App\Enums\StatusAtestadoAluno;
use App\Models\AtestadoAluno;
use App\Services\AtestadoAlunoService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class AtestadosAlunoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('matricula.turma.nome')
                    ->label('Turma'),
                TextColumn::make('data_inicio')
                    ->label('Período')
                    ->state(fn (AtestadoAluno $record): string => $record->periodo())
                    ->sortable(),
                TextColumn::make('frequencias_count')
                    ->label('Faltas justificadas')
                    ->numeric()
                    ->alignCenter(),
                IconColumn::make('abona_faltas')
                    ->label('Abono')
                    ->boolean()
                    ->tooltip(fn (AtestadoAluno $record): string => $record->abona_faltas ? 'Registrado como abono: as faltas não contam na frequência.' : 'Só justifica: as faltas continuam contando na frequência.')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->description(fn (AtestadoAluno $record): ?string => $record->motivo_cancelamento),
                TextColumn::make('registradoPor.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusAtestadoAluno::class),
                TernaryFilter::make('abona_faltas')
                    ->label('Abono de falta')
                    ->trueLabel('Com abono')
                    ->falseLabel('Só justifica'),
            ])
            ->recordActions([
                self::verComprovanteAction(),
                self::corrigirPeriodoAction(),
                ActionGroup::make([
                    self::cancelarAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }

    private static function verComprovanteAction(): Action
    {
        return Action::make('ver_comprovante')
            ->label('Comprovante')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (AtestadoAluno $record): bool => $record->temComprovante() && (bool) auth()->user()?->can('view', $record))
            ->url(fn (AtestadoAluno $record): string => route('documentos.visualizar', ['path' => $record->arquivo_path]))
            ->openUrlInNewTab();
    }

    private static function corrigirPeriodoAction(): Action
    {
        return Action::make('corrigir_periodo')
            ->label('Corrigir período / abono')
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->visible(fn (AtestadoAluno $record): bool => $record->estaVigente() && (bool) auth()->user()?->can('update', $record))
            ->modalHeading(fn (AtestadoAluno $record): string => 'Corrigir atestado — '.($record->matricula->pessoa?->nome ?? 'aluno'))
            ->modalDescription('As faltas que ficarem fora do novo período voltam a injustificadas; as novas do período são justificadas. Mudar o abono vale para todas as faltas deste atestado.')
            ->fillForm(fn (AtestadoAluno $record): array => [
                'data_inicio' => $record->data_inicio->toDateString(),
                'data_fim' => $record->data_fim->toDateString(),
                'abona_faltas' => $record->abona_faltas,
            ])
            ->schema([
                DatePicker::make('data_inicio')
                    ->label('Afastado a partir de')
                    ->native(false)
                    ->live()
                    ->required(),
                DatePicker::make('data_fim')
                    ->label('Até')
                    ->native(false)
                    ->afterOrEqual('data_inicio')
                    ->required(),
                Toggle::make('abona_faltas')
                    ->label('Registrar como abono de falta')
                    ->helperText('Marcado, as faltas deste atestado não contam na frequência. Desmarcado, ficam só justificadas e continuam contando.')
                    ->columnSpanFull(),
            ])
            ->action(function (AtestadoAluno $record, array $data): void {
                try {
                    $justificadas = app(AtestadoAlunoService::class)->corrigirPeriodo(
                        $record,
                        Carbon::parse($data['data_inicio']),
                        Carbon::parse($data['data_fim']),
                        auth()->user(),
                        (bool) ($data['abona_faltas'] ?? false),
                    );

                    Notification::make()->title('Atestado corrigido')->body("{$justificadas} falta(s) a mais justificada(s).")->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível corrigir')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function cancelarAction(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar atestado')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (AtestadoAluno $record): bool => $record->estaVigente() && (bool) auth()->user()?->can('cancelar', $record))
            ->modalHeading(fn (AtestadoAluno $record): string => 'Cancelar atestado — '.($record->matricula->pessoa?->nome ?? 'aluno'))
            ->modalDescription('As faltas que este atestado justificou voltam a injustificadas. O comprovante continua guardado.')
            ->schema([
                Textarea::make('motivo')
                    ->label('Motivo do cancelamento')
                    ->rows(2)
                    ->maxLength(255)
                    ->required(),
            ])
            ->action(function (AtestadoAluno $record, array $data): void {
                try {
                    $devolvidas = app(AtestadoAlunoService::class)->cancelar($record, (string) $data['motivo'], auth()->user());

                    Notification::make()->title('Atestado cancelado')->body("{$devolvidas} falta(s) voltaram a injustificadas.")->success()->send();
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível cancelar')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
