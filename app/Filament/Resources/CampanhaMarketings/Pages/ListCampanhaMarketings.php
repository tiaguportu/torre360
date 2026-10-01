<?php

namespace App\Filament\Resources\CampanhaMarketings\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CampanhaMarketings\CampanhaMarketingResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampanhaMarketings extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CampanhaMarketingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Campanhas de Marketing', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📣', 'Campanhas de Marketing', 'Acompanhe o investimento e o retorno de cada ação de captação.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja canal, período, investimento, leads gerados, matrículas e taxa de conversão de cada campanha.'],
                $user->can('Create:CampanhaMarketing') ? ['🆕', 'Nova Campanha', 'Cadastre nome, canal, datas, custo e o código UTM.'] : null,
                $user->can('Update:CampanhaMarketing') ? ['✏️', 'Editar', 'Ajuste datas, custo, observações ou encerre a campanha desmarcando "Ativa".'] : null,
                ['🔎', 'Filtros', 'Filtre por canal e por campanhas ativas ou encerradas.'],
            ])
            ->secao('🔗 Como os leads chegam à campanha?', [
                ['🏷️', 'Código UTM', 'Use o mesmo código nos links divulgados; os interessados que chegarem por ele são atribuídos à campanha.'],
                ['📈', 'Conversão', 'É a proporção entre leads e matrículas realizadas.'],
            ])
            ->dica('Preencha o custo para acompanhar o retorno do investimento por canal.');
    }
}
