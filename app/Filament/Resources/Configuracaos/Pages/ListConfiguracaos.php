<?php

namespace App\Filament\Resources\Configuracaos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Configuracaos\ConfiguracaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracaos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ConfiguracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('⚙️', 'Configurações', 'Parâmetros gerais do sistema, organizados por grupo.', 'Configuracao',
                'Veja campo, grupo e ordem de cada parâmetro.', 'Cadastre um novo parâmetro.', 'Altere o valor de um parâmetro.',
                dica: 'Altere com cuidado: estes parâmetros afetam o comportamento do sistema.'),
        ];
    }
}
