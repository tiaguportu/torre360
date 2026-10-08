<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Pages\ConfiguracaoCopilotoIa;
use App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource;
use App\Support\HelpContent;
use Filament\Actions\Action;
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
            Action::make('comportamentoIa')
                ->label('Comportamento do Copiloto IA')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(fn (): string => ConfiguracaoCopilotoIa::getUrl())
                ->visible(fn (): bool => ConfiguracaoCopilotoIa::canAccess()),
            $this->ajudaAction('Modelos de WhatsApp', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('💬', 'Modelos de WhatsApp', 'Textos padronizados e oficiais para envio ágil e links transacionais no CRM.')
            ->secao('🎯 Propósito e Quando Utilizar', [
                ['⚡', 'Disparo Ágil (0s Latência)', 'Envio instantâneo sem custo de inteligência artificial ou tempo de resposta de rede.'],
                ['🔗', 'Links e Transações', 'Obrigatório para mensagens com links seguros (ex: Pesquisa de Satisfação Pós-Visita, editais e termos).'],
                ['🏛️', 'Conformidade Institucional', 'Garante que comunicados formais, prazos de matrícula e termos contratuais sigam exatamente o texto aprovado.'],
                ['✨', 'Base para o Copiloto IA', 'Estes modelos ficam disponíveis no Copiloto IA (Gemini) como ponto de partida oficial para personalização humanizada.'],
                ['🧠', 'Comportamento da IA', 'Cada modelo aceita "Instruções para a IA" (ex.: manter um prazo, não oferecer desconto). Tom, persona e regras gerais ficam em "Comportamento do Copiloto IA".'],
            ])
            ->secao('⚙️ O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, trecho da mensagem, situação (ativo) e última atualização.'],
                $user->can('Create:MensagemWhatsappTemplate') ? ['🆕', 'Novo Modelo', 'Cadastre uma nova mensagem reutilizável para a equipe comercial.'] : null,
                $user->can('Update:MensagemWhatsappTemplate') ? ['✏️', 'Editar', 'Atualize o texto, as instruções para a IA ou desative um modelo que não deve mais ser oferecido.'] : null,
                ConfiguracaoCopilotoIa::canAccess() ? ['🎛️', 'Comportamento do Copiloto IA', 'Ajuste persona, diretrizes, o que a IA menciona ou evita, descrição dos objetivos e dos tons.'] : null,
            ])
            ->dica('Para abordagens consultivas e superação de objeções complexas, use o botão "Copiloto WhatsApp IA" na lista de leads.');
    }
}
