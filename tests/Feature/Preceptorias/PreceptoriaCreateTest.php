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
}
