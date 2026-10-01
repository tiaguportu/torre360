<?php

namespace App\Filament\Resources\ContaPagars\Pages;

use App\Filament\Resources\ContaPagars\ContaPagarResource;
use App\Models\ContaPagar;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContaPagars extends ListRecords
{
    protected static string $resource = ContaPagarResource::class;

    public function mount(): void
    {
        parent::mount();

        ContaPagar::atualizarAtrasadas();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
