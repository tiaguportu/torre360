<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\Concerns\TemAcoesDoPlano;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\PlanoDesenvolvimentoDocenteResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanoDesenvolvimentoDocente extends EditRecord
{
    use HasAjudaAction;
    use TemAcoesDoPlano;

    protected static string $resource = PlanoDesenvolvimentoDocenteResource::class;

    protected function gravarAntesDeMudarEtapa(): void
    {
        // Grava o objetivo e a data de revisão antes de ativar ou concluir, para não perder o que está na tela.
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->acoesDoPlano(),
            DeleteAction::make()->visible(fn (): bool => (bool) auth()->user()?->can('delete', $this->getRecord())),
            $this->ajudaAction('Editar plano', HelpContent::make('🚩', 'Editar plano', 'Ajuste o objetivo, a revisão e as ações.')
                ->alerta('Enquanto estiver em rascunho, o professor não vê o plano. Depois de ativado, ele registra o andamento e você acompanha.')),
        ];
    }
}
