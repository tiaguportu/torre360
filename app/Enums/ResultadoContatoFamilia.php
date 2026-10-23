<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResultadoContatoFamilia: string implements HasColor, HasLabel
{
    case Atendeu = 'atendeu';
    case Compromisso = 'compromisso';
    case NaoAtendeu = 'nao_atendeu';
    case SemRetorno = 'sem_retorno';

    public function getLabel(): string
    {
        return match ($this) {
            self::Atendeu => 'A família atendeu',
            self::Compromisso => 'A família se comprometeu a resolver',
            self::NaoAtendeu => 'Não atendeu',
            self::SemRetorno => 'Sem retorno',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Atendeu, self::Compromisso => 'success',
            self::NaoAtendeu, self::SemRetorno => 'danger',
        };
    }
}
