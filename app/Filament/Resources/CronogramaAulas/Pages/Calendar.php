<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Resources\Pages\Page;

class Calendar extends Page
{
    protected static string $resource = CronogramaAulaResource::class;

    protected string $view = 'filament.resources.cronograma-aulas.pages.calendar-refactored';

    protected static ?string $title = 'Calendário de Aulas';
}
