<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Pages;

use App\Enums\StatusConsentimento;
use App\Filament\Resources\ConsentimentoMatriculas\ConsentimentoMatriculaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConsentimentoMatricula extends EditRecord
{
    protected static string $resource = ConsentimentoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $statusMudou = ($data['status'] ?? null) !== $this->record->status?->value;

        if ($statusMudou && ($data['status'] ?? null) !== StatusConsentimento::Pendente->value) {
            $data['respondido_em'] = now();
            $data['respondido_por_user_id'] = auth()->id();
        }

        return $data;
    }
}
