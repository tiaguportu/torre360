<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ItensEstoque\ItemEstoqueResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItensEstoque extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ItemEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Estoque', HelpContent::make('📦', 'Estoque de consumíveis', 'Material de limpeza, papelaria e descartáveis, com saldo, mínimo e histórico.')
                ->passos('🔁 Como usar', [
                    'Cadastre o item com a unidade de medida e o estoque mínimo.',
                    'Registre a entrada quando o material chega (o que vem de um pedido de compra entra sozinho no recebimento) e a saída quando um setor retira.',
                    'Em inventário, use "Ajuste": informe o que foi contado e o motivo; o sistema registra a diferença.',
                ])
                ->secao('📌 Bom saber', [
                    ['🚫', 'Sem saldo negativo', 'A saída recusa mais do que há em estoque.'],
                    ['🧮', 'Custo médio', 'Cada entrada com custo recalcula o custo médio ponderado do item.'],
                    ['🔔', 'Mínimo', 'Item no mínimo ou abaixo dele aparece na fila "Estoque abaixo do mínimo" da Minha Mesa.'],
                ])),
        ];
    }
}
