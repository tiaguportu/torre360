<?php

namespace App\Filament\Widgets;

use App\Models\Contrato;
use App\Services\AssinafyService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ContratosPendentesWidget extends Widget
{
    protected static ?int $sort = -4;

    protected string $view = 'filament.widgets.contratos-pendentes';

    protected int|string|array $columnSpan = 'full';

    public function getContratosPendentes(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        $emails = collect([$user->email]);
        $pessoa = $user->pessoa;
        if ($pessoa && ! empty($pessoa->email)) {
            $emails->push($pessoa->email);
        }

        $emailsClean = $emails->filter()->map(fn ($e) => strtolower(trim($e)))->unique();

        return Contrato::query()
            ->with(['matricula.pessoa', 'matricula.turma.serie'])
            ->whereNotIn('assinafy_status', ['signed', 'completed'])
            ->latest()
            ->get()
            ->filter(function (Contrato $contrato) use ($emailsClean) {
                $statusSignatarios = $contrato->getStatusSignatarios();

                return $statusSignatarios->contains(function ($sig) use ($emailsClean) {
                    $sigEmail = strtolower(trim($sig['email'] ?? ''));

                    return $emailsClean->contains($sigEmail) && ($sig['status'] ?? 'pending') === 'pending';
                });
            });
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
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return (new static)->getContratosPendentes()->isNotEmpty();
    }
}
