<?php

namespace App\Console\Commands;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Notifications\AcompanhamentoInteressadoNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotificarInteressadosPendentesCommand extends Command
{
    protected $signature = 'crm:notificar-pendentes';

    protected $description = 'Notifica consultores sobre leads com contato atrasado';

    public function handle(): int
    {
        $interessados = Interessado::with(['pessoa', 'usuario', 'status'])
            ->precisaContato()
            ->ativos()
            ->whereNotNull('usuario_id')
            ->get();

        if ($interessados->isEmpty()) {
            $this->info('Nenhum lead pendente de contato encontrado.');

            return self::SUCCESS;
        }

        $notificados = 0;

        foreach ($interessados as $interessado) {
            $consultor = $interessado->usuario;

            if (! $consultor) {
                continue;
            }

            // Notificação por e-mail
            $consultor->notify(new AcompanhamentoInteressadoNotification($interessado));

            // Notificação do sininho (Database)
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

            // Registra no activity log
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

            $notificados++;
        }

        $this->info("Notificações enviadas para {$notificados} lead(s) pendentes.");

        return self::SUCCESS;
    }
}
