<?php

namespace App\Filament\Resources\TipoConsentimentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TipoConsentimentos\TipoConsentimentoResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoConsentimento extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = TipoConsentimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar Tipo de Consentimento', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        $secao = [
            ['✏️', 'Edição de Textos', 'Ajuste os termos explicativos e finalidades exibidas no Portal.'],
            ['⚙️', 'Status e Validade', 'Ative ou desative o consentimento sem perder o histórico de respostas anteriores.'],
        ];

        if ($user?->can('Delete:TipoConsentimento')) {
            $secao[] = ['🗑️', 'Exclusão', 'Exclua este tipo se não houver respostas vinculadas a ele.'];
        }

        return HelpContent::make('🛡️', 'Editar Tipo de Consentimento', 'Permite atualizar os textos jurídicos, regras de validade ou desativar uma finalidade.')
            ->secao('🎯 O que você pode fazer?', $secao)
            ->dica('Em vez de excluir um tipo de consentimento já utilizado, prefira desativá-lo para preservar a trilha de auditoria.');
    }
}
