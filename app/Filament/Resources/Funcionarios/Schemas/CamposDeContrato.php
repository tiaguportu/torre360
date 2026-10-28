<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funcionarios\Schemas;

use Filament\Forms\Components\TextInput;

class CamposDeContrato
{
    public static function salario(string $nome = 'salario', string $rotulo = 'Salário (R$)'): TextInput
    {
        return TextInput::make($nome)
            ->label($rotulo)
            ->prefix('R$')
            ->numeric()
            ->minValue(0.01);
    }
}
