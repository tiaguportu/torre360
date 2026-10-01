<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMensagemWhatsappTemplates extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = MensagemWhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Modelos de WhatsApp', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('💬', 'Modelos de WhatsApp', 'Textos prontos usados no acompanhamento dos interessados.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, trecho da mensagem, situação (ativo) e última atualização.'],
                $user->can('Create:MensagemWhatsappTemplate') ? ['🆕', 'Novo Modelo', 'Escreva um texto reutilizável para o atendimento.'] : null,
                $user->can('Update:MensagemWhatsappTemplate') ? ['✏️', 'Editar', 'Atualize o texto ou desative um modelo que não deve mais ser usado.'] : null,
            ])
            ->dica('Modelos inativos deixam de ser oferecidos no atendimento, mas continuam guardados.');
    }
}
