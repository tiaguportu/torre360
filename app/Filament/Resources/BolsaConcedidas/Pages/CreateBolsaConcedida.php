<?php

namespace App\Filament\Resources\BolsaConcedidas\Pages;

use App\Enums\StatusBolsa;
use App\Filament\Resources\BolsaConcedidas\BolsaConcedidaResource;
use App\Models\TipoBolsa;
use Filament\Resources\Pages\CreateRecord;

class CreateBolsaConcedida extends CreateRecord
{
    protected static string $resource = BolsaConcedidaResource::class;

    /**
     * Quando o tipo de bolsa não exige aprovação, a concessão já nasce aprovada.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tipoBolsa = TipoBolsa::find($data['tipo_bolsa_id']);

        if ($tipoBolsa && ! $tipoBolsa->exige_aprovacao) {
            $data['status'] = StatusBolsa::Aprovada->value;
            $data['aprovado_por_user_id'] = auth()->id();
        }

        return $data;
    }
}
