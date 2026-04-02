<?php

namespace App\Filament\Schemas\Components;

use Filament\Schemas\Components\Component;

class BoletimeGradesTable extends Component
{
    protected string $view = 'filament.schemas.components.boletime-grades-table';

    public static function make(): static
    {
        return app(static::class);
    }
}
