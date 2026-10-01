<?php

namespace App\Filament\Resources\Notas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Notas\NotaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = NotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Notas', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('💯', 'Notas', 'Consulta dos valores lançados por matrícula em cada avaliação.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja a matrícula, a avaliação e o valor da nota, com data de criação e de atualização.'],
                ['👁️', 'Visualizar', 'Abra o registro de uma nota para ver os detalhes.'],
                $user->can('Create:Nota') ? ['🆕', 'Nova Nota', 'Lance uma nota avulsa para uma matrícula em uma avaliação.'] : null,
                $user->can('Update:Nota') ? ['✏️', 'Editar', 'Corrija o valor de uma nota já lançada.'] : null,
            ])
            ->dica('Para lançar as notas de uma turma inteira de uma vez, use Avaliações → Lançamento de Notas em Grade.');
    }
}
