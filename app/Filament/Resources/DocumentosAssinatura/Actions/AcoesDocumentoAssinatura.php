<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Actions;

use App\Models\DocumentoAssinatura;
use App\Services\Documentos\AssinaturaDocumentoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Ações de um documento de assinatura eletrônica (Onda 69), iguais na lista e na página do documento. Cada uma confere a
 * permissão e o estado do documento: o super admin passa por qualquer habilidade (Gate::before), então a política sozinha
 * não esconde "Enviar" de um documento que já foi enviado.
 */
final class AcoesDocumentoAssinatura
{
    public static function enviar(): Action
    {
        return Action::make('enviar')
            ->label('Enviar para assinatura')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (DocumentoAssinatura $record): bool => $record->podeEnviar() && (bool) auth()->user()?->can('enviar', $record))
            ->requiresConfirmation()
            ->modalHeading('Enviar para assinatura eletrônica')
            ->modalDescription(fn (DocumentoAssinatura $record): string => 'Cada assinante recebe um e-mail do Assinafy com o link para assinar. Assinam: '.$record->assinantes()->pluck('nome')->implode(', ').'.')
            ->action(function (DocumentoAssinatura $record): void {
                try {
                    app(AssinaturaDocumentoService::class)->enviar($record, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Documento enviado')->body('Os assinantes receberam o e-mail do Assinafy.')->success()->send();
            });
    }

    public static function atualizar(): Action
    {
        return Action::make('atualizar')
            ->label('Atualizar situação')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (DocumentoAssinatura $record): bool => $record->podeSerConsultado() && (bool) auth()->user()?->can('atualizar', $record))
            ->action(function (DocumentoAssinatura $record): void {
                try {
                    $documento = app(AssinaturaDocumentoService::class)->atualizar($record);
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível atualizar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Situação: '.$documento->status->getLabel())->success()->send();
            });
    }

    public static function links(): Action
    {
        return Action::make('links')
            ->label('Links de assinatura')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->visible(fn (DocumentoAssinatura $record): bool => $record->aguardaAssinaturas() && (bool) auth()->user()?->can('verLinks', $record))
            ->modalHeading('Links de assinatura')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn (DocumentoAssinatura $record) => view('filament.documentos-assinatura.links', ['assinantes' => $record->assinantes()->get()]));
    }

    public static function cancelar(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (DocumentoAssinatura $record): bool => $record->emAndamento() && (bool) auth()->user()?->can('cancelar', $record))
            ->requiresConfirmation()
            ->modalHeading('Cancelar o documento')
            ->modalDescription('Quem ainda não assinou deixa de poder assinar (o documento sai do Assinafy). Isso não desfaz assinaturas já dadas.')
            ->action(function (DocumentoAssinatura $record): void {
                try {
                    app(AssinaturaDocumentoService::class)->cancelar($record, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível cancelar')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Documento cancelado')->success()->send();
            });
    }

    public static function baixarOriginal(): Action
    {
        return Action::make('baixar_original')
            ->label('PDF enviado')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (DocumentoAssinatura $record): bool => filled($record->arquivo_original_path) && (bool) auth()->user()?->can('view', $record))
            ->url(fn (DocumentoAssinatura $record): string => route('documentos.visualizar', ['path' => $record->arquivo_original_path]))
            ->openUrlInNewTab();
    }

    public static function baixarAssinado(): Action
    {
        return Action::make('baixar_assinado')
            ->label('PDF assinado')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (DocumentoAssinatura $record): bool => filled($record->arquivo_assinado_path) && (bool) auth()->user()?->can('view', $record))
            ->url(fn (DocumentoAssinatura $record): string => route('documentos.visualizar', ['path' => $record->arquivo_assinado_path]))
            ->openUrlInNewTab();
    }
}
