<?php

use App\Support\FilaLonga;
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

// Worker da fila dos jobs longos (PDF em lote, envio em massa, leitura de gabarito; ver App\Support\FilaLonga). Eles
// levam minutos: na fila padrão seguravam o worker e atrasavam e-mails e notificações, e passavam do `retry_after`
// (90 s), voltando para a fila enquanto ainda rodavam. A conexão `database_longa` tem `retry_after` de 720 s, acima do
// `--timeout` (600 s, o maior dos jobs). O mutex dura mais que o maior job, para uma segunda execução não entrar no meio.
$filaLonga = (string) config('queue.connections.'.FilaLonga::CONEXAO.'.queue', 'longas');

if ($filaLonga !== '') {
    Schedule::command(sprintf('queue:work %s --queue=%s --stop-when-empty --tries=3 --timeout=%d --max-time=55', FilaLonga::CONEXAO, $filaLonga, FilaLonga::TIMEOUT_MAXIMO))
        ->everyMinute()
        ->withoutOverlapping(15)
        ->runInBackground();
}
Schedule::command('assinafy:reconciliar')->hourly()->withoutOverlapping();
Schedule::command('documentos:reconciliar-assinaturas')->hourly()->withoutOverlapping();
Schedule::command('crm:notificar-pendentes')->dailyAt('08:00')->withoutOverlapping();
// De hora em hora: cada regra sai na primeira execução a partir do seu `horario_envio` (padrão 08:00) e a
// idempotência do log impede repetir. O envio é síncrono, então `withoutOverlapping` evita duas execuções juntas.
Schedule::command('crm:executar-regua-follow-up')->hourly()->withoutOverlapping(60);
Schedule::command('crm:expurgar-rascunhos-pre-matricula')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('crm:recalcular-lead-score')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('cobranca:executar-regua')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('financeiro:gerar-contas-recorrentes')->dailyAt('06:45')->withoutOverlapping();
// Antes do expediente e depois da rotina de risco de evasão (06:30): a fotografia do painel pega os números do dia.
Schedule::command('painel:fotografar')->dailyAt('06:55')->withoutOverlapping();
Schedule::command('financeiro:atualizar-contas-pagar-atrasadas')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('biblioteca:atualizar-emprestimos-atrasados')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('biblioteca:limpar-capas-pendentes')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('academico:recalcular-risco-evasao')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('omr:purgar-arquivos-antigos')->dailyAt('03:45')->withoutOverlapping();
Schedule::command('omr:limpar-temporarios --dias=7')->dailyAt('04:15')->withoutOverlapping();
Schedule::command('email:limpar-logs --dias=365')->dailyAt('04:45')->withoutOverlapping();
Schedule::command('manutencao:gerar-ordens')->dailyAt('06:15')->withoutOverlapping();
Schedule::command('ponto:apurar-dia')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('ponto:expirar-banco-horas')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('ponto:verificar-integridade')->dailyAt('03:20')->withoutOverlapping();
Schedule::command('rh:avisar-ferias')->weeklyOn(1, '07:30')->withoutOverlapping();
Schedule::command('activitylog:clean --days=180 --force')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('audit:limpar-logs --dias=180')->dailyAt('04:10')->withoutOverlapping();

// Relatórios configuráveis (Onda 72): a rodada é a cada 5 minutos, então a hora marcada vale com essa tolerância.
Schedule::command('relatorios:executar-agendados')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('relatorios:limpar-arquivos')->dailyAt('04:30')->withoutOverlapping();
// Limpeza diária de relatórios temporários volumosos com mais de 1 semana (7 dias) em storage/app/relatorios_gerados/
Schedule::command('relatorios:limpar-temporarios --dias=7')->dailyAt('04:35')->withoutOverlapping();

// Reuniões e atas (Ondas 27 e 32): de manhã, antes do expediente, o responsável e a coordenação sabem o que venceu.
Schedule::command('reunioes:lembrar-encaminhamentos')->dailyAt('07:15')->withoutOverlapping();

// Faltas: um único aviso por responsável no fim do dia, no lugar de um e-mail por aula. Todo dia, porque o que está pendente é
// o que foi lançado e ainda não avisado (inclui aula antiga lançada hoje); sem pendência, nada sai. O horário é `FALTAS_RESUMO_HORARIO`.
Schedule::command('frequencia:enviar-resumo-diario')->dailyAt((string) config('frequencia.resumo_horario', '18:00'))->withoutOverlapping();

// Atendimento da família (Onda 83): chamado que espera a família há dias demais fica resolvido sozinho, com aviso.
Schedule::command('atendimento:resolver-aguardando')->dailyAt('07:20')->withoutOverlapping();

// Proposta comercial (Onda 87): aviso 3 e 1 dia antes de vencer e expiração das que passaram da validade, antes do expediente.
Schedule::command('crm:expirar-propostas')->dailyAt('07:25')->withoutOverlapping();
