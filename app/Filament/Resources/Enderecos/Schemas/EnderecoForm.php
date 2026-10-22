<?php

namespace App\Filament\Resources\Enderecos\Schemas;

use App\Services\ViaCepService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EnderecoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo')
                    ->options([
                        'residencial' => 'Residencial',
                        'comercial' => 'Comercial',
                    ])
                    ->default('residencial')
                    ->required(),
                TextInput::make('cep')
                    ->mask('99999-999')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (! $state) {
                            return;
                        }

                        $dados = app(ViaCepService::class)->buscarComCidade($state);

                        if ($dados) {
                            $set('logradouro', $dados['logradouro'] ?? '');
                            $set('bairro', $dados['bairro'] ?? '');

                            if (! empty($dados['cidade_id'])) {
                                $set('cidade_id', $dados['cidade_id']);
                            }
                        }
                    }),
                TextInput::make('logradouro')
                    ->required(),
                TextInput::make('numero'),
                TextInput::make('complemento'),
                TextInput::make('bairro'),
                Select::make('cidade_id')
                    ->relationship('cidade', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome}-{$record->estado?->sigla}")
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
