<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\NotificacoesConselhoTutelar\NotificacaoConselhoTutelarResource;
use App\Filament\Resources\ReposicaoAulas\ReposicaoAulaResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Todo recurso do painel precisa de política (ou restringir o acesso por conta própria). Sem política o Filament libera o recurso a
 * qualquer conta ativa, e a conta da família (papel `responsavel`) entra no painel admin: foi o que aconteceu com *Notificações ao
 * Conselho Tutelar* (nome de criança e faltas) e *Reposição de aulas*.
 */
class AcessoDosRecursosDoPainelTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $papel, array $permissoes = []): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);

        foreach ($permissoes as $nome) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);
        $this->actingAs($usuario);
        session(['active_role' => $papel]);

        return $usuario;
    }

    public function test_todo_recurso_do_painel_tem_politica_ou_restringe_o_acesso(): void
    {
        $semProtecao = [];

        foreach (['admin', 'portal'] as $painel) {
            foreach (Filament::getPanel($painel)->getResources() as $recurso) {
                $temPolitica = Gate::getPolicyFor($recurso::getModel()) !== null;
                $restringeSozinho = collect(['canAccess', 'canViewAny'])
                    ->contains(fn (string $metodo): bool => (new ReflectionMethod($recurso, $metodo))->getDeclaringClass()->getName() === $recurso);

                if (! $temPolitica && ! $restringeSozinho) {
                    $semProtecao[] = $painel.': '.$recurso;
                }
            }
        }

        $this->assertSame([], $semProtecao, 'Recursos sem política e sem restrição própria: qualquer conta ativa os abre.');
    }

    public function test_a_familia_nao_abre_notificacoes_ao_conselho_tutelar_nem_reposicoes(): void
    {
        $this->usuario('responsavel');

        $this->get(NotificacaoConselhoTutelarResource::getUrl('index', panel: 'admin'))->assertForbidden();
        $this->get(ReposicaoAulaResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_quem_tem_a_permissao_abre(): void
    {
        $this->usuario('coordenador', ['ViewAny:NotificacaoConselhoTutelar', 'ViewAny:ReposicaoAula']);

        $this->get(NotificacaoConselhoTutelarResource::getUrl('index', panel: 'admin'))->assertOk();
        $this->get(ReposicaoAulaResource::getUrl('index', panel: 'admin'))->assertOk();
    }

    public function test_a_permissao_de_um_recurso_nao_abre_o_outro(): void
    {
        $this->usuario('professor', ['ViewAny:ReposicaoAula']);

        $this->get(ReposicaoAulaResource::getUrl('index', panel: 'admin'))->assertOk();
        $this->get(NotificacaoConselhoTutelarResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }
}
