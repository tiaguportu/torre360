<?php

namespace App\Filament\Resources\TransacaoBancarias\Schemas;

use App\Models\Fatura;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TransacaoBancariaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('banco_id')
                    ->label('Banco')
                    ->relationship('banco', 'nome')
                    ->required(),
                Select::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada (+)',
                        'saida' => 'Saída (-)',
                    ])
                    ->required()
                    ->native(false),
                TextInput::make('valor')
                    ->label('Valor')
                    ->prefix('R$')
                    ->required()
                    ->numeric(),
                DatePicker::make('data_transacao')
                    ->label('Data da Transação')
                    ->required()
                    ->default(now()),

                TextInput::make('descricao')
                    ->label('Descrição')
                    ->columnSpanFull(),

                Select::make('fatura_id')
                    ->label('Vincular a Fatura')
                    ->relationship('fatura', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Fatura $record) => "Fatura #{$record->id} - Venc: {$record->vencimento} - R$ {$record->valor}")
                    ->searchable(),

                Select::make('plano_conta_id')
                    ->label('Plano de Contas')
                    ->relationship('planoConta', 'nome')
                    ->searchable(),

                Select::make('centro_custo_id')
                    ->label('Centro de Custo')
                    ->relationship('centroCusto', 'nome')
                    ->searchable(),

                Select::make('fornecedor_id')
                    ->label('Fornecedor')
                    ->relationship('fornecedor', 'razao_social')
                    ->searchable(),

                Toggle::make('conciliado')
                    ->label('Conciliado?')
                    ->required(),

                TextInput::make('external_id')
                    ->label('ID Externo (Banco)')
                    ->disabled()
                    ->dehydrated(true),
            ]);
    }
}
