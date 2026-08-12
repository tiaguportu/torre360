<?php

namespace App\Filament\Resources\Faturas\Schemas;

use App\Enums\StatusFatura;
use App\Models\Contrato;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FaturaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('contrato_id')
                    ->label('Contrato')
                    ->relationship('contrato', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Contrato $record) => "Contrato #{$record->id} — ".($record->matricula?->pessoa?->nome ?? 'Sem aluno'))
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('vencimento')
                    ->required()
                    ->label('Data de Vencimento'),

                Select::make('status')
                    ->label('Status')
                    ->options(StatusFatura::class)
                    ->required()
                    ->native(false)
                    ->default(StatusFatura::Pendente),

                Placeholder::make('total_consolidado')
                    ->label('Valor Total Consolidado')
                    ->content(function (Get $get) {
                        $itens = $get('itens') ?? [];
                        $total = 0;
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
                            $total += $totalItem;
                        }

                        return 'R$ '.number_format($total, 2, ',', '.');
                    }),

                Textarea::make('pix_copia_e_cola')
                    ->label('PIX Copia e Cola')
                    ->helperText('Código PIX para pagamento desta fatura')
                    ->columnSpanFull(),

                Repeater::make('itens')
                    ->relationship()
                    ->label('Itens da Fatura')
                    ->schema([
                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('valor_unitario')
                            ->label('Vlr. Unitário')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('quantidade')
                            ->label('Qtd.')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('desconto')
                            ->label('Desconto')
                            ->numeric()
                            ->default(0)
                            ->live(onBlur: true),
                        Select::make('tipo_desconto')
                            ->label('Tipo Desc.')
                            ->options([
                                'absoluto' => 'R$',
                                'relativo' => '%',
                            ])
                            ->default('absoluto')
                            ->required()
                            ->live(),
                        Placeholder::make('valor_total_item')
                            ->label('Total Item')
                            ->content(function (Get $get) {
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

                                return 'R$ '.number_format($total, 2, ',', '.');
                            }),
                    ])
                    ->columns(6)
                    ->columnSpanFull()
                    ->createItemButtonLabel('Adicionar Item')
                    ->live(),
            ]);
    }
}
