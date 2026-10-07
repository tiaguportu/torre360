<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensagemWhatsappTemplate extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = MensagemWhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar Modelo de WhatsApp', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('✏️', 'Editar Modelo de WhatsApp', 'Atualize o texto do comunicado ou desative-o temporariamente.')
            ->secao('🏷️ Variáveis Dinâmicas Disponíveis', [
                ['👤', '[Nome do Responsável]', 'Substituído pelo nome completo do responsável.'],
                ['👋', '[Primeiro Nome]', 'Substituído pelo primeiro nome do responsável.'],
                ['🎒', '[Nome do Aluno]', 'Substituído pelo nome do aluno pretendido.'],
                ['📅', '[Horário de Visita Agendada]', 'Data e hora da visita agendada (ex: 15/10/2026 às 14:00h).'],
                ['📊', '[Link da Pesquisa da Visita]', 'URL única da pesquisa de satisfação da visita.'],
                ['🏫', '[Nome da Escola]', 'Nome oficial da instituição configurado no sistema.'],
            ])
            ->dica('Se você desativar o modelo, ele deixará de ser sugerido para os consultores, mas os históricos anteriores permanecem intactos.');
    }
}
