<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Schemas;

use App\Models\ItemRequisicaoCompra;
use App\Models\RecebimentoCompra;
use App\Models\RequisicaoCompra;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequisicaoCompraInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Requisição')
                ->columns(3)
                ->schema([
                    TextEntry::make('numero')->label('Número')->weight('bold'),
                    TextEntry::make('status')->label('Situação')->badge(),
                    TextEntry::make('urgencia')->label('Urgência')->badge()
                        ->formatStateUsing(fn (string $state): string => $state === 'urgente' ? 'Urgente' : 'Normal')
                        ->color(fn (string $state): string => $state === 'urgente' ? 'danger' : 'gray'),
                    TextEntry::make('titulo')->label('Compra')->columnSpan(2),
                    TextEntry::make('solicitante.name')->label('Solicitante')->placeholder('—'),
                    TextEntry::make('centroCusto.nome')->label('Setor')->placeholder('—'),
                    TextEntry::make('planoConta.nome')->label('Conta do plano')->placeholder('—'),
                    TextEntry::make('enviada_em')->label('Enviada para cotação')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('justificativa')->label('Por que é necessário')->columnSpanFull(),
                    TextEntry::make('cotacao_dispensada_justificativa')->label('Dispensa de cotações')->columnSpanFull()->visible(fn (RequisicaoCompra $record): bool => filled($record->cotacao_dispensada_justificativa)),
                    TextEntry::make('motivo_recusa')->label('Último motivo de recusa')->columnSpanFull()->visible(fn (RequisicaoCompra $record): bool => filled($record->motivo_recusa)),
                    TextEntry::make('motivo_cancelamento')->label('Motivo do cancelamento')->columnSpanFull()->visible(fn (RequisicaoCompra $record): bool => filled($record->motivo_cancelamento)),
                ]),
            Section::make('Itens')
                ->schema([
                    RepeatableEntry::make('itens')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('descricao')->label('Descrição')->columnSpan(2),
                            TextEntry::make('quantidade_formatada')->label('Quantidade')
                                ->state(fn (ItemRequisicaoCompra $record): string => rtrim(rtrim(number_format((float) $record->quantidade, 3, ',', '.'), '0'), ',').' '.$record->unidade_medida),
                            TextEntry::make('destino')->label('Destino')->badge(),
                            TextEntry::make('recebido')->label('Recebido')
                                ->state(fn (ItemRequisicaoCompra $record): string => rtrim(rtrim(number_format($record->quantidadeRecebida(), 3, ',', '.'), '0'), ',') ?: '0'),
                        ]),
                ]),
            Section::make('Cotações')
                ->description('O mapa compara os fornecedores; a mais barata vem marcada. O fornecedor com recebimento divergente nos últimos meses vem sinalizado.')
                ->visible(fn (RequisicaoCompra $record): bool => $record->cotacoes()->exists())
                ->schema([
                    ViewEntry::make('mapa')->hiddenLabel()->view('filament.infolists.compras-mapa-cotacoes')->columnSpanFull(),
                ]),
            Section::make('Pedido')
                ->columns(3)
                ->visible(fn (RequisicaoCompra $record): bool => $record->pedido !== null)
                ->schema([
                    TextEntry::make('pedido.numero')->label('Pedido')->weight('bold'),
                    TextEntry::make('pedido.fornecedor.razao_social')->label('Fornecedor'),
                    TextEntry::make('pedido.valor_total')->label('Valor')->money('BRL'),
                    TextEntry::make('pedido.nivel_alcada')->label('Alçada exigida')->formatStateUsing(fn ($state): string => "Nível {$state}"),
                    TextEntry::make('pedido.aprovadoPor.name')->label('Aprovado por')->placeholder('Aguardando aprovação'),
                    TextEntry::make('pedido.aprovado_em')->label('Aprovado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('saldo')->label('Saldo do orçamento na aprovação')->columnSpanFull()
                        ->visible(fn (RequisicaoCompra $record): bool => filled($record->pedido?->saldo_orcamentario))
                        ->state(function (RequisicaoCompra $record): string {
                            $s = (array) $record->pedido->saldo_orcamentario;

                            return sprintf('%s: previsto R$ %s, realizado R$ %s, comprometido R$ %s, saldo R$ %s.', $s['plano_orcado'] ?? 'Conta', number_format((float) ($s['previsto'] ?? 0), 2, ',', '.'), number_format((float) ($s['realizado'] ?? 0), 2, ',', '.'), number_format((float) ($s['comprometido'] ?? 0), 2, ',', '.'), number_format((float) ($s['saldo'] ?? 0), 2, ',', '.'));
                        }),
                    TextEntry::make('pedido.justificativa_acima_do_orcamento')->label('Justificativa (acima do orçamento)')->columnSpanFull()->visible(fn (RequisicaoCompra $record): bool => filled($record->pedido?->justificativa_acima_do_orcamento)),
                ]),
            Section::make('Recebimentos')
                ->visible(fn (RequisicaoCompra $record): bool => (bool) $record->pedido?->recebimentos->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('pedido.recebimentos')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('data_recebimento')->label('Data')->date('d/m/Y'),
                            TextEntry::make('nota_fiscal_numero')->label('Nota fiscal')
                                ->url(fn (RecebimentoCompra $record): ?string => $record->nota_fiscal_path ? route('compras.nota-fiscal', $record) : null)
                                ->openUrlInNewTab(),
                            TextEntry::make('valor_recebido')->label('Valor')->money('BRL'),
                            TextEntry::make('divergencia')->label('Conferência')->badge()
                                ->formatStateUsing(fn (bool $state): string => $state ? 'Com divergência' : 'Conforme')
                                ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                            TextEntry::make('recebidoPor.name')->label('Recebido por')->placeholder('—'),
                            TextEntry::make('observacao')->label('Observação')->columnSpanFull()->placeholder('—'),
                        ]),
                ]),
        ]);
    }
}
