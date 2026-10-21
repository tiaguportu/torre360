<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Pages\Concerns;

use App\Exceptions\ReajusteMensalidadeException;
use App\Filament\Resources\CenarioReajustes\CenarioReajusteResource;
use App\Models\CenarioReajuste;
use App\Services\ReajusteMensalidadeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Ações de fluxo do cenário de reajuste, compartilhadas pelas telas de edição e de visualização.
 */
trait TemAcoesDoCenario
{
    /**
     * @return list<Action>
     */
    protected function acoesDoCenario(): array
    {
        $servico = fn (): ReajusteMensalidadeService => app(ReajusteMensalidadeService::class);

        return [
            $this->acaoDeFluxo('aprovar', 'Aprovar cenário', 'heroicon-o-check', 'success', 'Depois de aprovado o cenário não muda. Para alterar, crie outra simulação.', fn (CenarioReajuste $c) => $servico()->aprovar($c, auth()->user()), 'Cenário aprovado'),
            $this->acaoDeFluxo('aplicar', 'Aplicar na campanha', 'heroicon-o-bolt', 'success', 'O preço de cada série passa a valer nas próximas rematrículas efetivadas desta campanha. Contratos que já existem não mudam.', fn (CenarioReajuste $c) => $servico()->aplicar($c, auth()->user()), 'Preços aplicados na campanha'),
            $this->acaoDeFluxo('desfazer', 'Desfazer aplicação', 'heroicon-o-arrow-uturn-left', 'warning', 'A campanha volta ao valor único. Só é possível enquanto nenhuma rematrícula foi efetivada.', fn (CenarioReajuste $c) => $servico()->desfazer($c, auth()->user()), 'Aplicação desfeita'),
            $this->acaoDeFluxo('comunicar', 'Registrar comunicado às famílias', 'heroicon-o-megaphone', 'gray', 'Registra a data em que as famílias foram avisadas do novo valor (o envio é feito pelos canais da escola).', fn (CenarioReajuste $c) => $servico()->registrarComunicado($c, auth()->user()), 'Comunicado registrado'),
            Action::make('baixarLista')
                ->label('Baixar lista de comunicação (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('comunicar', $this->getRecord()))
                ->action(function () use ($servico) {
                    /** @var CenarioReajuste $cenario */
                    $cenario = $this->getRecord();
                    $csv = $servico()->csvDeComunicacao($cenario);

                    // A lista traz contato de cada família: a exportação fica registrada.
                    activity('reajuste_mensalidade')->performedOn($cenario)->causedBy(auth()->user())->log('Lista de comunicação do reajuste exportada.');

                    return response()->streamDownload(fn () => print ($csv), 'reajuste-'.Str::slug($cenario->nome).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }

    private function acaoDeFluxo(string $nome, string $rotulo, string $icone, string $cor, string $descricao, callable $executar, string $sucesso): Action
    {
        return Action::make($nome)
            ->label($rotulo)
            ->icon($icone)
            ->color($cor)
            ->requiresConfirmation()
            ->modalDescription($descricao)
            ->visible(fn (): bool => (bool) auth()->user()?->can($nome, $this->getRecord()))
            ->action(function () use ($executar, $sucesso): void {
                try {
                    $cenario = $executar($this->getRecord()->refresh());
                } catch (ReajusteMensalidadeException $e) {
                    Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title($sucesso)->success()->send();

                $this->redirect(CenarioReajusteResource::getUrl(auth()->user()->can('update', $cenario) ? 'edit' : 'view', ['record' => $cenario]));
            });
    }
}
