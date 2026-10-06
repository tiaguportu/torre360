<?php

namespace App\Filament\Resources\PropostaComercials\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPropostaComercials extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PropostaComercialResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        $conteudo = HelpContent::make(
            '🎯',
            'Simulador de Propostas Comerciais & Alçadas de Desconto',
            'Gerencie propostas comerciais, simule condições personalizadas e controle concessões de desconto através de alçadas pré-definidas (Consultor, Coordenação e Diretoria).'
        )
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem e Alçadas', 'Acompanhe as propostas abertas, o nível de aprovação necessário e a data de validade de cada oferta.'],
                $user?->can('Create:PropostaComercial') ? ['🆕', 'Nova Proposta', 'Simule a mensalidade de tabela com desconto em tempo real e gere o código oficial da proposta.'] : null,
                ['✅', 'Aprovar / Recusar', 'Usuários com alçada adequada podem aprovar diretamente ou recusar informando a justificativa comercial.'],
                ['💬', 'Enviar por WhatsApp', 'Abra o WhatsApp do responsável com a mensagem formatada contendo todas as condições e validade.'],
                ['🎓', 'Matricular', 'Propostas aprovadas ou aceitas podem ser encaminhadas diretamente ao Assistente de Matrícula.'],
            ])
            ->dica('Descontos de até 7% são aprovados automaticamente pelo consultor. Acima de 7%, a proposta fica aguardando aprovação da chefia.');

        return [
            CreateAction::make(),
            $this->ajudaAction('Propostas Comerciais', $conteudo),
        ];
    }
}
