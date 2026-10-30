<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Exceptions\ConflitoDeHorarioException;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditPreceptoria extends EditRecord
{
    protected static string $resource = PreceptoriaResource::class;

    /**
     * Professor e aluno não podem ter outra preceptoria no mesmo horário: avisa o motivo e mantém o formulário aberto.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ConflitoDeHorarioException $e) {
            Notification::make()->title('Horário em conflito')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Preceptoria')
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
        $html = '<p>Nesta página você pode editar os dados de uma preceptoria cadastrada.</p>';
        $html .= '<h3>Orientações:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Data e Horários:</strong> Altere a data, hora de início e hora de término conforme necessário.</li>';
        $html .= '<li><strong>Professor e Aluno:</strong> É possível redefinir o professor responsável ou a matrícula do aluno vinculado. O professor e o aluno não podem ter outra preceptoria num horário que se sobreponha; se isso acontecer, o sistema avisa qual é o choque e não salva.</li>';
        $html .= '<li><strong>Exclusão:</strong> Utilize o botão "Excluir" no topo da página caso deseje remover este atendimento.</li>';
        $html .= '</ul>';

        return $html;
    }
}
