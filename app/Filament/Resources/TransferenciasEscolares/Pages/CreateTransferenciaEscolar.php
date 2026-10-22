<?php

namespace App\Filament\Resources\TransferenciasEscolares\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TransferenciasEscolares\TransferenciaEscolarResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateTransferenciaEscolar extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = TransferenciaEscolarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova Transferência Escolar', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🔄', 'Nova Transferência Escolar', 'Registro de remanejamento interno de turma ou expedição de transferência de aluno.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['🎓', 'Matrícula de Origem', 'Selecione o estudante que passará pelo processo de transferência.'],
                ['🏫', 'Destino', 'Defina se a transferência é interna para outra turma/unidade ou externa.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:TransferenciaEscolar') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:TransferenciaEscolar') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:TransferenciaEscolar') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
