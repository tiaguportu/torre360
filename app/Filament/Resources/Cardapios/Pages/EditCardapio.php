<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Cardapios\CardapioResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCardapio extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = CardapioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Cardápio Escolar', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🍲', 'Editar Cardápio Escolar', 'Alteração de itens, refeições e alérgenos do cardápio da unidade.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['✏️', 'Atualização do Menu', 'Modifique os pratos informados para cada dia da semana.'],
                ['⚠️', 'Alérgenos', 'Ajuste os marcadores de restrição alimentar conforme necessário.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:Cardapio') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:Cardapio') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:Cardapio') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
