<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5);

// Worker dedicado à fila da IA (análise de documentos por Gemini, `crm.fila_ia`): cada chamada pode levar mais de
// um minuto e, na fila padrão, atrasava e-mails e notificações. Roda em segundo plano para que um worker
// não espere o outro. `--timeout` (85 s) acompanha o `$timeout` do job e fica abaixo do `retry_after` da fila
// (90 s): se um job pudesse rodar mais que isso, a fila o entregaria a outro worker enquanto ainda roda.
$filaIa = (string) config('crm.fila_ia', 'ia');
$filaPadrao = (string) config('queue.connections.'.config('queue.default').'.queue', 'default');

if ($filaIa !== '' && $filaIa !== $filaPadrao) {
    Schedule::command("queue:work --queue={$filaIa} --stop-when-empty --tries=3 --timeout=85 --max-time=55")
        ->everyMinute()
        ->withoutOverlapping(5)
        ->runInBackground();
}
Schedule::command('assinafy:reconciliar')->hourly()->withoutOverlapping();
Schedule::command('crm:notificar-pendentes')->dailyAt('08:00')->withoutOverlapping();
// De hora em hora: cada regra sai na primeira execução a partir do seu `horario_envio` (padrão 08:00) e a
// idempotência do log impede repetir. O envio é síncrono, então `withoutOverlapping` evita duas execuções juntas.
Schedule::command('crm:executar-regua-follow-up')->hourly()->withoutOverlapping(60);
Schedule::command('crm:expurgar-rascunhos-pre-matricula')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('crm:recalcular-lead-score')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('cobranca:executar-regua')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('financeiro:atualizar-contas-pagar-atrasadas')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('biblioteca:atualizar-emprestimos-atrasados')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('biblioteca:limpar-capas-pendentes')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('academico:recalcular-risco-evasao')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('crm:verificar-sla-estourado')->everyFifteenMinutes()->withoutOverlapping(10);
