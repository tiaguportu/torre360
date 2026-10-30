<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Models\CicloPreceptoria;
use App\Models\Preceptoria;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A listagem de preceptorias esconde, por padrão, as de ciclos cujo término já passou.
 */
class PreceptoriaCiclosVencidosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $papel->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($papel);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function ciclo(string $nome, string $inicio, string $fim): CicloPreceptoria
    {
        return CicloPreceptoria::create(['nome' => $nome, 'data_inicio' => $inicio, 'data_fim' => $fim]);
    }

    private function preceptoria(?CicloPreceptoria $ciclo, string $hora): Preceptoria
    {
        return Preceptoria::factory()->create(['ciclo_preceptoria_id' => $ciclo?->id, 'hora_inicio' => $hora]);
    }

    public function test_por_padrao_a_listagem_esconde_as_de_ciclos_vencidos(): void
    {
        $vencido = $this->ciclo('Vencido', now()->subMonths(3)->toDateString(), now()->subDay()->toDateString());
        $terminaHoje = $this->ciclo('Termina hoje', now()->subMonth()->toDateString(), now()->toDateString());
        $emCurso = $this->ciclo('Em curso', now()->subMonth()->toDateString(), now()->addMonth()->toDateString());
        $futuro = $this->ciclo('Futuro', now()->addMonth()->toDateString(), now()->addMonths(3)->toDateString());

        $doVencido = $this->preceptoria($vencido, '08:00:00');
        $doTerminaHoje = $this->preceptoria($terminaHoje, '09:00:00');
        $doEmCurso = $this->preceptoria($emCurso, '10:00:00');
        $doFuturo = $this->preceptoria($futuro, '11:00:00');
        $semCiclo = $this->preceptoria(null, '12:00:00');

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->assertCanSeeTableRecords([$doTerminaHoje, $doEmCurso, $doFuturo, $semCiclo])
            ->assertCanNotSeeTableRecords([$doVencido]);
    }

    public function test_desligando_a_chave_as_de_ciclos_vencidos_aparecem(): void
    {
        $vencido = $this->ciclo('Vencido', now()->subMonths(3)->toDateString(), now()->subDay()->toDateString());
        $emCurso = $this->ciclo('Em curso', now()->subMonth()->toDateString(), now()->addMonth()->toDateString());

        $doVencido = $this->preceptoria($vencido, '08:00:00');
        $doEmCurso = $this->preceptoria($emCurso, '10:00:00');

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->assertCanNotSeeTableRecords([$doVencido])
            ->filterTable('ocultar_ciclos_vencidos', false)
            ->assertCanSeeTableRecords([$doVencido, $doEmCurso]);
    }

    public function test_o_seletor_de_ciclo_oferece_so_os_ciclos_que_nao_venceram(): void
    {
        $this->ciclo('Vencido', now()->subMonths(3)->toDateString(), now()->subDay()->toDateString());
        $this->ciclo('Termina hoje', now()->subMonth()->toDateString(), now()->toDateString());
        $this->ciclo('Futuro', now()->addMonth()->toDateString(), now()->addMonths(3)->toDateString());

        $teste = Livewire::actingAs($this->usuario())->test(ListPreceptorias::class);

        $this->assertEqualsCanonicalizing(['Termina hoje', 'Futuro'], $this->opcoesDoSeletorDeCiclo($teste));

        // com a chave desligada o seletor continua só com os válidos
        $teste->filterTable('ocultar_ciclos_vencidos', false);

        $this->assertEqualsCanonicalizing(['Termina hoje', 'Futuro'], $this->opcoesDoSeletorDeCiclo($teste));
    }

    /**
     * As opções que o usuário vê no filtro "Ciclo de Preceptoria" (o campo do formulário de filtros).
     *
     * @return array<int, string>
     */
    private function opcoesDoSeletorDeCiclo(Testable $teste): array
    {
        $campo = collect($teste->instance()->getTableFiltersForm()->getFlatComponents(false, true))
            ->first(fn ($componente) => $componente instanceof Select && str_ends_with($componente->getStatePath(), 'ciclo_preceptoria_id.value'));

        $this->assertNotNull($campo, 'O seletor de ciclo não está no formulário de filtros.');

        return array_values($campo->getOptions());
    }

    public function test_o_escopo_nao_vencidos_inclui_ciclo_sem_termino(): void
    {
        $this->ciclo('Vencido', now()->subMonths(3)->toDateString(), now()->subDay()->toDateString());
        $semTermino = CicloPreceptoria::create(['nome' => 'Sem término', 'data_inicio' => now()->subMonth()->toDateString(), 'data_fim' => null]);

        $this->assertSame([$semTermino->id], CicloPreceptoria::naoVencidos()->pluck('id')->all());
    }
}
