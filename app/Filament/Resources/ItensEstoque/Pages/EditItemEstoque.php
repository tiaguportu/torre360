<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ItensEstoque\ItemEstoqueResource;
use App\Filament\Resources\ItensEstoque\Tables\ItensEstoqueTable;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemEstoque extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ItemEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ItensEstoqueTable::entrada(),
            ItensEstoqueTable::saida(),
            ItensEstoqueTable::ajuste(),
            DeleteAction::make(),
            $this->ajudaAction('Editar item de estoque', HelpContent::make('📦', 'Editar item de estoque', 'Dados do item e histórico de movimentações.')
                ->secao('📌 Bom saber', [
                    ['📜', 'Histórico', 'A aba Movimentações mostra cada entrada, saída e ajuste, com o saldo depois de cada um.'],
                    ['🗑️', 'Excluir', 'Só vale para item sem movimentação. Os demais, desligue "Ativo".'],
                ])),
        ];
    }
}
