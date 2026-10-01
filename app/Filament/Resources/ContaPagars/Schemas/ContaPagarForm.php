<?php

namespace App\Filament\Resources\ContaPagars\Schemas;

use App\Enums\StatusContaPagar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContaPagarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conta a Pagar')
                    ->columns(2)
                    ->schema([
                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('valor')
                            ->label('Valor (R$)')
                            ->prefix('R$')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        DatePicker::make('vencimento')
                            ->label('Vencimento')
                            ->native(false)
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(StatusContaPagar::class)
                            ->default(StatusContaPagar::Pendente)
                            ->required(),
                        Select::make('fornecedor_id')
                            ->label('Fornecedor')
                            ->relationship('fornecedor', 'razao_social')
                            ->searchable()
                            ->preload(),
                        Select::make('plano_conta_id')
                            ->label('Plano de Contas')
                            ->relationship('planoConta', 'nome')
                            ->searchable()
                            ->preload(),
                        Select::make('centro_custo_id')
                            ->label('Centro de Custo')
                            ->relationship('centroCusto', 'nome')
                            ->searchable()
                            ->preload(),
                        Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
