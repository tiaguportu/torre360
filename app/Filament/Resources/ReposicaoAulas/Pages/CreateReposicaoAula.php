<?php

namespace App\Filament\Resources\ReposicaoAulas\Pages;

use App\Filament\Resources\ReposicaoAulas\ReposicaoAulaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateReposicaoAula extends CreateRecord
{
    protected static string $resource = ReposicaoAulaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Agendar Reposição')
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
        return '<p>Preencha os campos para programar uma reposição de aula. A reposição integrará o cômputo da carga horária e dos dias letivos previstos da turma.</p>'
            .'<ul>'
            .'<li><strong>Turma e Disciplina:</strong> Selecione a turma que necessita da compensação.</li>'
            .'<li><strong>Data e Horários:</strong> Informe a data da reposição (ex: sábado letivo ou contraturno) e os horários de início e término.</li>'
            .'<li><strong>Motivo:</strong> Justifique a necessidade para fins de registro e prestação de contas aos órgãos educacionais.</li>'
            .'</ul>';
    }
}
