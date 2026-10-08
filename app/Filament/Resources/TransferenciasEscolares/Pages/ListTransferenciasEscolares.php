<?php

namespace App\Filament\Resources\TransferenciasEscolares\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TransferenciasEscolares\TransferenciaEscolarResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferenciasEscolares extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = TransferenciaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Transferências Escolares', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🔄', 'Transferências Escolares', 'Processo de saída (para outra escola) e entrada (vinda de outra escola).')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja aluno, tipo, escola externa, data e status.'],
                $user->can('Create:TransferenciaEscolar') ? ['🆕', 'Novo processo', 'Registre uma saída (para outra escola) ou entrada (vinda de outra escola).'] : null,
                ['📄', 'Concluir Saída', 'Emite a Declaração de Transferência oficial (PDF com QR Code) e encerra a matrícula.'],
                ['📥', 'Marcar Histórico Recebido', 'Encerre o processo de Entrada depois de lançar os anos externos no Histórico Escolar do aluno.'],
            ])
            ->dica('O histórico escolar de quem está chegando é lançado na tela de Histórico Escolar (Secretaria), marcando o ano como "Externo". Pendências financeiras não impedem a emissão da Declaração de Transferência — a lei não permite reter esse documento por inadimplência.');
    }
}
