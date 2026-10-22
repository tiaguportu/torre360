<?php

namespace App\Filament\Resources\TransferenciasEscolares\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TransferenciasEscolares\TransferenciaEscolarResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransferenciaEscolar extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = TransferenciaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Transferência Escolar', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🔄', 'Editar Transferência Escolar', 'Atualização do status, motivo ou documentos vinculados à transferência.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📌', 'Status do Processo', 'Atualize para Solicitada, Em Análise, Concluída ou Cancelada.'],
                ['📄', 'Documentação', 'Anexe a guia de transferência e comprovantes oficiais emitidos.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:TransferenciaEscolar') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:TransferenciaEscolar') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:TransferenciaEscolar') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
