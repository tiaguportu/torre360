<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Models\ProcessoFuncionario;
use App\Services\ProcessoFuncionarioService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProcessoFuncionario extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = ProcessoFuncionarioResource::class;

    /**
     * Depois de concluir ou cancelar, recarrega o processo: os botões do cabeçalho dependem do estado dele e, sem isto,
     * continuariam na tela até a página ser aberta de novo.
     */
    private function atualizarTela(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['status']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('concluir')
                ->label('Concluir processo')
                ->icon('heroicon-o-flag')
                ->color('primary')
                ->visible(fn (): bool => (bool) auth()->user()?->can('concluir', $this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Concluir o processo')
                ->modalDescription(fn (): string => $this->getRecord()->ehDesligamento()
                    ? 'Isso grava a data de desligamento e retira os acessos de equipe da pessoa ao sistema. Quem também é responsável ou aluno continua com a conta.'
                    : 'Isso encerra a admissão. O cadastro do funcionário não muda.')
                ->action(function (): void {
                    /** @var ProcessoFuncionario $processo */
                    $processo = $this->getRecord();

                    try {
                        $resumo = app(ProcessoFuncionarioService::class)->concluir($processo, auth()->user());
                    } catch (\DomainException $e) {
                        Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $corpo = $processo->ehDesligamento()
                        ? "Acesso retirado de {$resumo['usuarios_sem_acesso']} usuário(s); {$resumo['usuarios_desativados']} conta(s) desativada(s)."
                        : 'Processo concluído.';

                    $notificacao = Notification::make()->title('Processo concluído')->body(trim($corpo.' '.implode(' ', $resumo['avisos'])));
                    $resumo['avisos'] === [] ? $notificacao->success() : $notificacao->warning()->persistent();
                    $notificacao->send();

                    $this->atualizarTela();
                }),
            Action::make('cancelar')
                ->label('Cancelar processo')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Cancelar o processo')
                ->modalDescription('Use quando o processo foi aberto por engano ou a pessoa desistiu. Nada muda no cadastro do funcionário.')
                ->action(function (): void {
                    try {
                        app(ProcessoFuncionarioService::class)->cancelar($this->getRecord(), auth()->user());
                    } catch (\DomainException $e) {
                        Notification::make()->title('Não foi possível cancelar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Processo cancelado')->success()->send();
                    $this->atualizarTela();
                }),
            $this->ajudaAction('Processo de Admissão ou Desligamento', HelpContent::make('🧑‍💼', 'Providências do processo', 'Marque o que já foi feito e anexe o comprovante.')
                ->secao('🎯 Como usar', [
                    ['✅', 'Marcar como feito', 'Registra quem fez e quando; o comprovante (PDF ou foto) é opcional.'],
                    ['🚫', 'Não se aplica', 'Resolve a providência com uma justificativa. Vale também para as obrigatórias.'],
                    ['↩️', 'Reabrir', 'Volta a providência para pendente.'],
                    ['➕', 'Acrescentar', 'Inclui uma providência que a lista padrão não tem.'],
                ])
                ->alerta('Os comprovantes ficam em área privada, visíveis só para quem pode ver Admissão e Desligamento.')),
        ];
    }
}
