<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Pages;

use App\Exceptions\AvaliacaoDocenteException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AvaliacaoDocentes\AvaliacaoDocenteResource;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\PlanoDesenvolvimentoDocenteResource;
use App\Models\AvaliacaoDocente;
use App\Services\AvaliacaoDocenteService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAvaliacaoDocente extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = AvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Continuar avaliação'),
            PlanoDesenvolvimentoDocenteResource::acaoPlanoDaAvaliacao(),
            Action::make('reabrir')
                ->label('Reabrir para correção')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('A avaliação volta a rascunho, a pontuação é zerada e o professor deixa de vê-la até ser finalizada de novo.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('reabrir', $this->getRecord()))
                ->action(function (): void {
                    /** @var AvaliacaoDocente $avaliacao */
                    $avaliacao = $this->getRecord();

                    try {
                        app(AvaliacaoDocenteService::class)->reabrir($avaliacao, auth()->user());
                    } catch (AvaliacaoDocenteException $e) {
                        Notification::make()->title('Não foi possível reabrir')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Avaliação reaberta para correção')->success()->send();

                    $this->redirect(AvaliacaoDocenteResource::getUrl('edit', ['record' => $avaliacao]));
                }),
            $this->ajudaAction('Avaliação de desempenho', HelpContent::make('👁️', 'Avaliação de desempenho', 'Notas por critério, observações e parecer da coordenação.')
                ->alerta('Esta avaliação é confidencial: o parecer e as observações ficam cifrados e só são vistos pelo professor avaliado, pela coordenação e pela administração.')),
        ];
    }
}
