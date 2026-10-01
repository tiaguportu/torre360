<?php

namespace App\Filament\Resources\CampoExperiencias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CampoExperiencias\CampoExperienciaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCampoExperiencias extends ManageRecords
{
    use HasAjudaAction;

    protected static string $resource = CampoExperienciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🎨', 'Campos de Experiência', 'Campos de experiência da educação infantil (BNCC).', 'CampoExperiencia',
                'Veja nome, descrição e quantas habilidades estão ligadas a cada campo.', 'Cadastre um campo de experiência.', 'Ajuste nome ou descrição.',
                dica: 'As habilidades são cadastradas em Currículo (BNCC) → Habilidades.'),
        ];
    }
}
