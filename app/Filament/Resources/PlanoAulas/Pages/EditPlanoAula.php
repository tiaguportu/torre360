<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoAulas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoAulas\Concerns\HasCopilotoPlanoAulaAction;
use App\Filament\Resources\PlanoAulas\PlanoAulaResource;
use App\Models\PlanoAula;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPlanoAula extends EditRecord
{
    use HasAjudaAction;
    use HasCopilotoPlanoAulaAction;

    protected static string $resource = PlanoAulaResource::class;

    protected function getHeaderActions(): array
    {
        /** @var PlanoAula $plano */
        $plano = $this->record;

        return [
            $this->getCopilotoPlanoAulaAction(),
            Action::make('pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(route('planos-aula.pdf', $plano))
                ->openUrlInNewTab(),
            DeleteAction::make()
                ->visible(fn () => ! $plano->foiExecutado()),
            $this->ajudaAction('Edição de Plano de Aula', $this->getHelpContent()),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var PlanoAula $plano */
        $plano = $this->record;

        if ($plano->foiExecutado()) {
            Notification::make()
                ->danger()
                ->title('Não é possível editar')
                ->body('Este plano de aula já foi executado e virou um registro no diário de aulas.')
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('✏️', 'Edição do Plano de Aula', 'Ajuste de objetivos, metodologia e adaptações curriculares.')
            ->secao('🎯 Funcionalidades', [
                $user->can('GerarComCopiloto:PlanoAula') ? ['✨', 'Copiloto IA', 'Regenere ou aprimore objetivos e metodologia com base nas habilidades da BNCC.'] : null,
                ['📄', 'Exportar PDF', 'Gera o documento impresso oficial do plano de aula.'],
                $user->can('GerarPei:PlanoAula') ? ['♿', 'Adaptações PEI', 'Na aba inferior, visualize ou gere adaptações curriculares inclusivas para estudantes com necessidades especiais.'] : null,
            ])
            ->alerta('Planos já executados no diário de classe tornam-se imutáveis para garantir a integridade do diário.');
    }
}
