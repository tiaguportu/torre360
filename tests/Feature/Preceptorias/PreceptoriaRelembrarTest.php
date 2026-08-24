<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Preceptorias\LembretePreceptoriaNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PreceptoriaRelembrarTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_agendamento_futuro_considera_data_e_horario(): void
    {
        Carbon::setTestNow('2026-08-23 21:00:00');

        // Preceptoria no dia seguinte de manhã (deve ser futuro)
        $preceptoriaAmanha = new Preceptoria([
            'data' => '2026-08-24',
            'hora_inicio' => '07:15:00',
        ]);
        $this->assertTrue($preceptoriaAmanha->isAgendamentoFuturo());

        // Preceptoria no mesmo dia, horas depois (deve ser futuro)
        $preceptoriaMaisTarde = new Preceptoria([
            'data' => '2026-08-23',
            'hora_inicio' => '22:00:00',
        ]);
        $this->assertTrue($preceptoriaMaisTarde->isAgendamentoFuturo());

        // Preceptoria no mesmo dia, horas antes (deve ser passado)
        $preceptoriaMaisCedo = new Preceptoria([
            'data' => '2026-08-23',
            'hora_inicio' => '14:00:00',
        ]);
        $this->assertFalse($preceptoriaMaisCedo->isAgendamentoFuturo());

        // Preceptoria no passado
        $preceptoriaPassada = new Preceptoria([
            'data' => '2026-08-20',
            'hora_inicio' => '10:00:00',
        ]);
        $this->assertFalse($preceptoriaPassada->isAgendamentoFuturo());

        Carbon::setTestNow();
    }

    public function test_listagem_de_preceptorias_carrega_com_sucesso_para_admin(): void
    {
        Carbon::setTestNow('2026-08-23 21:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permViewAny = Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo($permViewAny);

        $user = User::factory()->create();
        $user->assignRole($roleAdmin);
        session(['active_role' => 'admin']);

        $professor = Pessoa::factory()->create();
        $aluno = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        // Preceptoria futura e agendada
        $preceptoriaFutura = Preceptoria::factory()->create([
            'data' => '2026-08-24',
            'hora_inicio' => '07:15:00',
            'hora_fim' => '08:15:00',
            'professor_id' => $professor->id,
            'matricula_id' => $matricula->id,
        ]);

        $this->assertTrue($preceptoriaFutura->isCompletamenteAgendada());
        $this->assertTrue($preceptoriaFutura->isAgendamentoFuturo());

        Livewire::actingAs($user)
            ->test(ListPreceptorias::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$preceptoriaFutura]);

        Carbon::setTestNow();
    }

    public function test_bulk_action_relembrar_lote_envia_apenas_para_preceptorias_elegiveis(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-23 21:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permViewAny = Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo($permViewAny);

        $user = User::factory()->create();
        $user->assignRole($roleAdmin);
        session(['active_role' => 'admin']);

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        // Elegível: futura e completamente agendada
        $preceptoriaFutura = Preceptoria::factory()->create([
            'data' => '2026-08-24',
            'hora_inicio' => '07:15:00',
            'hora_fim' => '08:15:00',
            'professor_id' => $professor->id,
            'matricula_id' => $matricula->id,
        ]);

        // Não elegível: já ocorreu
        $preceptoriaPassada = Preceptoria::factory()->create([
            'data' => '2026-08-20',
            'hora_inicio' => '10:00:00',
            'hora_fim' => '11:00:00',
            'professor_id' => $professor->id,
            'matricula_id' => $matricula->id,
        ]);

        // Não elegível: sem aluno vinculado
        $preceptoriaIncompleta = Preceptoria::factory()->create([
            'data' => '2026-08-25',
            'hora_inicio' => '09:00:00',
            'hora_fim' => '10:00:00',
            'professor_id' => $professor->id,
            'matricula_id' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ListPreceptorias::class)
            ->callTableBulkAction('relembrar_lote', [$preceptoriaFutura, $preceptoriaPassada, $preceptoriaIncompleta]);

        Notification::assertSentTo($professor, LembretePreceptoriaNotification::class, function ($notification) use ($preceptoriaFutura) {
            return $notification->preceptoria->is($preceptoriaFutura);
        });

        Notification::assertSentTimes(LembretePreceptoriaNotification::class, 2);

        Carbon::setTestNow();
    }
}
