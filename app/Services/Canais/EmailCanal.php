<?php

namespace App\Services\Canais;

use App\Contracts\CanalMensagem;
use App\Mail\MensagemGenericaMail;
use App\Models\EmailLog;
use App\Models\Pessoa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailCanal implements CanalMensagem
{
    public function chave(): string
    {
        return 'email';
    }

    public function rotulo(): string
    {
        return 'E-mail';
    }

    public function disponivelPara(Pessoa $pessoa): bool
    {
        return filled($pessoa->email);
    }

    public function enviar(Pessoa $pessoa, string $assunto, string $corpo): bool
    {
        if (! $this->disponivelPara($pessoa)) {
            return false;
        }

        try {
            $mailable = new MensagemGenericaMail($assunto, $corpo);
            Mail::to($pessoa->email)->send($mailable);

            EmailLog::create([
                'to' => [$pessoa->email],
                'subject' => $assunto,
                'body' => $corpo,
                'sent_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("EmailCanal: falha ao enviar para {$pessoa->email}: ".$e->getMessage());

            return false;
        }
    }
}
