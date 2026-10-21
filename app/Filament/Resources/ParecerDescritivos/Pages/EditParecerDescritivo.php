<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Pages;

use App\Exceptions\ParecerDescritivoException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ParecerDescritivos\ParecerDescritivoResource;
use App\Models\ParecerDescritivo;
use App\Services\CopilotoPedagogico\Exceptions\CopilotoIndisponivelException;
use App\Services\CopilotoPedagogico\Exceptions\LimiteDeUsoException;
use App\Services\CopilotoPedagogico\Exceptions\ParecerIaDesabilitadoException;
use App\Services\CopilotoPedagogico\Exceptions\RespostaIaInvalidaException;
use App\Services\ParecerDescritivoIaService;
use App\Services\ParecerDescritivoService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditParecerDescritivo extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ParecerDescritivoResource::class;

    /** O texto atual veio de um rascunho de IA (marca o parecer ao salvar). */
    public bool $geradoComIa = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->geradoComIa) {
            $data['gerado_com_ia'] = true;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarComIa')
                ->label('Rascunhar com IA')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Rascunhar o parecer com IA')
                ->modalDescription('O sistema envia ao provedor de IA os conceitos por habilidade e as evidências do portfólio desta etapa, sem o nome do aluno. O texto volta como rascunho no formulário: revise e ajuste antes de salvar. Substitui o texto atual da tela.')
                ->visible(fn (): bool => config('copiloto_pedagogico.parecer_ia_habilitado', false) && (bool) auth()->user()?->can('gerarComIa', $this->getRecord()))
                ->action(function (): void {
                    /** @var ParecerDescritivo $parecer */
                    $parecer = $this->getRecord();

                    try {
                        $texto = app(ParecerDescritivoIaService::class)->gerarRascunho($parecer, auth()->user());
                    } catch (ParecerIaDesabilitadoException|LimiteDeUsoException|RespostaIaInvalidaException|CopilotoIndisponivelException $e) {
                        Notification::make()->title('Não foi possível rascunhar com IA')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->form->fill([...$this->data, 'texto' => $texto]);
                    $this->geradoComIa = true;

                    Notification::make()->title('Rascunho gerado')->body('Revise com atenção e salve. Nada vai para a família sem a revisão da coordenação.')->success()->send();
                }),
            Action::make('enviarParaRevisao')
                ->label('Enviar para revisão')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (bool) auth()->user()?->can('enviarParaRevisao', $this->getRecord()))
                ->action(fn () => $this->salvarEAvancar(fn (ParecerDescritivo $p) => app(ParecerDescritivoService::class)->enviarParaRevisao($p, auth()->user()), 'Parecer enviado para revisão')),
            Action::make('publicar')
                ->label('Publicar para a família')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('A família do aluno passa a ver este parecer no Portal. Depois de publicado, só volta a ser editado se for reaberto.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('publicar', $this->getRecord()))
                ->action(fn () => $this->salvarEAvancar(fn (ParecerDescritivo $p) => app(ParecerDescritivoService::class)->publicar($p, auth()->user()), 'Parecer publicado')),
            DeleteAction::make(),
            $this->ajudaAction('Editar parecer', HelpContent::make('✏️', 'Editar parecer', 'Escreva o parecer do aluno nesta etapa.')
                ->passos('Como fazer', [
                    'Leia os "Registros desta etapa": conceitos por habilidade e evidências do portfólio.',
                    'Escreva o parecer (ou, se a IA estiver ligada, rascunhe e revise).',
                    'Salve; quando estiver pronto, envie para revisão. A coordenação publica.',
                ])
                ->alerta('Rascunho de IA nunca é publicado sozinho. Revise o texto inteiro e confira se não há nada que o aluno não tenha feito.')),
        ];
    }

    /**
     * Grava o que está na tela e só então muda a situação, para não perder a edição ainda não salva.
     */
    private function salvarEAvancar(callable $transicao, string $sucesso): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        /** @var ParecerDescritivo $parecer */
        $parecer = $this->getRecord()->refresh();

        try {
            $transicao($parecer);
        } catch (ParecerDescritivoException $e) {
            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($sucesso)->success()->send();

        $this->redirect(ParecerDescritivoResource::getUrl('index'));
    }
}
