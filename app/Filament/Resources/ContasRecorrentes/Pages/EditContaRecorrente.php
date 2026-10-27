<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContasRecorrentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ContasRecorrentes\ContaRecorrenteResource;
use App\Filament\Resources\ContasRecorrentes\Tables\ContasRecorrentesTable;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContaRecorrente extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ContaRecorrenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContasRecorrentesTable::reajuste(),
            DeleteAction::make(),
            $this->ajudaAction('Editar despesa recorrente', HelpContent::make('🔁', 'Editar despesa recorrente', 'Mudanças valem para as contas que ainda vão ser geradas.')
                ->secao('📌 Bom saber', [
                    ['🧾', 'Contas já geradas', 'Não mudam sozinhas. Para um novo valor de contrato, use "Registrar reajuste": ele atualiza as abertas que seguem no valor antigo.'],
                    ['⏹️', 'Encerrar o contrato', 'Preencha "Vale até" ou desligue "Ativa". Excluir a despesa não apaga as contas já geradas.'],
                ])),
        ];
    }
}
