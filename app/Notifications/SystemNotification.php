<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public string $actionLabel = 'Ver',
        public string $type = 'info'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title)
            ->greeting('Olá, '.$notifiable->name)
            ->line($this->body);

        if ($this->actionUrl) {
            $message->action($this->actionLabel, $this->actionUrl);
        }

        return $message;
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body);

        if ($this->type === 'success') {
            $notification->success();
        } elseif ($this->type === 'warning') {
            $notification->warning();
        } elseif ($this->type === 'danger') {
            $notification->danger();
        } else {
            $notification->info();
        }

        if ($this->actionUrl) {
            $notification->actions([
                FilamentAction::make('view')
                    ->label($this->actionLabel)
                    ->url($this->actionUrl)
                    ->button(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => [
                'url' => $this->actionUrl,
                'type' => $this->type,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'type' => $this->type,
        ];
    }
}
