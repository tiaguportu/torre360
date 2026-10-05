<?php

declare(strict_types=1);

namespace App\Filament\Resources\Objecoes\Pages;

use App\Filament\Resources\Objecoes\ObjecaoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditObjecao extends EditRecord
{
    protected static string $resource = ObjecaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Objeção')
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
        $user = auth()->user();

        $html = '<p>Edite o roteiro e as perguntas da objeção para manter a argumentação sempre afiada.</p>';
        $html .= '<ul>';
        $html .= '<li><strong>Atualizar Roteiro:</strong> Ajuste o texto caso novos serviços ou diferenciais tenham sido lançados pela escola.</li>';
        $html .= '<li><strong>Ordem:</strong> Altere o número de ordem para priorizar as objeções mais comuns no topo da lista dos consultores.</li>';

        if ($user && $user->can('Delete:Objecao')) {
            $html .= '<li><strong>Excluir:</strong> Remova permanentemente esta objeção da matriz.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
