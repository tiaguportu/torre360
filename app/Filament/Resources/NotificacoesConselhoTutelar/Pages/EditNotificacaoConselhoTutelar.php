<?php

namespace App\Filament\Resources\NotificacoesConselhoTutelar\Pages;

use App\Filament\Resources\NotificacoesConselhoTutelar\NotificacaoConselhoTutelarResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditNotificacaoConselhoTutelar extends EditRecord
{
    protected static string $resource = NotificacaoConselhoTutelarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Notificação')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        return '<p>Edite os dados cadastrais da notificação, atualize o status ou anote o protocolo retornado pelo Conselho Tutelar.</p>';
    }
}
