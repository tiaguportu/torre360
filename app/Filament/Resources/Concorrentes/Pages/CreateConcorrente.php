<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concorrentes\Pages;

use App\Filament\Resources\Concorrentes\ConcorrenteResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateConcorrente extends CreateRecord
{
    protected static string $resource = ConcorrenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Concorrente')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Nesta página você cadastra uma nova escola concorrente no banco de inteligência comercial.</p>';
        $html .= '<h3>Campos essenciais:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Identificação:</strong> Nome oficial e sigla como é conhecida na cidade.</li>';
        $html .= '<li><strong>Posicionamento de Preço:</strong> Compare a faixa de mensalidade em relação aos nossos valores.</li>';
        $html .= '<li><strong>Pontos Fortes & Vulnerabilidades:</strong> Preencha as tags com o que as famílias comentam sobre eles.</li>';
        $html .= '<li><strong>Nossos Diferenciais Matadores:</strong> Insira os motivos pelos quais a família deve escolher a nossa escola.</li>';
        $html .= '</ul>';

        return $html;
    }
}
