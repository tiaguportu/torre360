<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LimparEmailLogsAntigosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_com_dry_run_nao_remove_registros(): void
    {
        Carbon::setTestNow('2026-10-23 12:00:00');

        // Registro com mais de 365 dias (400 dias atrás)
        EmailLog::forceCreate([
            'to' => ['antigo@teste.com'],
            'subject' => 'E-mail Antigo',
            'body' => 'Corpo do e-mail antigo',
            'sent_at' => now()->subDays(400),
            'created_at' => now()->subDays(400),
            'updated_at' => now()->subDays(400),
        ]);

        $this->artisan('email:limpar-logs --dias=365 --dry-run')
            ->expectsOutputToContain('[DRY-RUN] 1 registro(s) de log de e-mail seriam removidos')
            ->assertSuccessful();

        $this->assertDatabaseCount('email_logs', 1);

        Carbon::setTestNow();
    }

    public function test_comando_remove_apenas_logs_com_mais_de_um_ano(): void
    {
        Carbon::setTestNow('2026-10-23 12:00:00');

        // 1. Registro antigo (400 dias atrás) -> Deve ser removido
        $antigo = EmailLog::forceCreate([
            'to' => ['antigo@teste.com'],
            'subject' => 'E-mail Antigo 400 dias',
            'body' => 'Conteúdo antigo',
            'sent_at' => now()->subDays(400),
            'created_at' => now()->subDays(400),
            'updated_at' => now()->subDays(400),
        ]);

        // 2. Registro recente (30 dias atrás) -> Deve ser mantido
        $recente = EmailLog::forceCreate([
            'to' => ['recente@teste.com'],
            'subject' => 'E-mail Recente 30 dias',
            'body' => 'Conteúdo recente',
            'sent_at' => now()->subDays(30),
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        // 3. Outro registro antigo (370 dias atrás) -> Deve ser removido
        $outroAntigo = EmailLog::forceCreate([
            'to' => ['outro_antigo@teste.com'],
            'subject' => 'Outro e-mail antigo 370 dias',
            'body' => 'Conteúdo outro antigo',
            'sent_at' => now()->subDays(370),
            'created_at' => now()->subDays(370),
            'updated_at' => now()->subDays(370),
        ]);

        $this->artisan('email:limpar-logs --dias=365')
            ->expectsOutputToContain('2 registro(s) de log de e-mail antigos expurgados com sucesso')
            ->assertSuccessful();

        // O recente continua no banco
        $this->assertDatabaseHas('email_logs', [
            'id' => $recente->id,
            'subject' => 'E-mail Recente 30 dias',
        ]);

        // Os antigos foram excluídos
        $this->assertDatabaseMissing('email_logs', [
            'id' => $antigo->id,
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'id' => $outroAntigo->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_comando_avisa_quando_nao_ha_logs_elegiveis(): void
    {
        Carbon::setTestNow('2026-10-23 12:00:00');

        // Apenas registro recente
        EmailLog::forceCreate([
            'to' => ['recente@teste.com'],
            'subject' => 'Recente',
            'body' => 'Corpo recente',
            'sent_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->artisan('email:limpar-logs --dias=365')
            ->expectsOutputToContain('Nenhum log de e-mail com mais de 365 dias encontrado.')
            ->assertSuccessful();

        $this->assertDatabaseCount('email_logs', 1);

        Carbon::setTestNow();
    }
}
