<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Nível da assinatura que o PDF assinado traz (Onda 74), a partir do que o sistema consegue ler no arquivo. A Lei nº
 * 14.063/2020 separa a assinatura eletrônica simples, a avançada e a qualificada (certificado ICP-Brasil); o sistema só
 * reconhece o que o PDF mostra e **não valida a cadeia nem a validade do certificado** (o Verificador de Conformidade do ITI
 * faz isso).
 */
enum NivelAssinatura: string implements HasColor, HasIcon, HasLabel
{
    case Eletronica = 'eletronica';
    case Digital = 'digital';
    case QualificadaIcp = 'qualificada_icp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Eletronica => 'Assinatura eletrônica',
            self::Digital => 'Assinatura digital (certificado não ICP-Brasil)',
            self::QualificadaIcp => 'Assinatura qualificada (certificado ICP-Brasil)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Eletronica => 'gray',
            self::Digital => 'info',
            self::QualificadaIcp => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Eletronica => 'heroicon-o-pencil',
            self::Digital => 'heroicon-o-key',
            self::QualificadaIcp => 'heroicon-o-shield-check',
        };
    }
}
