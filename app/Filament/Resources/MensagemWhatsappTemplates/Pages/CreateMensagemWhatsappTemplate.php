<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateMensagemWhatsappTemplate extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = MensagemWhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Criar Modelo de WhatsApp', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🆕', 'Criar Modelo de WhatsApp', 'Cadastre uma mensagem padronizada para comunicados oficiais ou disparo rápido.')
            ->secao('🏷️ Variáveis Dinâmicas Disponíveis', [
                ['👤', '[Nome do Responsável]', 'Substituído pelo nome completo do contato cadastrado no lead.'],
                ['👋', '[Primeiro Nome]', 'Substituído apenas pelo primeiro nome para um tom mais próximo.'],
                ['🎒', '[Nome do Aluno]', 'Substituído pelo nome da criança/dependente do interessado.'],
                ['📅', '[Horário de Visita Agendada]', 'Data e hora da visita agendada (ex: 15/10/2026 às 14:00h).'],
                ['📊', '[Link da Pesquisa da Visita]', 'URL única da pesquisa de satisfação da visita do lead.'],
                ['🏫', '[Nome da Escola]', 'Nome oficial da instituição configurado no sistema.'],
            ])
            ->dica('Este modelo também poderá ser selecionado no Copiloto IA (Gemini) como base oficial para personalização.');
    }
}
