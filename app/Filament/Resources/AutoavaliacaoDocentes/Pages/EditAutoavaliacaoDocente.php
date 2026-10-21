<?php

declare(strict_types=1);

namespace App\Filament\Resources\AutoavaliacaoDocentes\Pages;

use App\Exceptions\DesenvolvimentoDocenteException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AutoavaliacaoDocentes\AutoavaliacaoDocenteResource;
use App\Models\AutoavaliacaoDocente;
use App\Services\AutoavaliacaoDocenteService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAutoavaliacaoDocente extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = AutoavaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviar')
                ->label('Enviar autoavaliação')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Enviar autoavaliação')
                ->modalDescription('A pontuação será calculada e a coordenação passará a ver a sua autoavaliação. Depois de enviada ela não pode mais ser alterada.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('enviar', $this->getRecord()))
                ->action(function (): void {
                    // Grava o que está na tela antes de enviar, para não perder notas ainda não salvas.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    /** @var AutoavaliacaoDocente $autoavaliacao */
                    $autoavaliacao = $this->getRecord()->refresh();

                    try {
                        app(AutoavaliacaoDocenteService::class)->enviar($autoavaliacao, auth()->user());
                    } catch (DesenvolvimentoDocenteException $e) {
                        Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Autoavaliação enviada')->success()->send();

                    $this->redirect(AutoavaliacaoDocenteResource::getUrl('view', ['record' => $autoavaliacao]));
                }),
            $this->ajudaAction('Preencher autoavaliação', HelpContent::make('✏️', 'Preencher autoavaliação', 'Dê uma nota a cada critério.')
                ->alerta('Todos os critérios precisam de nota para enviar. Salve para continuar depois; só você vê o rascunho.')),
        ];
    }
}
