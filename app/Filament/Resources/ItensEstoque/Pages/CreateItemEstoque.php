<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ItensEstoque\ItemEstoqueResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateItemEstoque extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ItemEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo item de estoque', HelpContent::make('📦', 'Novo item de estoque', 'O cadastro não leva saldo: o saldo nasce com a primeira entrada.')
                ->secao('🎯 Como preencher', [
                    ['📏', 'Unidade de medida', 'A mesma em que o item é comprado e retirado (caixa, resma, litro...).'],
                    ['🔔', 'Estoque mínimo', 'Quando o saldo chega a este valor, o item vai para a fila da Mesa. Zero desliga o aviso.'],
                    ['🏷️', 'Conta do plano', 'Classifica as compras deste item no orçamento.'],
                ])),
        ];
    }
}
