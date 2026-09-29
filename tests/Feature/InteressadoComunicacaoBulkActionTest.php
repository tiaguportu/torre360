<?php

namespace Tests\Feature;

use App\Enums\TipoPublicoComunicacao;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Models\ComunicacaoEmMassa;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InteressadoComunicacaoBulkActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Em produção a migration de permissões já concede ViewAny/Create/Enviar de
     * ComunicacaoEmMassa aos papéis padrão, mas ela roda antes de o papel
     * existir num banco de teste zerado — por isso concedemos aqui.
     */
    private function usuario(string $role): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        foreach (['Create', 'Enviar'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:ComunicacaoEmMassa", 'guard_name' => 'web']);

            if (! $roleModel->hasPermissionTo($permissao)) {
                $roleModel->givePermissionTo($permissao);
            }
        }

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function interessado(): Interessado
    {
        return Interessado::factory()->create([
            'status_interessado_id' => StatusInteressado::factory()->create()->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
        ]);
    }

    public function test_acao_em_lote_cria_comunicacao_com_os_leads_selecionados_e_despacha_o_job(): void
    {
        Queue::fake();

        $selecionado1 = $this->interessado();
        $selecionado2 = $this->interessado();
        $naoSelecionado = $this->interessado();

        Livewire::actingAs($this->usuario('super_admin'))
            ->test(ListInteressados::class)
            ->callTableBulkAction('enviarComunicacaoEmail', [$selecionado1, $selecionado2], data: [
                'assunto' => 'Novidades da escola',
                'corpo' => '<p>Olá [Nome], temos novidades!</p>',
            ])
            ->assertNotified();

        $this->assertDatabaseHas('comunicacao_em_massa', [
            'assunto' => 'Novidades da escola',
            'tipo_publico' => TipoPublicoComunicacao::Interessados->value,
        ]);

        $comunicacao = ComunicacaoEmMassa::latest('id')->first();
        $this->assertEqualsCanonicalizing(
            [$selecionado1->id, $selecionado2->id],
            $comunicacao->filtros['interessado_ids']
        );
        $this->assertNotContains($naoSelecionado->id, $comunicacao->filtros['interessado_ids']);

        Queue::assertPushed(EnviarComunicacaoEmMassaJob::class, fn (EnviarComunicacaoEmMassaJob $job) => $job->comunicacao->is($comunicacao));
    }

    public function test_acao_em_lote_fica_oculta_sem_permissao_de_enviar(): void
    {
        $this->interessado();

        // Vê a listagem de Interessados, mas não tem permissão de Comunicação em Massa.
        Permission::firstOrCreate(['name' => 'ViewAny:Interessado', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->givePermissionTo('ViewAny:Interessado', 'View:Interessado');

        Livewire::actingAs($user)
            ->test(ListInteressados::class)
            ->assertTableBulkActionHidden('enviarComunicacaoEmail');
    }
}
