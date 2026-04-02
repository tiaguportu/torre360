<?php

namespace App\Filament\Schemas\Components;

use Filament\Schemas\Components\Component;

class BoletimeStatsSection extends Component
{
    protected string $view = 'filament.schemas.components.boletime-stats-section';

    public static function make(): static
    {
        return app(static::class);
    }
}
