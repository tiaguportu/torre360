<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequisicoesCompra extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = RequisicaoCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova requisição'),
            $this->ajudaAction('Compras', HelpContent::make('🛒', 'Compras e cotações', 'Do pedido do setor ao recebimento: cotação, aprovação pela alçada, nota fiscal e conta a pagar.')
                ->passos('🔁 O caminho de uma compra', [
                    'O setor cria a requisição, lista os itens e envia para cotação.',
                    'O comprador registra as propostas dos fornecedores. O mapa compara item por item e marca a mais barata.',
                    'Escolhida a cotação, o pedido vai para a aprovação. O valor define quem aprova (alçada) e o saldo do orçamento aparece para quem decide.',
                    'Aprovado, quem recebe confere a entrega e anexa a nota fiscal. O recebimento dá entrada no estoque, cadastra o bem no patrimônio e gera a conta a pagar.',
                ])
                ->secao('📌 Bom saber', [
                    ['👥', 'Funções separadas', 'Quem solicita não aprova, e quem aprova não recebe.'],
                    ['⚖️', 'Cotações mínimas', 'Quanto maior o valor, mais cotações; sem elas é preciso justificar a dispensa.'],
                    ['⚠️', 'Fornecedor sinalizado', 'Recebimento com divergência nos últimos meses aparece ao lado do fornecedor no mapa.'],
                    ['👀', 'O que você vê', 'Suas próprias requisições. Quem tem a permissão "Ver todas" enxerga as dos outros setores.'],
                ])),
        ];
    }
}
