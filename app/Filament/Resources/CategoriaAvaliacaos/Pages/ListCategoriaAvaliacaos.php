<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CategoriaAvaliacaos\CategoriaAvaliacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaAvaliacaos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CategoriaAvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🗂️', 'Categorias de Avaliação', 'Tipos de avaliação (prova, trabalho, recuperação etc.).', 'CategoriaAvaliacao',
                'Veja nome, descrição, ordem no boletim, se é recuperação e qual categoria ela substitui.', 'Cadastre uma categoria de avaliação.', 'Ajuste nome, ordem no boletim ou regra de substituição.',
                extras: [['♻️', 'Recuperação', 'Marque "recuperação" e escolha a categoria que ela substitui.']]),
        ];
    }
}
