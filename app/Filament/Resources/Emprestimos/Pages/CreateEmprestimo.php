<?php

namespace App\Filament\Resources\Emprestimos\Pages;

use App\Filament\Resources\Emprestimos\EmprestimoResource;
use App\Models\Emprestimo;
use App\Models\Livro;
use Filament\Resources\Pages\CreateRecord;

class CreateEmprestimo extends CreateRecord
{
    protected static string $resource = EmprestimoResource::class;

    protected function handleRecordCreation(array $data): Emprestimo
    {
        $emprestimo = Emprestimo::create($data);

        Livro::where('id', $data['livro_id'])->decrement('quantidade_disponivel');

        return $emprestimo;
    }
}
