<?php

namespace Tests\Feature;

use App\Filament\Resources\Salas\Pages\CreateSala;
use App\Filament\Resources\Salas\Pages\ListSalas;
use App\Models\Sala;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_lista_e_cria_sala(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $existente = Sala::factory()->create(['unidade_id' => $unidade->id, 'nome' => 'Laboratório de Informática']);

        Livewire::actingAs($this->admin())
            ->test(ListSalas::class)
            ->assertCanSeeTableRecords([$existente]);

        Livewire::actingAs($this->admin())
            ->test(CreateSala::class)
            ->fillForm([
                'unidade_id' => $unidade->id,
                'nome' => 'Sala 101',
                'capacidade' => 30,
                'tipo' => 'Sala de aula',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sala', ['nome' => 'Sala 101', 'capacidade' => 30]);
    }

    public function test_scope_ativas_ignora_salas_inativas(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $ativa = Sala::factory()->create(['unidade_id' => $unidade->id]);
        Sala::factory()->inativa()->create(['unidade_id' => $unidade->id]);

        $resultado = Sala::ativas()->get();

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->first()->is($ativa));
    }
}
