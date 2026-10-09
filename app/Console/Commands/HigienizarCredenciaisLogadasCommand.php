<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Support\EmailLogSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpa o que o sistema guardava antes da correção: senhas em texto claro e links de acesso no corpo de `email_logs`
 * e payloads de notificações com senha em `failed_jobs`. Roda uma vez por ambiente, de propósito (a alteração é
 * irreversível), e é idempotente.
 */
class HigienizarCredenciaisLogadasCommand extends Command
{
    protected $signature = 'seguranca:higienizar-credenciais {--dry-run : Só conta o que seria alterado, sem gravar}';

    protected $description = 'Remove senhas e links de acesso já gravados em email_logs e failed_jobs';

    /** Assuntos dos e-mails que levavam senha ou link de acesso. */
    private const ASSUNTOS_SENSIVEIS = [
        'Bem-vindo ao Torre360',
        'Alteração de Dados no Torre360',
        'Nova Senha Gerada - Torre360',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $logsAlterados = 0;

        EmailLog::query()->orderBy('id')->chunkById(200, function ($logs) use ($dryRun, &$logsAlterados): void {
            foreach ($logs as $log) {
                $novo = $this->corpoHigienizado($log);

                if ($novo === $log->body) {
                    continue;
                }

                $logsAlterados++;

                if (! $dryRun) {
                    // Direto no banco: não é uma edição do usuário e não deve gerar atividade nem tocar updated_at.
                    DB::table('email_logs')->where('id', $log->id)->update(['body' => $novo]);
                }
            }
        });

        $jobsRemovidos = $this->removerFalhasComSenha($dryRun);

        $verbo = $dryRun ? 'seriam alterados' : 'alterados';
        $this->info("E-mails do log {$verbo}: {$logsAlterados}. Jobs com falha removidos: {$jobsRemovidos}.");

        return self::SUCCESS;
    }

    private function corpoHigienizado(EmailLog $log): string
    {
        $corpo = (string) $log->body;

        $sensivel = in_array($log->subject, self::ASSUNTOS_SENSIVEIS, true)
            || preg_match('~(reset-password|password-reset|email-verification|verify-email)~i', $corpo) === 1;

        if ($sensivel) {
            return EmailLogSanitizer::corpo($corpo, 'App\\Notifications\\WelcomeUserMail');
        }

        return EmailLogSanitizer::corpo($corpo);
    }

    private function removerFalhasComSenha(bool $dryRun): int
    {
        $consulta = DB::table('failed_jobs')->where(function ($q): void {
            foreach (['WelcomeUserMail', 'UserUpdatedMail', 'UserNewPasswordNotification'] as $classe) {
                $q->orWhere('payload', 'like', "%{$classe}%");
            }
        });

        $total = (clone $consulta)->count();

        if (! $dryRun && $total > 0) {
            $consulta->delete();
        }

        return $total;
    }
}
