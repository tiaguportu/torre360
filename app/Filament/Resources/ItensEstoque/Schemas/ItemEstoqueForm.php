<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque\Schemas;

use App\Models\ItemEstoque;
use App\Models\PlanoConta;
use App\Models\Unidade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemEstoqueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Item')
                ->columns(2)
                ->schema([
                    TextInput::make('nome')->label('Nome')->required()->maxLength(150),
                    TextInput::make('codigo')->label('Código (opcional)')->maxLength(30)->unique(ignoreRecord: true),
                    TextInput::make('categoria')->label('Categoria')->placeholder('Limpeza, papelaria, descartáveis...')->maxLength(60),
                    Select::make('unidade_medida')->label('Unidade de medida')->options(ItemEstoque::UNIDADES_DE_MEDIDA)->default('un')->native(false)->required(),
                    Select::make('unidade_id')->label('Guardado na unidade')->options(fn (): array => Unidade::query()->orderBy('nome')->pluck('nome', 'id')->all())->placeholder('Não informar')->native(false),
                    Select::make('plano_conta_id')
                        ->label('Conta do plano (compras deste item)')
                        ->options(fn (): array => PlanoConta::query()->where('tipo', 'despesa')->where('is_active', true)->orderBy('codigo')->get()->mapWithKeys(fn (PlanoConta $p): array => [$p->id => "{$p->codigo} - {$p->nome}"])->all())
                        ->searchable()
                        ->native(false),
                ]),
            Section::make('Controle')
                ->columns(2)
                ->schema([
                    TextInput::make('estoque_minimo')
                        ->label('Estoque mínimo')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Quando o saldo chega a este valor ou abaixo, o item entra na fila da Mesa. Zero desliga o aviso.'),
                    Toggle::make('is_active')->label('Ativo')->default(true)->helperText('Item inativo não recebe nem entrega saldo.'),
                    TextInput::make('quantidade_atual')
                        ->label('Saldo atual')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('O saldo muda só por entrada, saída ou ajuste de inventário.')
                        ->visibleOn('edit'),
                    TextInput::make('custo_medio')
                        ->label('Custo médio (R$)')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                ]),
        ]);
    }
}
