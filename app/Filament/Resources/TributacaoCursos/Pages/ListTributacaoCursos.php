<?php

namespace App\Filament\Resources\TributacaoCursos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TributacaoCursos\TributacaoCursoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTributacaoCursos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = TributacaoCursoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🧮', 'Tributações dos Cursos', 'Dados fiscais de cada curso.', 'TributacaoCurso',
                'Veja curso, CNAE, item de serviço, ISS, PIS e COFINS.', 'Cadastre a tributação de um curso.', 'Atualize as alíquotas e códigos fiscais.',
                dica: 'Confirme os valores com a contabilidade antes de alterar alíquotas.'),
        ];
    }
}
