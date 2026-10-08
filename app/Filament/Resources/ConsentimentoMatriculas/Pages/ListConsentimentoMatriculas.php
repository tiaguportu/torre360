<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ConsentimentoMatriculas\ConsentimentoMatriculaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConsentimentoMatriculas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ConsentimentoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Consentimentos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🛡️', 'Consentimentos', 'Indicador rápido: o que cada família já autorizou (ex.: uso de imagem).')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja aluno, tipo de consentimento, status e vigência.'],
                ['🆕', 'Registrar manualmente', 'Use quando a resposta foi colhida fora do Portal (ex.: papel assinado presencialmente).'],
            ])
            ->dica('A resposta normal da família acontece no Portal. O status aqui já considera a vigência: um "Autorizado" vencido aparece como "Pendente" automaticamente, sem precisar editar nada.');
    }
}
