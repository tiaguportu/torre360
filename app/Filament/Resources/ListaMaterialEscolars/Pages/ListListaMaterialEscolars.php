<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ListaMaterialEscolars\ListaMaterialEscolarResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListaMaterialEscolars extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ListaMaterialEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova lista'),
            $this->ajudaAction('Lista de Material Escolar', HelpContent::make('🎒', 'Lista de Material Escolar', 'Uma lista por série e período, publicada para as famílias no Portal.')
                ->passos('Como fazer', [
                    'Clique em "Nova lista" e escolha a série e o período letivo.',
                    'Adicione os itens na aba "Itens". Para reaproveitar a lista de outro ano, use "Copiar itens de outra lista".',
                    'O sistema barra itens de uso coletivo (papel higiênico, material de limpeza, giz, pincel de quadro, toner...) e pede confirmação para os que podem ser de uso individual ou coletivo.',
                    'Publique. As famílias dos alunos da série veem a lista no Portal e podem baixar o PDF. Para corrigir depois, volte a lista para rascunho.',
                ])
                ->alerta('A Lei nº 9.870/1999 (art. 1º, § 7º) torna nula a cláusula que obriga a família a pagar ou fornecer material de uso coletivo da escola: o custo entra na mensalidade. Exigir marca, loja ou kit fechado é venda casada (CDC, art. 39). O sistema ajuda, mas a responsabilidade é da escola: confira com o jurídico.')
                ->dica('Descreva o item pelo que o aluno precisa (por exemplo, "caderno brochura 96 folhas"), sem citar marca nem loja.')),
        ];
    }
}
