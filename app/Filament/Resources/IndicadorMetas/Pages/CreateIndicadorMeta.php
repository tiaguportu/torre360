<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicadorMetas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\IndicadorMetas\IndicadorMetaResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateIndicadorMeta extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = IndicadorMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova meta', HelpContent::make('🚩', 'Nova meta', 'Uma meta por indicador, ano e unidade.')
                ->secao('🎯 Como preencher', [
                    ['📊', 'Formato', 'Alunos em número, financeiro em reais, taxas em percentual sem o símbolo.'],
                    ['🏫', 'Unidade', 'Em branco vale para a escola toda.'],
                ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['unidade_id'] = (int) ($data['unidade_id'] ?? 0);

        return $data;
    }
}
