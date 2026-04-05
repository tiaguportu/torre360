<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Mime\Address;

class LogSentMessage
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        EmailLog::create([
            'user_id' => Auth::id(),
            'to' => $this->formatAddresses($message->getTo()),
            'cc' => $this->formatAddresses($message->getCc()),
            'bcc' => $this->formatAddresses($message->getBcc()),
            'subject' => $message->getSubject() ?? '(Sem Assunto)',
            'body' => $message->getHtmlBody() ?: $message->getTextBody() ?: '',
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  Address[]  $addresses
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $address->getAddress(), $addresses);
    }
}
