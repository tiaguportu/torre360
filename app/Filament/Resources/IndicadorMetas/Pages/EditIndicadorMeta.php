<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicadorMetas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\IndicadorMetas\IndicadorMetaResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIndicadorMeta extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = IndicadorMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar meta', HelpContent::make('🚩', 'Editar meta', 'Mudar a meta muda a cor do cartão na hora, para o ano inteiro.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['unidade_id'] = ((int) ($data['unidade_id'] ?? 0)) ?: null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['unidade_id'] = (int) ($data['unidade_id'] ?? 0);

        return $data;
    }
}
