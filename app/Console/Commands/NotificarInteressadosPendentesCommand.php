<?php

namespace App\Console\Commands;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\User;
use App\Notifications\AcompanhamentoInteressadoNotification;
use App\Notifications\LeadEstagnadoNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotificarInteressadosPendentesCommand extends Command
{
    protected $signature = 'crm:notificar-pendentes';

    protected $description = 'Notifica consultores sobre leads com contato atrasado ou estagnados sem interação';

    public function handle(): int
    {
        $atrasados = Interessado::with(['pessoa', 'usuario', 'status'])
            ->precisaContato()
            ->ativos()
            ->whereNotNull('usuario_id')
            ->get();

        $estagnados = Interessado::with(['pessoa', 'usuario', 'status'])
            ->estagnados()
            ->ativos()
            ->whereNotNull('usuario_id')
            ->whereNotIn('id', $atrasados->pluck('id'))
            ->get();

        if ($atrasados->isEmpty() && $estagnados->isEmpty()) {
            $this->info('Nenhum lead pendente de contato ou estagnado encontrado.');

            return self::SUCCESS;
        }

        $notificados = 0;

        foreach ($atrasados as $interessado) {
            if ($this->notificarAtraso($interessado)) {
                $notificados++;
            }
        }

        foreach ($estagnados as $interessado) {
            if ($this->notificarEstagnacao($interessado)) {
                $notificados++;
            }
        }

        $this->info("Notificações enviadas para {$notificados} lead(s).");

        return self::SUCCESS;
    }

    private function notificarAtraso(Interessado $interessado): bool
    {
        $consultor = $interessado->usuario;

        if (! $consultor instanceof User) {
            return false;
        }

        $consultor->notify(new AcompanhamentoInteressadoNotification($interessado));

        Notification::make()
            ->title('Follow-up Pendente')
            ->body("O lead {$interessado->pessoa->nome} precisa de contato. Agendado para {$interessado->data_proximo_contato->format('d/m/Y H:i')}.")
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver Lead')
                    ->url(InteressadoResource::getUrl('edit', ['record' => $interessado]))
                    ->button(),
            ])
            ->sendToDatabase($consultor);

        activity('crm')
            ->performedOn($interessado)
            ->causedByAnonymous()
            ->withProperties([
                'tipo' => 'notificacao_follow_up_automatica',
                'consultor_id' => $consultor->id,
                'consultor_nome' => $consultor->name,
                'interessado_nome' => $interessado->pessoa->nome,
            ])
            ->log("Notificação automática de follow-up enviada para {$consultor->name}");

        return true;
    }

    private function notificarEstagnacao(Interessado $interessado): bool
    {
        $consultor = $interessado->usuario;

        if (! $consultor instanceof User) {
            return false;
        }

        $dias = $interessado->diasSemInteracao();

        $consultor->notify(new LeadEstagnadoNotification($interessado, $dias));

        Notification::make()
            ->title('Lead Estagnado')
            ->body("O lead {$interessado->pessoa->nome} está há {$dias} dias sem qualquer interação registrada.")
            ->icon('heroicon-o-exclamation-circle')
            ->color('danger')
            ->actions([
                Action::make('view')
                    ->label('Ver Lead')
                    ->url(InteressadoResource::getUrl('edit', ['record' => $interessado]))
                    ->button(),
            ])
            ->sendToDatabase($consultor);

        activity('crm')
            ->performedOn($interessado)
            ->causedByAnonymous()
            ->withProperties([
                'tipo' => 'notificacao_estagnacao_automatica',
                'consultor_id' => $consultor->id,
                'consultor_nome' => $consultor->name,
                'interessado_nome' => $interessado->pessoa->nome,
                'dias_sem_interacao' => $dias,
            ])
            ->log("Notificação automática de estagnação enviada para {$consultor->name}");

        return true;
    }
}
