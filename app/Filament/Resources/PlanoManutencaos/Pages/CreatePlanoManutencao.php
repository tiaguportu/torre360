<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoManutencaos\PlanoManutencaoResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreatePlanoManutencao extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = PlanoManutencaoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Plano de Manutenção', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🛠️', 'Novo Plano de Manutenção', 'Programação de manutenção preventiva para espaços e bens patrimoniais.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['🏢', 'Espaço ou Patrimônio', 'Selecione a sala, área ou equipamento coberto pelo plano.'],
                ['⏱️', 'Periodicidade', 'Defina o intervalo entre as revisões e o checklist preventivo.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PlanoManutencao') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PlanoManutencao') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PlanoManutencao') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
