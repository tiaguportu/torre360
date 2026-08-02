<?php

namespace App\Filament\Resources\Contratos\Tables;

use App\Filament\Exports\ContratoExporter;
use App\Models\Contrato;
use App\Services\AssinafyService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno / Matrícula')
                    ->searchable(),
                TextColumn::make('status_signatarios')
                    ->label('Signatários e Status')
                    ->html()
                    ->state(function (Contrato $record): string {
                        $signatarios = $record->getStatusSignatarios();
                        if ($signatarios->isEmpty()) {
                            return '<span class="text-gray-400 font-normal text-xs">Sem signatários</span>';
                        }

                        $html = '<div class="space-y-1 text-xs">';
                        foreach ($signatarios as $sig) {
                            $nome = e($sig['nome']);
                            if ($sig['status'] === 'signed') {
                                $html .= "<div class=\"flex items-center gap-1 text-emerald-600 font-medium\"><span class=\"w-2 h-2 rounded-full bg-emerald-500 inline-block flex-shrink-0\"></span> <span>{$nome} (Assinado)</span></div>";
                            } elseif ($sig['status'] === 'refused') {
                                $html .= "<div class=\"flex items-center gap-1 text-rose-600 font-medium\"><span class=\"w-2 h-2 rounded-full bg-rose-500 inline-block flex-shrink-0\"></span> <span>{$nome} (Recusado)</span></div>";
                            } else {
                                $html .= "<div class=\"flex items-center gap-1 text-amber-600 font-medium\"><span class=\"w-2 h-2 rounded-full bg-amber-500 inline-block flex-shrink-0\"></span> <span>{$nome} (Pendente)</span></div>";
                            }
                        }
                        $html .= '</div>';

                        return $html;
                    }),
                TextColumn::make('valor_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('data_aceite')
                    ->date()
                    ->sortable(),
                TextColumn::make('assinafy_status')
                    ->label('Assinatura')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'signed', 'completed' => 'success',
                        'enviado', 'pending' => 'warning',
                        'erro_envio', 'rejected', 'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'signed', 'completed' => 'Assinado',
                        'enviado', 'pending' => 'Pendente',
                        'erro_envio' => 'Erro no Envio',
                        'rejected' => 'Recusado',
                        'canceled' => 'Cancelado',
                        default => ucfirst($state),
                    })
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
                Action::make('visualizar_contrato')
                    ->label(fn (?Contrato $record) => $record && in_array($record->assinafy_status, ['signed', 'completed']) ? 'Ver Contrato Assinado' : 'Assinar Contrato')
                    ->icon(fn (?Contrato $record) => $record && in_array($record->assinafy_status, ['signed', 'completed']) ? 'heroicon-o-document-magnifying-glass' : 'heroicon-o-document-check')
                    ->color(fn (?Contrato $record) => $record && in_array($record->assinafy_status, ['signed', 'completed']) ? 'success' : 'warning')
                    ->action(function (Contrato $record, AssinafyService $service) {
                        if (in_array($record->assinafy_status, ['signed', 'completed'])) {
                            return redirect()->away(route('contratos.visualizar', $record));
                        }

                        $result = $service->enviarContrato($record);

                        if ($result['success'] && isset($result['redirect_url'])) {
                            return redirect()->away($result['redirect_url']);
                        }

                        Notification::make()
                            ->title('Erro ao processar assinatura')
                            ->body($result['message'] ?? 'Não foi possível obter a URL de assinatura da Assinafy.')
                            ->danger()
                            ->send();
                    }),
                Action::make('sincronizar_assinafy')
                    ->label('Sincronizar Assinaturas')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (Contrato $record, AssinafyService $service) {
                        $result = $service->consultarEAtualizarStatusSignatarios($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Status dos signatários atualizado!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Erro ao sincronizar status')
                                ->body($result['message'] ?? 'Não foi possível consultar a API do Assinafy.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->visible(fn (?Contrato $record) => $record && $record->assinafy_id !== null),
                Action::make('download_contrato')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => route('contratos.download', $record))
                    ->visible(fn ($record) => $record->assinafy_id !== null),
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
