<?php

namespace App\Filament\Resources\Enderecos\Schemas;

use App\Models\Cidade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

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

                        $cep = preg_replace('/[^0-9]/', '', $state);

                        if (strlen($cep) !== 8) {
                            return;
                        }

                        $response = Http::get("https://viacep.com.br/ws/{$cep}/json/")->json();

                        if (! isset($response['erro'])) {
                            $set('logradouro', $response['logradouro'] ?? '');
                            $set('bairro', $response['bairro'] ?? '');

                            if (isset($response['ibge'])) {
                                $cidade = Cidade::where('codigo_ibge', $response['ibge'])->first();
                                if ($cidade) {
                                    $set('cidade_id', $cidade->id);
                                }
                            }
                        }
                    }),
                Select::make('cidade_id')
                    ->relationship('cidade', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome}-{$record->estado?->sigla}")
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('logradouro')
                    ->required(),
                TextInput::make('numero'),
                TextInput::make('complemento'),
                TextInput::make('bairro'),
            ]);
    }
}
