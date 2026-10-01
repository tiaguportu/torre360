<?php

namespace App\Filament\Resources\Salas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Salas\SalaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Salas', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🏫', 'Salas', 'Espaços físicos de cada unidade.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, tipo, unidade, capacidade e se a sala está ativa.'],
                $user->can('Create:Sala') ? ['🆕', 'Nova Sala', 'Cadastre a sala com unidade, tipo e capacidade.'] : null,
                $user->can('Update:Sala') ? ['✏️', 'Editar', 'Ajuste a capacidade ou desative uma sala em desuso.'] : null,
                ['🔎', 'Filtros', 'Filtre por unidade e por salas ativas ou inativas.'],
            ])
            ->dica('Prefira desativar a excluir uma sala em desuso, para não perder o histórico.');
    }
}
