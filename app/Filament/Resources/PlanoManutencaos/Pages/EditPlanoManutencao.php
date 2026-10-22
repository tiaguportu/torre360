<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoManutencaos\PlanoManutencaoResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanoManutencao extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PlanoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Plano de Manutenção', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🛠️', 'Editar Plano de Manutenção', 'Ajuste de periodicidade e itens contemplados no plano de manutenção.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['✏️', 'Checklist de Ações', 'Atualize as instruções de inspeção técnica dos itens.'],
                ['📅', 'Datas e Responsáveis', 'Realoque técnicos e revise o calendário de manutenções.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PlanoManutencao') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PlanoManutencao') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PlanoManutencao') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
