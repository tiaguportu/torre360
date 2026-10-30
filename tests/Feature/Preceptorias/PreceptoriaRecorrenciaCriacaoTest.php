<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\CreatePreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Models\CicloPreceptoria;
use App\Models\DiaNaoLetivo;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class PreceptoriaRecorrenciaCriacaoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissoes
     */
    private function usuarioCom(array $permissoes): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // o papel ativo na sessão só vale se o usuário realmente o tem (senão a página dá 403)
        $papel = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($papel);
        session(['active_role' => 'admin']);

        return $user;
    }

    /**
     * @return array<int, string> datas (Y-m-d) das preceptorias, em ordem
     */
    private function datasCriadas(): array
    {
        return Preceptoria::orderBy('data')->get()->map(fn (Preceptoria $p) => $p->data->toDateString())->all();
    }

    public function test_recorrente_pula_fim_de_semana_e_feriado_por_padrao(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();

        // 01/09 (terça) a 07/09 (segunda): sáb 05 e dom 06 + feriado 07 ficam de fora
        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'hora_fim' => '10:30',
                'tipo_selecao_data' => 'intervalo',
                'data_inicio_range' => '2026-09-01',
                'data_fim_range' => '2026-09-07',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ListPreceptorias::getUrl());

        $this->assertSame(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'], $this->datasCriadas());
    }

    public function test_recorrente_inclui_fim_de_semana_e_feriado_quando_desligados(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'tipo_selecao_data' => 'intervalo',
                'data_inicio_range' => '2026-09-01',
                'data_fim_range' => '2026-09-07',
                'ignorar_fins_de_semana' => false,
                'ignorar_feriados' => false,
                'ignorar_dias_nao_letivos' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertCount(7, $this->datasCriadas());
    }

    public function test_recorrente_pula_dia_nao_letivo_do_periodo_do_ciclo(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $periodo = PeriodoLetivo::factory()->create();
        DiaNaoLetivo::create(['periodo_letivo_id' => $periodo->id, 'data' => '2026-09-02', 'descricao' => 'Recesso', 'flag_ativo' => true]);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30', 'periodo_letivo_id' => $periodo->id]);
        $professor = Pessoa::factory()->create();

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'tipo_selecao_data' => 'intervalo',
                'data_inicio_range' => '2026-09-01',
                'data_fim_range' => '2026-09-04',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['2026-09-01', '2026-09-03', '2026-09-04'], $this->datasCriadas());
    }

    public function test_recorrente_a_cada_duas_semanas_nos_dias_escolhidos(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'tipo_selecao_data' => 'intervalo',
                'data_inicio_range' => '2026-09-01',
                'data_fim_range' => '2026-09-30',
                'dias_semana_range' => [2],
                'intervalo_semanas' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['2026-09-01', '2026-09-15', '2026-09-29'], $this->datasCriadas());
    }

    public function test_recorrente_nao_duplica_horario_que_o_professor_ja_tem(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();
        Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-09-02', 'hora_inicio' => '10:00:00', 'hora_fim' => '10:30:00']);

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'hora_fim' => '10:30',
                'tipo_selecao_data' => 'intervalo',
                'data_inicio_range' => '2026-09-01',
                'data_fim_range' => '2026-09-03',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['2026-09-01', '2026-09-02', '2026-09-03'], $this->datasCriadas());
        $this->assertSame(1, Preceptoria::whereDate('data', '2026-09-02')->count());
    }

    public function test_recorrente_sem_nenhuma_data_valida_nao_cria_nada(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
        $professor = Pessoa::factory()->create();

        // 05 e 06/09 são sábado e domingo
        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'tipo_selecao_data' => 'intervalo',
                'data_inicio_range' => '2026-09-05',
                'data_fim_range' => '2026-09-06',
            ])
            ->call('create')
            ->assertNoRedirect();

        $this->assertSame(0, Preceptoria::count());
    }

    public function test_escolher_o_ciclo_sugere_a_janela_do_modo_recorrente(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = CicloPreceptoria::create(['nome' => 'Ciclo', 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->set('data.tipo_selecao_data', 'intervalo')
            ->set('data.ciclo_preceptoria_id', $ciclo->id)
            // o DatePicker guarda a data com a hora atual anexada; só o dia importa (o serviço usa startOfDay)
            ->assertSet('data.data_inicio_range', fn ($valor) => str_starts_with($valor, '2026-09-01'))
            ->assertSet('data.data_fim_range', fn ($valor) => str_starts_with($valor, '2026-09-30'));
    }

    public function test_listagem_filtra_por_ciclo_de_preceptoria(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria']);
        // ciclos ainda válidos (a listagem esconde os vencidos por padrão); datas relativas a hoje
        $cicloA = CicloPreceptoria::create(['nome' => 'Ciclo A', 'data_inicio' => now()->subMonths(2)->toDateString(), 'data_fim' => now()->addMonth()->toDateString()]);
        $cicloB = CicloPreceptoria::create(['nome' => 'Ciclo B', 'data_inicio' => now()->addMonth()->toDateString(), 'data_fim' => now()->addMonths(4)->toDateString()]);

        $doA = Preceptoria::factory()->create(['ciclo_preceptoria_id' => $cicloA->id, 'hora_inicio' => '09:00:00']);
        $doB = Preceptoria::factory()->create(['ciclo_preceptoria_id' => $cicloB->id, 'hora_inicio' => '10:00:00']);
        $semCiclo = Preceptoria::factory()->create(['ciclo_preceptoria_id' => null, 'hora_inicio' => '11:00:00']);

        Livewire::actingAs($user)
            ->test(ListPreceptorias::class)
            ->assertCanSeeTableRecords([$doA, $doB, $semCiclo])
            ->filterTable('ciclo_preceptoria_id', $cicloA->id)
            ->assertCanSeeTableRecords([$doA])
            ->assertCanNotSeeTableRecords([$doB, $semCiclo])
            ->assertTableColumnStateSet('cicloPreceptoria.nome', 'Ciclo A', $doA);
    }
}
