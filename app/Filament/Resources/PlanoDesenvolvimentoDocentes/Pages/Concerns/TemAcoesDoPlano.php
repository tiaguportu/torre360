<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\Concerns;

use App\Exceptions\DesenvolvimentoDocenteException;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\PlanoDesenvolvimentoDocenteResource;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Services\DesenvolvimentoDocenteService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Ações de fluxo do plano (ativar, concluir, dar ciência), compartilhadas pelas telas de edição e de visualização.
 */
trait TemAcoesDoPlano
{
    /**
     * Gancho para a tela de edição gravar o que está no formulário antes de mudar a etapa do plano.
     */
    protected function gravarAntesDeMudarEtapa(): void {}

    /**
     * @return list<Action>
     */
    protected function acoesDoPlano(): array
    {
        return [
            $this->acaoDoPlano('ativar', 'Ativar plano', 'heroicon-o-play', 'success', 'Ativar plano', 'O professor passa a ver o plano e a registrar o andamento das ações. Revise as ações e os prazos antes.', fn (PlanoDesenvolvimentoDocente $p, $user) => app(DesenvolvimentoDocenteService::class)->ativar($p, $user), 'Plano ativado'),
            $this->acaoDoPlano('concluir', 'Concluir plano', 'heroicon-o-check-badge', 'success', 'Concluir plano', 'O plano fica concluído e deixa de aceitar alterações. Todas as ações precisam estar concluídas ou canceladas.', fn (PlanoDesenvolvimentoDocente $p, $user) => app(DesenvolvimentoDocenteService::class)->concluir($p, $user), 'Plano concluído'),
            $this->acaoDoPlano('darCiencia', 'Estou ciente do plano', 'heroicon-o-hand-thumb-up', 'info', 'Confirmar ciência', 'Você confirma que leu o plano. Isso não significa concordância; só registra a data em que tomou conhecimento.', fn (PlanoDesenvolvimentoDocente $p, $user) => app(DesenvolvimentoDocenteService::class)->darCiencia($p, $user), 'Ciência registrada'),
        ];
    }

    private function acaoDoPlano(string $nome, string $rotulo, string $icone, string $cor, string $titulo, string $descricao, callable $executar, string $sucesso): Action
    {
        return Action::make($nome)
            ->label($rotulo)
            ->icon($icone)
            ->color($cor)
            ->requiresConfirmation()
            ->modalHeading($titulo)
            ->modalDescription($descricao)
            ->visible(fn (): bool => (bool) auth()->user()?->can($nome, $this->getRecord()))
            ->action(function () use ($executar, $sucesso): void {
                $this->gravarAntesDeMudarEtapa();

                /** @var PlanoDesenvolvimentoDocente $plano */
                $plano = $this->getRecord()->refresh();

                try {
                    $plano = $executar($plano, auth()->user());
                } catch (DesenvolvimentoDocenteException $e) {
                    Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($sucesso)->success()->send();

                $this->redirect(PlanoDesenvolvimentoDocenteResource::getUrl(auth()->user()->can('update', $plano) ? 'edit' : 'view', ['record' => $plano]));
            });
    }
}
