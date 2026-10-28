<?php

namespace App\Filament\Resources\Contratos\Tables;

use App\Enums\StatusAssinaturaContrato;
use App\Enums\StatusFatura;
use App\Filament\Exports\ContratoExporter;
use App\Filament\Resources\Contratos\Actions\AcoesContrato;
use App\Models\Contrato;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // A coluna de signatários (Contrato::getSignatarios) e o indicador de faturamento leem estas relações
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'matricula.pessoa.responsaveis.users',
                'matricula.turma.serie.curso.unidade.representantesLegais.users',
                'responsaveisFinanceiros.pessoa.users',
                'faturas' => fn ($q) => $q->where('status', '!=', 'cancelado')->with('itens'),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno / Matrícula')
                    ->searchable()
                    ->description(fn (Contrato $record): ?string => $record->matricula?->turma ? ($record->matricula->turma->nome.($record->matricula->turma->serie ? ' - '.$record->matricula->turma->serie->nome : '')) : null),
                TextColumn::make('status_signatarios')
                    ->label('Signatários e Status')
                    ->badge()
                    ->state(function (Contrato $record): array {
                        $signatarios = $record->getStatusSignatarios();
                        if ($signatarios->isEmpty()) {
                            return ['Sem signatários'];
                        }

                        return $signatarios->map(function ($sig) {
                            $rotulo = match ($sig['status'] ?? 'pending') {
                                'signed' => ' (Assinado)',
                                'refused' => ' (Recusado)',
                                default => ' (Pendente)',
                            };

                            return $sig['nome'].$rotulo;
                        })->all();
                    })
                    ->color(function (string $state): string {
                        if ($state === 'Sem signatários') {
                            return 'gray';
                        }
                        if (str_ends_with($state, '(Assinado)')) {
                            return 'success';
                        }
                        if (str_ends_with($state, '(Recusado)')) {
                            return 'danger';
                        }

                        return 'warning';
                    })
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),
                TextColumn::make('valor_total')
                    ->label('Valor')
                    ->money('BRL')
                    ->description(function (Contrato $record): ?string {
                        $faturas = $record->relationLoaded('faturas')
                            ? $record->faturas->where('status', '!=', StatusFatura::Cancelado)
                            : $record->faturas;

                        if ($faturas->isEmpty()) {
                            return 'Sem faturas';
                        }

                        if ($record->temDivergenciaFaturamento()) {
                            $diff = $record->diferencaFaturamento();
                            $sinal = $diff > 0 ? '+' : '';

                            return 'Divergente ('.$sinal.'R$ '.number_format($diff, 2, ',', '.').')';
                        }

                        return 'Faturado';
                    })
                    ->color(fn (Contrato $record): ?string => $record->temDivergenciaFaturamento() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('data_aceite')
                    ->label('Aceite em')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('assinafy_status')
                    ->label('Assinatura')
                    ->badge()
                    ->color(fn (?string $state): string => StatusAssinaturaContrato::corDe($state))
                    ->icon(fn (?string $state): ?string => filled($state) ? StatusAssinaturaContrato::tryFrom($state)?->getIcon() : null)
                    ->formatStateUsing(fn (?string $state): string => StatusAssinaturaContrato::rotuloDe($state))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('situacao_faturamento')
                    ->label('Situação do Faturamento')
                    ->options([
                        'divergente' => 'Faturamento divergente',
                        'alinhado' => 'Faturamento alinhado',
                        'sem_faturas' => 'Sem faturas emitidas',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'divergente' => $query->comFaturamentoDivergente(),
                            'alinhado' => $query->comFaturamentoAlinhado(),
                            'sem_faturas' => $query->semFaturasEmitidas(),
                            default => $query,
                        };
                    }),
                SelectFilter::make('assinafy_status')
                    ->label('Status de Assinatura')
                    ->options(collect(StatusAssinaturaContrato::cases())->mapWithKeys(fn (StatusAssinaturaContrato $status) => [$status->value => $status->getLabel()])),
                SelectFilter::make('aluno')
                    ->relationship('matricula.pessoa', 'nome')
                    ->multiple()
                    ->label('Filtrar por Aluno')
                    ->searchable(),
                SelectFilter::make('responsavel')
                    ->relationship('responsaveisFinanceiros.pessoa', 'nome')
                    ->multiple()
                    ->label('Filtrar por Responsável')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                AcoesContrato::visualizarContrato(),
                ActionGroup::make([
                    AcoesContrato::sincronizarAssinafy(),
                    AcoesContrato::downloadContrato(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(ContratoExporter::class)
                        ->label('Exportar Selecionados')
                        ->visible(fn (): bool => auth()->user()->can('export', Contrato::class)),
                ]),
            ])
            ->stackedOnMobile();
    }
}
