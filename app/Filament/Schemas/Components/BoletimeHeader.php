<?php

namespace App\Filament\Schemas\Components;

use Filament\Schemas\Components\Component;

class BoletimeHeader extends Component
{
    protected string $view = 'filament.schemas.components.boletime-header';

    public static function make(): static
    {
        return app(static::class);
    }
}
