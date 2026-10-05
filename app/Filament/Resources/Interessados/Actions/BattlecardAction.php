<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\Actions;

use App\Models\Interessado;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

class BattlecardAction
{
    public static function make(?string $name = 'battlecards'): Action
    {
        return Action::make($name)
            ->label('Battlecards & Objeções')
            ->icon('heroicon-o-shield-check')
            ->color('indigo')
            ->modalHeading('🛡️ Battlecards Comerciais & Inteligência de Objeções')
            ->modalDescription('Consulte diferenciais contra colégios concorrentes, matriz de contorno de objeções e roteiros de valor.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn (?Interessado $record = null) => view('filament.crm.modal-battlecards', [
                'lead' => $record,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar');
    }
}
