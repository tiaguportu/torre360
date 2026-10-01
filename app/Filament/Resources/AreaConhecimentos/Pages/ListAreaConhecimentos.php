<?php

namespace App\Filament\Resources\AreaConhecimentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AreaConhecimentos\AreaConhecimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAreaConhecimentos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = AreaConhecimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🧠', 'Áreas de Conhecimento', 'Agrupamentos de disciplinas do currículo.', 'AreaConhecimento',
                'Veja o nome de cada área.', 'Cadastre uma área de conhecimento.', 'Altere o nome da área.'),
        ];
    }
}
