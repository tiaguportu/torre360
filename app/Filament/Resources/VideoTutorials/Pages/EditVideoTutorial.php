<?php

namespace App\Filament\Resources\VideoTutorials\Pages;

use App\Filament\Resources\VideoTutorials\VideoTutorialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVideoTutorial extends EditRecord
{
    protected static string $resource = VideoTutorialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
