<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Schemas;

use App\Enums\DestinoItemCompra;
use App\Models\CentroCusto;
use App\Models\ItemEstoque;
use App\Models\PlanoConta;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RequisicaoCompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pedido de compra')
                ->columns(2)
                ->schema([
                    TextInput::make('titulo')->label('O que precisa comprar')->placeholder('Ex.: Papel para as provas do 4º bimestre')->required()->maxLength(160)->columnSpanFull(),
                    Select::make('centro_custo_id')
                        ->label('Setor / centro de custo')
                        ->options(fn (): array => CentroCusto::query()->where('is_active', true)->orderBy('nome')->pluck('nome', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->helperText('Define de onde sai o dinheiro e onde a despesa aparece no orçamento.'),
                    Select::make('plano_conta_id')
                        ->label('Conta do plano (despesa)')
                        ->options(fn (): array => PlanoConta::query()->where('tipo', 'despesa')->where('is_active', true)->orderBy('codigo')->get()->mapWithKeys(fn (PlanoConta $p): array => [$p->id => "{$p->codigo} - {$p->nome}"])->all())
                        ->searchable()
                        ->native(false)
                        ->helperText('É com ela que a aprovação confere o saldo do orçamento.'),
                    Select::make('urgencia')->label('Urgência')->options(['normal' => 'Normal', 'urgente' => 'Urgente'])->default('normal')->native(false)->required(),
                    Textarea::make('justificativa')->label('Por que é necessário')->required()->rows(3)->columnSpanFull(),
                ]),
            Section::make('Itens')
                ->description('O destino diz o que o recebimento faz: entra no estoque, vira bem no patrimônio ou só gera a conta a pagar.')
                ->schema([
                    Repeater::make('itens')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Adicionar item')
                        ->itemLabel(fn (array $state): ?string => filled($state['descricao'] ?? null) ? (string) $state['descricao'] : null)
                        ->columns(4)
                        ->schema([
                            TextInput::make('descricao')->label('Descrição')->required()->maxLength(200)->columnSpan(2),
                            TextInput::make('quantidade')->label('Quantidade')->numeric()->minValue(0.001)->required(),
                            Select::make('unidade_medida')->label('Unidade')->options(ItemEstoque::UNIDADES_DE_MEDIDA)->default('un')->native(false)->required(),
                            TextInput::make('estimativa_unitaria')->label('Estimativa por unidade (R$)')->numeric()->minValue(0),
                            Select::make('destino')->label('Destino')->options(DestinoItemCompra::class)->default(DestinoItemCompra::Consumo->value)->native(false)->live()->required(),
                            Select::make('item_estoque_id')
                                ->label('Item de estoque')
                                ->options(fn (): array => ItemEstoque::query()->where('is_active', true)->orderBy('nome')->pluck('nome', 'id')->all())
                                ->searchable()
                                ->native(false)
                                ->visible(fn (Get $get): bool => self::destinoDoItem($get('destino')) === DestinoItemCompra::Estoque)
                                ->required(fn (Get $get): bool => self::destinoDoItem($get('destino')) === DestinoItemCompra::Estoque),
                        ]),
                ]),
        ]);
    }

    /**
     * O estado de um Select com enum chega como o enum ou como o texto, conforme o caminho.
     */
    public static function destinoDoItem(mixed $valor): ?DestinoItemCompra
    {
        return $valor instanceof DestinoItemCompra ? $valor : (is_string($valor) ? DestinoItemCompra::tryFrom($valor) : null);
    }
}
