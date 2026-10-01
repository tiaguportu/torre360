<?php

namespace Tests\Feature;

use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditContratoGerarFaturasTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($role);
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    public function test_acao_gerar_faturas_continua_funcionando_apos_extracao_do_servico(): void
    {
        $this->autenticarSuperAdmin();

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $aluno = Pessoa::create(['nome' => 'Aluno Teste EditContrato']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'periodo_letivo_id' => $periodo->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 600.0,
            'data_aceite' => '2026-01-10',
        ]);

        Livewire::test(EditContrato::class, ['record' => $contrato->getKey()])
            ->callAction('gerarFaturas', data: [
                'quantidade_parcelas' => 6,
                'valor_entrada' => 0,
            ])
            ->assertSuccessful()
            ->assertHasNoActionErrors();

        $this->assertEquals(6, $contrato->fresh()->faturas()->count());
    }
}
