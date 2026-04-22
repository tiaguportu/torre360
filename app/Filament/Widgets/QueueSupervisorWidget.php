<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueSupervisorWidget extends Widget implements HasActions, HasForms
{
    use HasWidgetShield;
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.queue-supervisor-widget';

    protected function getActions(): array
    {
        return [
            $this->processQueueAction(),
            $this->clearQueueAction(),
        ];
    }

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getQueueStatus(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $lastRunAt = Cache::get('queue_last_run_at');

        $isRunning = false;
        if ($lastRunAt) {
            $isRunning = Carbon::parse($lastRunAt)->diffInMinutes(now()) < 5;
        }

        return [
            'pending' => $pendingJobs,
            'failed' => $failedJobs,
            'last_run' => $lastRunAt ? Carbon::parse($lastRunAt)->diffForHumans() : 'Nunca',
            'is_running' => $isRunning,
        ];
    }

    public function processQueueAction(): Action
    {
        return Action::make('processQueue')
            ->label('Processar Fila Agora')
            ->icon('heroicon-m-play')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Processar Fila')
            ->modalDescription('O sistema tentará processar todos os jobs pendentes agora. Isso pode levar alguns segundos dependendo da quantidade.')
            ->action(function () {
                try {
                    set_time_limit(300); // Aumenta timeout para 5 min se permitido

                    // No Windows/XAMPP, Artisan::call('queue:work') bloqueia a requisição se não tiver timeout.
                    // Para deixar rodando em background, usamos powershell no Windows.
                    $command = 'powershell -Command "Start-Process php -ArgumentList \'artisan\', \'queue:work\' -WindowStyle Hidden"';
                    shell_exec($command);

                    // Atualiza o heartbeat
                    Cache::put('queue_last_run_at', now()->toDateTimeString(), now()->addHours(24));

                    Notification::make()
                        ->title('Fila processada com sucesso!')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Erro ao processar fila')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function clearQueueAction(): Action
    {
        return Action::make('clearQueue')
            ->label('Limpar Fila')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Limpar todos os Jobs?')
            ->modalDescription('Isso removerá todas as notificações e processos pendentes permanentemente.')
            ->action(function () {
                DB::table('jobs')->truncate();

                Notification::make()
                    ->title('Fila limpa com sucesso!')
                    ->warning()
                    ->send();
            });
    }
}
