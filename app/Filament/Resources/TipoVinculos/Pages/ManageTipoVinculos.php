<?php

namespace App\Filament\Resources\TipoVinculos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TipoVinculos\TipoVinculoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTipoVinculos extends ManageRecords
{
    use HasAjudaAction;

    protected static string $resource = TipoVinculoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🔗', 'Tipos de Vínculo', 'Classificações do vínculo entre pessoas (por exemplo, parentesco).', 'TipoVinculo',
                'Veja o nome de cada tipo de vínculo.', 'Cadastre um novo tipo de vínculo.', 'Altere o nome do tipo.',
                dica: 'Esta tela abre os formulários em janelas, sem sair da listagem.'),
        ];
    }
}
