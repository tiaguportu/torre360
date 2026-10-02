<?php

namespace App\Filament\Resources\TipoBolsas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TipoBolsas\TipoBolsaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTipoBolsas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = TipoBolsaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Tipos de Bolsa', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🏷️', 'Tipos de Bolsa', 'Modelos de bolsa/desconto que podem ser concedidos a um aluno.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, percentual máximo e se exige aprovação.'],
                $user->can('Create:TipoBolsa') ? ['🆕', 'Novo tipo', 'Cadastre um novo tipo de bolsa (ex.: Filantrópica, Convênio, Desconto Irmãos).'] : null,
                $user->can('Update:TipoBolsa') ? ['✏️', 'Editar', 'Ajuste o percentual máximo e o critério de renovação.'] : null,
            ])
            ->dica('Conceder uma bolsa para um aluno é feito na tela "Bolsas Concedidas", não aqui — aqui você só cadastra os modelos disponíveis.');
    }
}
