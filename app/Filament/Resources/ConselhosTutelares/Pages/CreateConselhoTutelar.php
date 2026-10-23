<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConselhosTutelares\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ConselhosTutelares\ConselhoTutelarResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateConselhoTutelar extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ConselhoTutelarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Cadastrar Conselho Tutelar', HelpContent::make('🏛️', 'Cadastrar Conselho Tutelar', 'Preencha o nome e os dados de contato do conselho.')
                ->secao('🎯 Como preencher', [
                    ['🏫', 'Unidade atendida', 'Deixe em branco para valer para todas as unidades.'],
                    ['📍', 'Endereço, e-mail e telefone', 'Saem no ofício. Confira com o próprio Conselho.'],
                ])),
        ];
    }
}
