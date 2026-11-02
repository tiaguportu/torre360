<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class InteressadoMotivoPerdaFormularioTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $admin;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusDesistente;

    private Interessado $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['activated_at' => now()]);
        $this->admin->assignRole('super_admin');

        $this->statusNovo = StatusInteressado::firstOrCreate(
            ['nome' => 'Novo'],
            ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]
        );

        $this->statusDesistente = StatusInteressado::firstOrCreate(
            ['nome' => 'Desistente'],
            ['cor' => 'danger', 'ordem' => 90, 'is_final' => true, 'is_ganho' => false]
        );

        $this->lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => $this->admin->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'status_interessado_id' => $this->statusNovo->id,
        ]);
    }

    public function test_status_e_reativo_para_o_motivo_da_perda_aparecer_na_hora(): void
    {
        // Sem live(), o navegador não avisa o servidor da troca de status e o campo só aparece ao salvar.
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $this->lead->id])
            ->assertFormComponentExists(
                'status_interessado_id',
                checkComponentUsing: fn (Select $campo): bool => $campo->isLive(),
            );
    }

    public function test_motivo_da_perda_so_aparece_quando_o_status_e_de_perda(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $this->lead->id])
            ->assertFormFieldHidden('motivo_perda')
            ->fillForm(['status_interessado_id' => $this->statusDesistente->id])
            ->assertFormFieldVisible('motivo_perda')
            ->fillForm(['status_interessado_id' => $this->statusNovo->id])
            ->assertFormFieldHidden('motivo_perda');
    }

    public function test_motivo_da_perda_e_obrigatorio_ao_salvar_como_desistente(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $this->lead->id])
            ->fillForm(['status_interessado_id' => $this->statusDesistente->id, 'motivo_perda' => null])
            ->call('save')
            ->assertHasFormErrors(['motivo_perda' => 'required']);
    }
}
