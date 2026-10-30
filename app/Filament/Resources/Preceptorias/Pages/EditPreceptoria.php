<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Exceptions\ConflitoDeHorarioException;
use App\Exceptions\ExclusaoDePreceptoriaRecusadaException;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Filament\Resources\Preceptorias\Tables\PreceptoriasTable;
use App\Models\Preceptoria;
use App\Services\PreceptoriaAgendaService;
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

    /** Quantos destinatários foram avisados pela mudança que acabou de ser salva. */
    protected int $avisados = 0;

    /**
     * Professor e aluno não podem ter outra preceptoria no mesmo horário: avisa o motivo e mantém o formulário aberto.
     * O que mudou (professor, aluno, horário, ciclo) vai para o histórico e quem é afetado é avisado.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $this->avisados = app(PreceptoriaAgendaService::class)->atualizarComHistorico($record, $data)['notificados'];
        } catch (ConflitoDeHorarioException $e) {
            Notification::make()->title('Horário em conflito')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        $notificacao = parent::getSavedNotification();

        if ($notificacao && $this->avisados > 0) {
            $notificacao->body("{$this->avisados} destinatário(s) avisado(s) da mudança.");
        }

        return $notificacao;
    }

    /**
     * A preceptoria fica guardada (dá para restaurar) e o cancelamento entra no histórico; sendo futura e
     * com aluno, quem é afetado é avisado. Com relatório, não é excluída.
     */
    private function excluirAction(): DeleteAction
    {
        $servico = app(PreceptoriaAgendaService::class);
        $avisados = 0;

        return DeleteAction::make()
            ->modalDescription(fn (Preceptoria $record) => PreceptoriasTable::descricaoDaExclusao($servico->previaDaExclusao([$record])))
            ->using(function (Preceptoria $record) use ($servico, &$avisados) {
                try {
                    $avisados = $servico->excluirComHistorico($record)['notificados'];

                    return true;
                } catch (ExclusaoDePreceptoriaRecusadaException $e) {
                    Notification::make()->title('Preceptoria não excluída')->body($e->getMessage())->danger()->send();

                    return false;
                }
            })
            ->successNotification(function (Notification $notification) use (&$avisados) {
                return $notification->body($avisados > 0 ? "{$avisados} aviso(s) de cancelamento enviado(s)." : null);
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excluirAction(),
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
        $html .= '<li><strong>Histórico e avisos:</strong> Trocar o professor, o aluno, o horário ou o ciclo fica registrado em "Histórico de Alterações". Sendo uma preceptoria futura, o professor, o aluno e os responsáveis afetados recebem um aviso (quem salvou não recebe).</li>';
        $html .= '<li><strong>Exclusão:</strong> Utilize o botão "Excluir" no topo da página caso deseje remover este atendimento. Ele sai da agenda, mas fica guardado: dá para restaurá-lo na listagem, pelo filtro de registros excluídos. Sendo futuro e com aluno, o professor, o aluno e os responsáveis recebem o aviso de cancelamento. Preceptoria com relatório não pode ser excluída; para devolver o horário à agenda, tire o aluno dele.</li>';
        $html .= '</ul>';

        return $html;
    }
}
