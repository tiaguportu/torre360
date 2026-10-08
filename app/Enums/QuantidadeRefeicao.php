<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum QuantidadeRefeicao: string implements HasColor, HasIcon, HasLabel
{
    case ComeuTudo = 'comeu_tudo';
    case ComeuParcialmente = 'comeu_parcialmente';
    case NaoQuisComer = 'nao_quis_comer';

    public function getLabel(): string
    {
        return match ($this) {
            self::ComeuTudo => 'Comeu Tudo',
            self::ComeuParcialmente => 'Comeu Parcialmente',
            self::NaoQuisComer => 'Não Quis Comer',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ComeuTudo => 'success',
            self::ComeuParcialmente => 'warning',
            self::NaoQuisComer => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ComeuTudo => 'heroicon-o-check-circle',
            self::ComeuParcialmente => 'heroicon-o-minus-circle',
            self::NaoQuisComer => 'heroicon-o-x-circle',
        };
    }
}
