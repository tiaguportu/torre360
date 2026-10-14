<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoMaterialAula: string implements HasIcon, HasLabel
{
    case Apostila = 'apostila';
    case VideoAula = 'video';
    case LinkExterno = 'link';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Apostila => 'Apostila / PDF',
            self::VideoAula => 'Vídeo-aula',
            self::LinkExterno => 'Link Externo',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Apostila => 'heroicon-m-document-text',
            self::VideoAula => 'heroicon-m-play-circle',
            self::LinkExterno => 'heroicon-m-link',
        };
    }
}
