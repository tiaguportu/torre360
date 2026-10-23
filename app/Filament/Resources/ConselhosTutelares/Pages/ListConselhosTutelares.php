<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConselhosTutelares\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ConselhosTutelares\ConselhoTutelarResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConselhosTutelares extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ConselhoTutelarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Conselhos Tutelares', HelpContent::make('🏛️', 'Conselhos Tutelares', 'Para onde vai o ofício de faltas excessivas.')
                ->secao('🎯 Para que serve', [
                    ['📄', 'Ofício', 'Ao abrir um caso de faltas, o sistema sugere o conselho da unidade do aluno (ou o conselho sem unidade). Os dados dele saem no ofício.'],
                    ['🏫', 'Unidade', 'Se a escola tem unidades em municípios diferentes, cadastre um conselho para cada. Sem unidade, o conselho vale para todas.'],
                ])
                ->dica('Confira o endereço e o e-mail com o próprio Conselho: o ofício sai com o que está cadastrado aqui.')),
        ];
    }
}
