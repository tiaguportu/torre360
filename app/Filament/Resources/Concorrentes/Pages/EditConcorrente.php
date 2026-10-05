<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concorrentes\Pages;

use App\Filament\Resources\Concorrentes\ConcorrenteResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditConcorrente extends EditRecord
{
    protected static string $resource = ConcorrenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Concorrente & Battlecard')
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

        $html = '<p>Nesta página você pode atualizar as informações e o Battlecard de uma escola concorrente.</p>';
        $html .= '<h3>O que você pode fazer:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Atualizar Diferenciais:</strong> Refine os argumentos da equipe comercial conforme novas informações de mercado forem surgindo.</li>';
        $html .= '<li><strong>Ajustar Mensalidade:</strong> Mantenha a estimativa de valor da anuidade/mensalidade do concorrente atualizada.</li>';
        $html .= '<li><strong>Desativar do Radar:</strong> Desmarque a opção "Concorrente Ativo" caso a escola encerre atividades ou não concorra mais com a nossa unidade.</li>';

        if ($user && $user->can('Delete:Concorrente')) {
            $html .= '<li><strong>Excluir:</strong> Remova o registro da escola (leads vinculados terão a referência desassociada com segurança).</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
