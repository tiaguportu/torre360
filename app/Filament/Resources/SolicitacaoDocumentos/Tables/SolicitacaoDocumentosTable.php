<?php

namespace App\Filament\Resources\SolicitacaoDocumentos\Tables;

use App\Enums\StatusSolicitacaoDocumento;
use App\Models\SolicitacaoDocumento;
use App\Services\DocumentoService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class SolicitacaoDocumentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Protocolo copiado'),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('templateDocumento.nome')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('codigo_verificacao')
                    ->label('Código QR')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Código copiado'),

                TextColumn::make('data_emissao')
                    ->label('Emissão')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('data_validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (SolicitacaoDocumento $record) => $record->data_validade && $record->data_validade->isPast() ? 'danger' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusSolicitacaoDocumento::class),

                SelectFilter::make('template_documento_id')
                    ->label('Tipo de Documento')
                    ->relationship('templateDocumento', 'nome'),
            ])
            ->recordActions([
                Action::make('gerar_pdf')
                    ->label('Emitir PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(function (SolicitacaoDocumento $record, DocumentoService $service) {
                        try {
                            $service->gerarPdf($record);

                            Notification::make()
                                ->title('Documento Gerado com Sucesso')
                                ->body("O arquivo PDF do protocolo {$record->protocolo} foi gerado com QR Code.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao gerar documento')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('baixar')
                    ->label('Baixar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (SolicitacaoDocumento $record) => ! empty($record->arquivo_path) && Storage::disk('public')->exists($record->arquivo_path))
                    ->url(fn (SolicitacaoDocumento $record) => Storage::disk('public')->url($record->arquivo_path))
                    ->openUrlInNewTab(),

                Action::make('validar')
                    ->label('Conferir QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (SolicitacaoDocumento $record) => route('documentos.validar-autenticidade', $record->codigo_verificacao))
                    ->openUrlInNewTab(),

                Action::make('recusar')
                    ->label('Recusar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SolicitacaoDocumento $record) => $record->status === StatusSolicitacaoDocumento::Solicitado)
                    ->form([
                        Textarea::make('justificativa_recusa')
                            ->label('Motivo da Recusa')
                            ->placeholder('Ex: O estudante possui pendência na entrega de documentos básicos ou taxa administrativa em aberto.')
                            ->required(),
                    ])
                    ->action(function (SolicitacaoDocumento $record, array $data) {
                        $record->update([
                            'status' => StatusSolicitacaoDocumento::Rejeitado,
                            'justificativa_recusa' => $data['justificativa_recusa'],
                            'atendido_por_user_id' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Solicitação Recusada')
                            ->body('A solicitação foi marcada como recusada e a família poderá consultar o motivo no portal.')
                            ->warning()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
