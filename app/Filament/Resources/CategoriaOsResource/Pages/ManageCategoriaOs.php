<?php

namespace App\Filament\Resources\CategoriaOsResource\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CategoriaOsResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCategoriaOs extends ManageRecords
{
    use HasAjudaAction;

    protected static string $resource = CategoriaOsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->ajudaCadastro('🏷️', 'Categorias de OS', 'Classificação das Ordens de Serviço.', 'CategoriaOs',
                'Veja o nome de cada categoria.', 'Cadastre uma categoria de ordem de serviço.', 'Altere o nome da categoria.',
                dica: 'Esta tela abre os formulários em janelas, sem sair da listagem.'),
        ];
    }
}
