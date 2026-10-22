<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortfolioItems\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PortfolioItems\PortfolioItemResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPortfolioItem extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PortfolioItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Item de Portfólio', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🎨', 'Editar Item de Portfólio', 'Alteração de mídias, descrição e visibilidade do portfólio.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['✏️', 'Descrição e Habilidades', 'Atualize o relato pedagógico e as competências desenvolvidas.'],
                ['👁️', 'Publicação', 'Controle se o item já está disponível para consulta pelos pais.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:PortfolioItem') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:PortfolioItem') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:PortfolioItem') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
