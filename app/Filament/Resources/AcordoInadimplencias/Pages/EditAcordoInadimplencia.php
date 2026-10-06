<?php

namespace App\Filament\Resources\AcordoInadimplencias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AcordoInadimplencias\AcordoInadimplenciaResource;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcordoInadimplencia extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = AcordoInadimplenciaResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '✏️',
            'Editar Acordo de Inadimplência',
            'Ajuste o status ou anote observações do acompanhamento da renegociação financeira.'
        );

        return [
            Action::make('verTermo')
                ->label('Ver Termo de Confissão')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->modalHeading('Termo de Confissão e Transação de Dívida')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(fn () => view('filament.components.termo-confissao-modal', [
                    'acordo' => $this->getRecord(),
                ])),

            DeleteAction::make(),
            $this->ajudaAction('Editar Acordo', $conteudo),
        ];
    }
}
