<?php

namespace App\Filament\Resources\ComunicacaoEmMassas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ComunicacaoEmMassas\ComunicacaoEmMassaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComunicacaoEmMassas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ComunicacaoEmMassaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Comunicação em Massa', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📢', 'Comunicação em Massa', 'Envie um mesmo aviso para vários interessados ou responsáveis de uma vez.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Acompanhe status, público, total de destinatários, enviados e falhas de cada comunicação.'],
                $user->can('Create:ComunicacaoEmMassa') ? ['🆕', 'Nova Comunicação', 'Defina nome, canal, assunto e o público por origem, status do funil ou turma.'] : null,
                $user->can('Update:ComunicacaoEmMassa') ? ['✏️', 'Editar', 'Altere o conteúdo enquanto a comunicação ainda não foi enviada.'] : null,
                ['🚀', 'Enviar', 'Dispara a mensagem para o público escolhido; o resultado aparece em Enviados e Falhas.'],
            ])
            ->alerta('O envio em massa não pode ser desfeito. Revise público e texto antes de clicar em Enviar.')
            ->dica('Use os filtros de status e de público para localizar comunicações anteriores.');
    }
}
