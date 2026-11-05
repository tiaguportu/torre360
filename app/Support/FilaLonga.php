<?php

namespace App\Support;

/**
 * Fila dos jobs longos: os que podem levar mais que o `retry_after` da conexão padrão (90 s).
 *
 * Na fila padrão um job de minutos segurava o worker e atrasava e-mails e notificações, e, passados os 90 s, a fila o
 * entregava a outro worker com o primeiro ainda rodando (PDF gerado e e-mail enviado em dobro). Aqui o job vai para a
 * conexão `database_longa`, que usa a mesma tabela `jobs`, mas tem `retry_after` acima do maior `timeout`, e é atendido
 * por um worker próprio (routes/console.php).
 *
 * Só vale para o driver `database`. Com outro driver (sync nos testes, redis em outra hospedagem) o job segue a conexão
 * e a fila padrão, como qualquer outro.
 *
 * Regra para job novo: `timeout` de 90 s ou mais exige chamar {@see self::direcionar()} no construtor
 * (um teste de guarda confere isso).
 */
final class FilaLonga
{
    public const CONEXAO = 'database_longa';

    /** Maior `timeout` de um job desta fila, em segundos. O worker agendado usa o mesmo valor. */
    public const TIMEOUT_MAXIMO = 600;

    public static function ativa(): bool
    {
        return config('queue.connections.'.config('queue.default').'.driver') === 'database';
    }

    public static function conexao(): ?string
    {
        return self::ativa() ? self::CONEXAO : null;
    }

    public static function fila(): ?string
    {
        return self::ativa() ? (string) config('queue.connections.'.self::CONEXAO.'.queue', 'longas') : null;
    }

    /**
     * Manda o job para a conexão e a fila dos jobs longos. Chamar no construtor do job.
     *
     * @param  object  $job  um job com o trait Queueable
     */
    public static function direcionar(object $job): void
    {
        $job->onConnection(self::conexao())->onQueue(self::fila());
    }
}
