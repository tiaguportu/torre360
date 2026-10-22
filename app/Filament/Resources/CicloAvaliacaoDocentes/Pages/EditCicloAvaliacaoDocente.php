<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CicloAvaliacaoDocentes\CicloAvaliacaoDocenteResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCicloAvaliacaoDocente extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = CicloAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Ciclo de Avaliação Docente', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🔄', 'Editar Ciclo de Avaliação Docente', 'Alteração de prazos e parâmetros do ciclo avaliativo.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📅', 'Prorrogação de Prazos', 'Ajuste a data de término caso necessário para conclusão das avaliações.'],
                ['⚙️', 'Status do Ciclo', 'Altere entre Planejado, Em Andamento e Concluído conforme a etapa.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:CicloAvaliacaoDocente') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:CicloAvaliacaoDocente') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:CicloAvaliacaoDocente') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
