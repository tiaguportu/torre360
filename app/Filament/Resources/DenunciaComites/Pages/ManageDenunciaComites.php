<?php

namespace App\Filament\Resources\DenunciaComites\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DenunciaComites\DenunciaComiteResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDenunciaComites extends ManageRecords
{
    use HasAjudaAction;

    protected static string $resource = DenunciaComiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo membro'),
            $this->ajudaAction('Comitê da Ouvidoria', $this->conteudoDaAjuda()),
        ];
    }

    private function conteudoDaAjuda(): HelpContent
    {
        return HelpContent::make('🛡️', 'Comitê da Ouvidoria', 'Quem pode ler e tratar as manifestações do canal de ouvidoria e de convivência escolar.')
            ->passos('🚀 Como montar o comitê', [
                'Clique em "Novo membro" e escolha a pessoa.',
                'Escolha a categoria que ela acompanha, ou deixe em branco para todas.',
                'A pessoa também precisa da permissão "Ver" da Ouvidoria no perfil dela (Perfis de acesso).',
            ])
            ->dica('Estar no comitê não dá acesso sozinho, e ter a permissão sem estar no comitê também não: as duas coisas são necessárias, exceto para quem tem a permissão "Ver todas".');
    }
}
