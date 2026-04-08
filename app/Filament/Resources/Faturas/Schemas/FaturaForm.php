<?php

namespace App\Filament\Resources\Faturas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FaturaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('contrato_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('vencimento')
                    ->required(),
                TextInput::make('valor')
                    ->label('Valor Total')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->helperText('O valor total é a soma dos itens.'),
                TextInput::make('valor_pago')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('pendente'),
                Textarea::make('pix_copia_e_cola')
                    ->columnSpanFull(),

                Repeater::make('itens')
                    ->relationship()
                    ->schema([
                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('valor_unitario')
                            ->label('Vlr. Unitário')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $get, $set) => self::updateTotal($get, $set)),
                        TextInput::make('quantidade')
                            ->label('Qtd.')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $get, $set) => self::updateTotal($get, $set)),
                        TextInput::make('desconto')
                            ->label('Desconto')
                            ->numeric()
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $get, $set) => self::updateTotal($get, $set)),
                        Select::make('tipo_desconto')
                            ->label('Tipo Desc.')
                            ->options([
                                'absoluto' => 'R$',
                                'relativo' => '%',
                            ])
                            ->default('absoluto')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $get, $set) => self::updateTotal($get, $set)),
                        TextInput::make('valor_total_item')
                            ->label('Total Item')
                            ->numeric()
                            ->readOnly()
                            ->state(function ($get) {
                                $vlr = (float) $get('valor_unitario');
                                $qtd = (float) $get('quantidade');
                                $desc = (float) $get('desconto');
                                $tipo = $get('tipo_desconto');

                                $total = $vlr * $qtd;

                                if ($tipo === 'absoluto') {
                                    $total -= $desc;
                                } else {
                                    $total -= ($total * ($desc / 100));
                                }

                                return number_format($total, 2, '.', '');
                            }),
                    ])
                    ->columns(6)
                    ->columnSpanFull()
                    ->createItemButtonLabel('Adicionar Item')
                    ->afterStateUpdated(fn ($get, $set) => self::updateTotal($get, $set)),
            ]);
    }

    public static function updateTotal($get, $set): void
    {
        $itens = $get('itens') ?? [];
        $totalFatura = 0;

        foreach ($itens as $item) {
            $vlr = (float) ($item['valor_unitario'] ?? 0);
            $qtd = (float) ($item['quantidade'] ?? 0);
            $desc = (float) ($item['desconto'] ?? 0);
            $tipo = $item['tipo_desconto'] ?? 'absoluto';

            $totalItem = $vlr * $qtd;

            if ($tipo === 'absoluto') {
                $totalItem -= $desc;
            } else {
                $totalItem -= ($totalItem * ($desc / 100));
            }

            $totalFatura += $totalItem;
        }

        $set('valor', number_format($totalFatura, 2, '.', ''));
    }
}
