<?php

namespace App\Filament\Widgets;

use App\Models\Contrato;
use App\Services\AssinafyService;
use App\Traits\HasCustomWidgetShield;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ContratosPendentesWidget extends Widget
{
    use HasCustomWidgetShield;

    protected static ?int $sort = -4;

    protected string $view = 'filament.widgets.contratos-pendentes';

    protected int|string|array $columnSpan = 'full';

    public function getContratosPendentes(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        return Contrato::pendentesAssinaturaDoUsuario($user);
    }

    public function assinarContrato(int $contratoId, AssinafyService $service)
    {
        $contrato = Contrato::find($contratoId);
        if (! $contrato) {
            Notification::make()->title('Contrato não encontrado.')->danger()->send();

            return;
        }

        $result = $service->enviarContrato($contrato);

        if ($result['success'] && isset($result['redirect_url'])) {
            return redirect()->away($result['redirect_url']);
        }

        Notification::make()
            ->title('Erro ao processar assinatura')
            ->body($result['message'] ?? 'Não foi possível obter o link da Assinafy.')
            ->danger()
            ->send();
    }

    public static function canView(): bool
    {
        if (! static::hasWidgetShieldPermission()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return (new static)->getContratosPendentes()->isNotEmpty();
    }
}
