<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicacaoInteressados\Pages;

use App\Filament\Resources\IndicacaoInteressados\IndicacaoInteressadoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListIndicacaoInteressados extends ListRecords
{
    protected static string $resource = IndicacaoInteressadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Programa Família Indica Família (MGM)')
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

        $html = '<p>O <strong>Programa Família Indica Família</strong> (Member Get Member) gerencia o boca a boca institucional, recompensando pais de alunos atuais e famílias embaixadoras que atraem novas matrículas.</p>';
        $html .= '<h3>Como funciona o ciclo:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>1. Cadastro da Indicação:</strong> Registre quem indicou (família da escola) e o novo lead interessado. O sistema pode vincular um código exclusivo para cada família.</li>';
        $html .= '<li><strong>2. Em Negociação (Pendente):</strong> O lead avança pelas etapas normais do funil (atendimento, tour presencial).</li>';
        $html .= '<li><strong>3. Conversão em Matrícula:</strong> Quando o lead é matriculado, a indicação passa automaticamente para "Matriculado (Elegível a Recompensa)".</li>';
        $html .= '<li><strong>4. Concessão de Recompensa:</strong> Pela ação "Conceder Recompensa", a secretaria/financeiro valida que o desconto ou brinde foi aplicado para quem indicou.</li>';
        $html .= '</ul>';

        if ($user->can('Create:IndicacaoInteressado')) {
            $html .= '<p><strong>Novo Registro:</strong> Use o botão "Novo" para cadastrar uma indicação manual informada pela família.</p>';
        }

        return $html;
    }
}
