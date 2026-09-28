<?php

namespace App\Services;

use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\VisitaInteressado;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Regras de agendamento de visitas de leads à escola e envio de lembretes ao consultor.
 */
class VisitaInteressadoService
{
    /**
     * Horas de antecedência em que o consultor é avisado de uma visita agendada.
     */
    public const JANELA_LEMBRETE_HORAS = 24;

    public static function agendar(
        Interessado $interessado,
        CarbonInterface|string $dataHora,
        ?int $usuarioId = null,
        ?int $dependenteId = null,
        ?string $observacoes = null,
    ): VisitaInteressado {
        $visita = $interessado->visitas()->create([
            'data_hora' => $dataHora,
            'usuario_id' => $usuarioId ?? $interessado->usuario_id,
            'interessado_dependente_id' => $dependenteId,
            'observacoes' => $observacoes,
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        self::sincronizarProximoContato($interessado);
        LeadScoreService::recalcular($interessado);

        return $visita;
    }

    /**
     * Antecipa o "próximo contato" do lead para a próxima visita agendada.
     * Nunca adia um retorno já marcado para uma data anterior à visita.
     */
    public static function sincronizarProximoContato(Interessado $interessado): void
    {
        $proximaVisita = $interessado->visitas()
            ->agendadas()
            ->where('data_hora', '>=', now())
            ->min('data_hora');

        if (! $proximaVisita) {
            return;
        }

        $atual = $interessado->fresh()->data_proximo_contato;

        if ($atual === null || $atual->isPast() || $atual->gt($proximaVisita)) {
            $interessado->update(['data_proximo_contato' => $proximaVisita]);
        }
    }

    /**
     * Avisa o consultor (sino do painel) das visitas nas próximas 24h ainda sem lembrete.
     *
     * @return int Quantidade de lembretes enviados.
     */
    public static function enviarLembretes(): int
    {
        $enviados = 0;

        $visitas = VisitaInteressado::query()
            ->pendentesDeLembrete(self::JANELA_LEMBRETE_HORAS)
            ->with(['interessado.pessoa', 'usuario', 'interessado.usuario'])
            ->get();

        foreach ($visitas as $visita) {
            $consultor = $visita->usuario ?? $visita->interessado?->usuario;

            if (! $consultor) {
                continue;
            }

            Notification::make()
                ->title('Visita agendada em breve')
                ->body("{$visita->interessado->pessoa->nome} visita a escola em {$visita->data_hora->format('d/m/Y \à\s H:i')}.")
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->actions([
                    Action::make('view')
                        ->label('Ver Lead')
                        ->url(InteressadoResource::getUrl('edit', ['record' => $visita->interessado]))
                        ->button(),
                ])
                ->sendToDatabase($consultor);

            $visita->update(['lembrete_enviado_em' => now()]);
            $enviados++;
        }

        return $enviados;
    }
}
