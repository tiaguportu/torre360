<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars\Pages;

use App\Enums\StatusListaMaterial;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ListaMaterialEscolars\ListaMaterialEscolarResource;
use App\Models\ListaMaterialEscolar;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateListaMaterialEscolar extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ListaMaterialEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova lista de material', HelpContent::make('🎒', 'Nova lista de material', 'Escolha a série e o período. Os itens vêm na tela seguinte.')
                ->dica('Só pode haver uma lista por série e período. Se a do ano passado serve, crie a nova e use "Copiar itens de outra lista".')),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $existente = ListaMaterialEscolar::query()
            ->where('serie_id', $data['serie_id'])
            ->where('periodo_letivo_id', $data['periodo_letivo_id'])
            ->first();

        if ($existente) {
            Notification::make()
                ->title('Já existe uma lista para esta série e período')
                ->body('Abra a lista existente em vez de criar outra.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        return ListaMaterialEscolar::query()->create($data + [
            'status' => StatusListaMaterial::Rascunho,
            'criada_por_user_id' => auth()->id(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
