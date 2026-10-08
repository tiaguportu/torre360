<?php

namespace App\Filament\Resources\Emprestimos\Pages;

use App\Filament\Resources\Emprestimos\EmprestimoResource;
use App\Models\Emprestimo;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEmprestimo extends CreateRecord
{
    protected static string $resource = EmprestimoResource::class;

    protected function handleRecordCreation(array $data): Emprestimo
    {
        try {
            return Emprestimo::emprestar(
                (int) $data['livro_id'],
                (int) $data['matricula_id'],
                $data['data_emprestimo'] ?? null,
                $data['data_prevista_devolucao'] ?? null,
            );
        } catch (\DomainException $e) {
            Notification::make()
                ->danger()
                ->title('Empréstimo não registrado')
                ->body($e->getMessage())
                ->send();

            $this->halt();
        }
    }
}
