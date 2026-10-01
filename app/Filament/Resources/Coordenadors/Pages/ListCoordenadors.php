<?php

namespace App\Filament\Resources\Coordenadors\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Coordenadors\CoordenadorResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoordenadors extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CoordenadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Coordenadores', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🧭', 'Coordenadores', 'Vincule pessoas à coordenação de cursos.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja a pessoa, o cargo, o curso coordenado, a data de início e se o acesso é somente leitura.'],
                $user->can('Create:Coordenador') ? ['🆕', 'Novo Coordenador', 'Escolha a pessoa, o curso e o cargo.'] : null,
                $user->can('Update:Coordenador') ? ['✏️', 'Editar', 'Altere cargo, curso ou marque o acesso como somente leitura.'] : null,
            ])
            ->dica('Marque "somente leitura" para quem precisa acompanhar o curso sem alterar dados.');
    }
}
