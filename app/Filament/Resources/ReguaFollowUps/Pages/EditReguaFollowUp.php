<?php

namespace App\Filament\Resources\ReguaFollowUps\Pages;

use App\Filament\Resources\ReguaFollowUps\ReguaFollowUpResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EditReguaFollowUp extends EditRecord
{
    protected static string $resource = ReguaFollowUpResource::class;

    public function boot(): void
    {
        if (! Schema::hasTable('regua_follow_ups')) {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $e) {
                report($e);
            }
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
                ->modalHeading('Ajuda: Editar Automação de Follow-up')
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
        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Modifique os parâmetros do gatilho ou ajuste a redação da mensagem enviada para as famílias.</p>';
        $html .= '<p class="text-xs text-gray-500">Alterações entrarão em vigor na próxima execução agendada (a régua roda de hora em hora e cada regra sai a partir do seu horário de disparo) ou nas execuções manuais do sistema. Use {{DATA_VISITA}} em vez de "ontem": se a rotina atrasar, a mensagem ainda pode sair até 2 dias depois do evento.</p>';
        $html .= '</div>';

        return $html;
    }
}
