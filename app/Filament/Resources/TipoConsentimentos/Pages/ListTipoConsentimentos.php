<?php

namespace App\Filament\Resources\TipoConsentimentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TipoConsentimentos\TipoConsentimentoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTipoConsentimentos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = TipoConsentimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Tipos de Consentimento', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🛡️', 'Tipos de Consentimento', 'Catálogo de consentimentos que a família responde no Portal (ex.: uso de imagem).')
            ->secao('🎯 O que você pode fazer?', [
                ['🆕', 'Novo tipo', 'Cadastre um novo consentimento (nome, texto, se exige renovação periódica).'],
                ['✏️', 'Editar', 'Ajuste o texto ou desative um tipo sem apagar o histórico de respostas.'],
            ])
            ->dica('Consentimentos com renovação periódica voltam a pedir resposta da família automaticamente quando a vigência vence.');
    }
}
