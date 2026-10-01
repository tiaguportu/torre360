<?php

namespace App\Filament\Resources\LandingLeads\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\LandingLeads\LandingLeadResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\ListRecords;

class ListLandingLeads extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = LandingLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Leads da Landing Page', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🌐', 'Leads da Landing Page', 'Contatos recebidos pelo formulário público de captação.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, e-mail, WhatsApp, mensagem, data de recebimento e status de cada contato.'],
                ['📞', 'Em contato', 'Marque quando já iniciou o atendimento ao lead.'],
                ['🗑️', 'Descartar', 'Retire contatos inválidos ou duplicados da fila de trabalho.'],
                ['♻️', 'Reabrir', 'Volte um lead descartado ou em contato para a fila.'],
                ['🔎', 'Filtro de status', 'Mostre apenas os leads em determinada situação.'],
            ])
            ->dica('Os leads são criados automaticamente pelo site; não é possível cadastrá-los manualmente aqui.');
    }
}
