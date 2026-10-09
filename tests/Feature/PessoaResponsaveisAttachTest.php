<?php

namespace Tests\Feature;

use App\Filament\Resources\Pessoas\Pages\EditPessoa;
use App\Filament\Resources\Pessoas\RelationManagers\AlunosRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\ResponsaveisRelationManager;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class PessoaResponsaveisAttachTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_montar_e_vincular_responsavel_pelo_relation_manager(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        Gate::before(fn () => true);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Responsavel Teste']);
        $tipoVinculo = TipoVinculo::create(['nome' => 'Mãe']);

        Livewire::test(ResponsaveisRelationManager::class, [
            'ownerRecord' => $aluno,
            'pageClass' => EditPessoa::class,
        ])
            ->mountTableAction('attach')
            ->assertTableActionMounted('attach')
            ->setTableActionData([
                'recordId' => $responsavel->id,
                'tipo_vinculo_id' => $tipoVinculo->id,
                'permissao_retirada' => true,
                'observacao' => 'Teste automatizado de vínculo',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('aluno_responsavel', [
            'aluno_id' => $aluno->id,
            'responsavel_id' => $responsavel->id,
            'tipo_vinculo_id' => $tipoVinculo->id,
            'permissao_retirada' => 1,
            'observacao' => 'Teste automatizado de vínculo',
        ]);
    }

    public function test_valida_campos_obrigatorios_ao_vincular_responsavel(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        Gate::before(fn () => true);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);

        Livewire::test(ResponsaveisRelationManager::class, [
            'ownerRecord' => $aluno,
            'pageClass' => EditPessoa::class,
        ])
            ->mountTableAction('attach')
            ->setTableActionData([
                'recordId' => null,
                'tipo_vinculo_id' => null,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors([
                'recordId' => 'required',
                'tipo_vinculo_id' => 'required',
            ]);
    }

    public function test_pode_montar_e_vincular_aluno_pelo_relation_manager(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        Gate::before(fn () => true);

        $responsavel = Pessoa::factory()->create(['nome' => 'Responsavel Teste']);
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $tipoVinculo = TipoVinculo::create(['nome' => 'Pai']);

        Livewire::test(AlunosRelationManager::class, [
            'ownerRecord' => $responsavel,
            'pageClass' => EditPessoa::class,
        ])
            ->mountTableAction('attach')
            ->assertTableActionMounted('attach')
            ->setTableActionData([
                'recordId' => $aluno->id,
                'tipo_vinculo_id' => $tipoVinculo->id,
                'permissao_retirada' => true,
                'observacao' => 'Teste aluno relation manager',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('aluno_responsavel', [
            'aluno_id' => $aluno->id,
            'responsavel_id' => $responsavel->id,
            'tipo_vinculo_id' => $tipoVinculo->id,
            'permissao_retirada' => 1,
            'observacao' => 'Teste aluno relation manager',
        ]);
    }
}
