<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LimparAuditLogsAntigosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expurga_apenas_logs_mais_antigos_que_o_limite_de_dias(): void
    {
        $user = User::factory()->create();

        // Log antigo (200 dias atrás)
        $antigoId = DB::table('audit_logs')->insertGetId([
            'user_id' => $user->id,
            'event' => 'view',
            'url' => 'https://exemplo.com/admin/teste',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        // Log recente (30 dias atrás)
        $recenteId = DB::table('audit_logs')->insertGetId([
            'user_id' => $user->id,
            'event' => 'view',
            'url' => 'https://exemplo.com/admin/recente',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        $this->artisan('audit:limpar-logs', ['--dias' => 180])
            ->expectsOutputToContain('1 registro(s) de log de auditoria com mais de 180 dias foram removidos')
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $antigoId]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recenteId]);
    }

    public function test_dry_run_nao_remove_registros(): void
    {
        $user = User::factory()->create();

        $antigoId = DB::table('audit_logs')->insertGetId([
            'user_id' => $user->id,
            'event' => 'view',
            'url' => 'https://exemplo.com/admin/teste',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        $this->artisan('audit:limpar-logs', ['--dias' => 180, '--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] 1 registro(s) de log de auditoria seriam removidos')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $antigoId]);
    }
}
