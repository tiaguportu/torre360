<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConselhosTutelares\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ConselhosTutelares\ConselhoTutelarResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConselhoTutelar extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ConselhoTutelarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar Conselho Tutelar', HelpContent::make('🏛️', 'Editar Conselho Tutelar', 'Atualize os dados do conselho.')
                ->secao('📌 Bom saber', [
                    ['📄', 'Casos já abertos', 'Mudar o endereço aqui não altera ofícios já criados: cada documento guarda a cópia do texto que foi gerado.'],
                    ['🚫', 'Inativar', 'Desligue "Ativo" em vez de excluir se o conselho deixou de ser o da unidade.'],
                ])),
        ];
    }
}
