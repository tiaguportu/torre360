<?php

namespace App\Observers;

use App\Models\Interessado;
use App\Services\LeadFunilService;

/**
 * Registra histórico de transição de etapa quando o lead é criado ou tem seu status alterado
 * diretamente fora do LeadFunilService (ex.: importações por IA, formulário público, edições manuais).
 */
class InteressadoObserver
{
    public function created(Interessado $lead): void
    {
        if ($lead->status_interessado_id && ! $lead->transicaoRegistradaPorServico) {
            app(LeadFunilService::class)->registrarTransicaoViaObserver(
                $lead,
                null,
                $lead->status_interessado_id,
                false,
                $lead->created_at
            );
        }
    }

    public function updated(Interessado $lead): void
    {
        if ($lead->wasChanged('status_interessado_id')) {
            if ($lead->transicaoRegistradaPorServico) {
                // Reseta a flag para futuras alterações
                $lead->transicaoRegistradaPorServico = false;

                return;
            }

            $statusAnteriorId = $lead->getOriginal('status_interessado_id');
            $statusNovoId = $lead->status_interessado_id;

            if ($statusNovoId) {
                app(LeadFunilService::class)->registrarTransicaoViaObserver(
                    $lead,
                    $statusAnteriorId,
                    $statusNovoId,
                    false,
                    $lead->updated_at
                );
            }
        }
    }
}
