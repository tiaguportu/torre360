<?php

namespace App\Filament\Resources\Habilidades\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Habilidades\HabilidadeResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHabilidades extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = HabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Habilidades (BNCC)', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📚', 'Habilidades (BNCC)', 'Catálogo de habilidades do currículo, usado na avaliação por habilidades.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja código, nome, tipo e o campo de experiência de cada habilidade.'],
                $user->can('Create:Habilidade') ? ['🆕', 'Nova Habilidade', 'Cadastre uma habilidade com código, nome, tipo e campo de experiência.'] : null,
                $user->can('Update:Habilidade') ? ['✏️', 'Editar', 'Ajuste o texto ou a classificação de uma habilidade.'] : null,
            ])
            ->dica('As habilidades cadastradas aqui ficam disponíveis em Avaliação de Habilidades e em Notas de Habilidades.');
    }
}
