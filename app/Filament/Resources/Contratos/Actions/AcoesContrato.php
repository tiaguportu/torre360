<?php

namespace App\Filament\Resources\Contratos\Actions;

use App\Models\Contrato;
use App\Services\AssinafyService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AcoesContrato
{
    public static function visualizarContrato(): Action
    {
        return Action::make('visualizar_contrato')
            ->label(fn (?Contrato $record) => $record?->estaAssinado() ? 'Ver Contrato Assinado' : 'Assinar Contrato')
            ->icon(fn (?Contrato $record) => $record?->estaAssinado() ? 'heroicon-o-document-magnifying-glass' : 'heroicon-o-document-check')
            ->color(fn (?Contrato $record) => $record?->estaAssinado() ? 'success' : 'warning')
            ->action(function (Contrato $record, AssinafyService $service) {
                if ($record->estaAssinado()) {
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
            });
    }

    public static function sincronizarAssinafy(): Action
    {
        return Action::make('sincronizar_assinafy')
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
            ->visible(fn (?Contrato $record) => $record && $record->assinafy_id !== null);
    }

    public static function downloadContrato(): Action
    {
        return Action::make('download_contrato')
            ->label('Download PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->url(fn (?Contrato $record) => $record ? route('contratos.download', $record) : null)
            ->visible(fn (?Contrato $record) => $record && $record->assinafy_id !== null);
    }
}
