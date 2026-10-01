<?php

namespace App\Filament\Resources\VideoTutorials\Pages;

use App\Filament\Resources\VideoTutorials\VideoTutorialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVideoTutorial extends CreateRecord
{
    protected static string $resource = VideoTutorialResource::class;
}
