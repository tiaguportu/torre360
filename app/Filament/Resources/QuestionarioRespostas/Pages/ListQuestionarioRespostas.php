<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuestionarioRespostas extends ListRecords
{
    protected static string $resource = QuestionarioRespostaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
