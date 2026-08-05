<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\CreatePreceptoria;
use App\Models\CicloPreceptoria;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PreceptoriaCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_criar_multiplas_preceptorias_para_varias_datas(): void
    {
        Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Preceptoria', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:Preceptoria', 'Create:Preceptoria']);

        $ciclo = CicloPreceptoria::create([
            'nome' => 'Ciclo Teste',
            'data_inicio' => now()->startOfMonth(),
            'data_fim' => now()->endOfMonth(),
        ]);

        $professor = Pessoa::factory()->create();

        $datasInput = [
            ['data' => '2026-08-10'],
            ['data' => '2026-08-11'],
            ['data' => '2026-08-12'],
        ];

        Livewire::actingAs($user)
            ->test(CreatePreceptoria::class)
            ->fillForm([
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '14:00',
                'hora_fim' => '14:30',
                'datas' => $datasInput,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(3, Preceptoria::count());

        $datas = ['2026-08-10 00:00:00', '2026-08-11 00:00:00', '2026-08-12 00:00:00'];
        foreach ($datas as $data) {
            $this->assertDatabaseHas('preceptoria', [
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'data' => $data,
            ]);
        }
    }

    public function test_pode_criar_preceptorias_por_intervalo_de_datas_com_filtro_de_dias_da_semana(): void
    {
        Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:Preceptoria', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:Preceptoria', 'Create:Preceptoria']);

        $ciclo = CicloPreceptoria::create([
            'nome' => 'Ciclo Setembro',
            'data_inicio' => '2026-09-01',
            'data_fim' => '2026-09-30',
        ]);

        $professor = Pessoa::factory()->create();

        // 01/09/2026 a 07/09/2026:
        // 01/09 (Terça - 2), 02/09 (Quarta - 3), 03/09 (Quinta - 4), 04/09 (Sexta - 5), 05/09 (Sáb - 6), 06/09 (Dom - 0), 07/09 (Seg - 1)
        // Selecionando apenas Terça (2) e Quinta (4) no intervalo: 01/09/2026 e 03/09/2026 (2 datas).

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
                'dias_semana_range' => [2, 4], // Terça e Quinta
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(2, Preceptoria::count());

        $this->assertDatabaseHas('preceptoria', [
            'ciclo_preceptoria_id' => $ciclo->id,
            'professor_id' => $professor->id,
            'data' => '2026-09-01 00:00:00',
        ]);

        $this->assertDatabaseHas('preceptoria', [
            'ciclo_preceptoria_id' => $ciclo->id,
            'professor_id' => $professor->id,
            'data' => '2026-09-03 00:00:00',
        ]);
    }
}
