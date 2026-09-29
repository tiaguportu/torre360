<?php

namespace Tests\Feature;

use App\Models\CampanhaMarketing;
use App\Models\LandingLead;
use App\Models\User;
use App\Models\VisitaInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmCaptacaoPermissoesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function permissoesEsperadas(): array
    {
        $permissoes = ['View:ConversaoCampanhaWidget', 'View:ConversaoOrigemWidget'];

        foreach (['CampanhaMarketing', 'VisitaInteressado', 'LandingLead'] as $entidade) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $permissoes[] = "{$acao}:{$entidade}";
            }
        }

        return $permissoes;
    }

    public function test_migration_cria_as_permissoes_de_crm_de_captacao(): void
    {
        foreach ($this->permissoesEsperadas() as $permissao) {
            $this->assertTrue(
                Permission::where('name', $permissao)->where('guard_name', 'web')->exists(),
                "Permissão {$permissao} não foi criada."
            );
        }
    }

    public function test_policies_delegam_para_as_permissoes_do_shield(): void
    {
        $usuario = User::factory()->create();

        $this->assertFalse(Gate::forUser($usuario)->allows('viewAny', CampanhaMarketing::class));
        $this->assertFalse(Gate::forUser($usuario)->allows('viewAny', LandingLead::class));
        $this->assertFalse(Gate::forUser($usuario)->allows('create', VisitaInteressado::class));

        $usuario->givePermissionTo('ViewAny:CampanhaMarketing', 'ViewAny:LandingLead', 'Create:VisitaInteressado');
        $usuario->refresh();

        $this->assertTrue(Gate::forUser($usuario)->allows('viewAny', CampanhaMarketing::class));
        $this->assertTrue(Gate::forUser($usuario)->allows('viewAny', LandingLead::class));
        $this->assertTrue(Gate::forUser($usuario)->allows('create', VisitaInteressado::class));
    }
}
