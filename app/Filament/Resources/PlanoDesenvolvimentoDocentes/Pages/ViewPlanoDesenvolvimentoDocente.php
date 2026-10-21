<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\Concerns\TemAcoesDoPlano;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\PlanoDesenvolvimentoDocenteResource;
use App\Support\HelpContent;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlanoDesenvolvimentoDocente extends ViewRecord
{
    use HasAjudaAction;
    use TemAcoesDoPlano;

    protected static string $resource = PlanoDesenvolvimentoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->acoesDoPlano(),
            EditAction::make()->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord())),
            $this->ajudaAction('Plano de desenvolvimento', HelpContent::make('🚩', 'Plano de desenvolvimento', 'Objetivo, ações e prazos combinados a partir da avaliação.')
                ->alerta('Conteúdo confidencial: só o professor do plano, a coordenação e a administração o veem.')),
        ];
    }
}
