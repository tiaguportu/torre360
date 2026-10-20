<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Pages;

use App\Exceptions\AvaliacaoDocenteException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AvaliacaoDocentes\AvaliacaoDocenteResource;
use App\Models\AvaliacaoDocente;
use App\Services\AvaliacaoDocenteService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAvaliacaoDocente extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = AvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalizar')
                ->label('Finalizar avaliação')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Finalizar avaliação')
                ->modalDescription('A pontuação será calculada e a avaliação ficará visível para o professor avaliado. Depois de finalizada ela não pode mais ser alterada (só reaberta por quem tem essa permissão).')
                ->visible(fn (): bool => (bool) auth()->user()?->can('finalizar', $this->getRecord()))
                ->action(function (): void {
                    // Grava o que está na tela antes de finalizar, para não perder notas ainda não salvas.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    /** @var AvaliacaoDocente $avaliacao */
                    $avaliacao = $this->getRecord()->refresh();

                    try {
                        app(AvaliacaoDocenteService::class)->finalizar($avaliacao, auth()->user());
                    } catch (AvaliacaoDocenteException $e) {
                        Notification::make()->title('Não foi possível finalizar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Avaliação finalizada')->success()->send();

                    $this->redirect(AvaliacaoDocenteResource::getUrl('view', ['record' => $avaliacao]));
                }),
            DeleteAction::make(),
            $this->ajudaAction('Avaliar professor', HelpContent::make('✏️', 'Avaliar professor', 'Dê a nota de cada critério e registre o parecer.')
                ->passos('Como avaliar', [
                    'Dê uma nota a cada critério (1 = muito abaixo do esperado; o maior valor = excelente).',
                    'Registre observações por critério e o parecer qualitativo.',
                    'Salve para continuar depois, ou clique em "Finalizar avaliação" para calcular a pontuação.',
                ])
                ->alerta('Todos os critérios precisam de nota para finalizar. Depois de finalizada, a avaliação não pode mais ser alterada.')),
        ];
    }
}
