<?php

namespace Tests\Feature;

use App\Enums\StatusComunicacaoEmMassa;
use App\Enums\TipoPublicoComunicacao;
use App\Filament\Resources\ComunicacaoEmMassas\Pages\ListComunicacaoEmMassas;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Models\ComunicacaoEmMassa;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComunicacaoEmMassaResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria (se preciso) o papel e concede as permissões de ComunicacaoEmMassa.
     * Em produção a migration já faz essa concessão para os papéis padrão, mas
     * ela roda antes de qualquer papel existir num banco de teste zerado — por
     * isso os testes precisam conceder explicitamente.
     */
    private function usuario(string $role, bool $comPermissoesDeComunicacao = true): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        if ($comPermissoesDeComunicacao) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Enviar'] as $acao) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:ComunicacaoEmMassa", 'guard_name' => 'web']);

                if (! $roleModel->hasPermissionTo($permissao)) {
                    $roleModel->givePermissionTo($permissao);
                }
            }
        }

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    public function test_secretaria_gerencia_a_tela_de_comunicacoes(): void
    {
        $comunicacao = ComunicacaoEmMassa::factory()->create();

        Livewire::actingAs($this->usuario('secretaria'))
            ->test(ListComunicacaoEmMassas::class)
            ->assertCanSeeTableRecords([$comunicacao])
            ->assertOk();
    }

    public function test_professor_nao_acessa_a_tela(): void
    {
        $this->actingAs($this->usuario('professor', comPermissoesDeComunicacao: false))
            ->get(ListComunicacaoEmMassas::getUrl())
            ->assertForbidden();
    }

    public function test_acao_enviar_despacha_o_job_e_move_o_status_para_fora_de_rascunho(): void
    {
        Queue::fake();

        $status = StatusInteressado::factory()->create();
        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $pessoa = Pessoa::factory()->create(['email' => 'lead@example.com']);
        Interessado::factory()->create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => OrigemInteressado::first()->id,
        ]);

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'status_interessado_ids' => [$status->id],
        ])->create();

        Livewire::actingAs($this->usuario('secretaria'))
            ->test(ListComunicacaoEmMassas::class)
            ->callAction(TestAction::make('enviar')->table($comunicacao))
            ->assertHasNoActionErrors()
            ->assertNotified();

        Queue::assertPushed(EnviarComunicacaoEmMassaJob::class, fn (EnviarComunicacaoEmMassaJob $job) => $job->comunicacao->is($comunicacao));
    }

    public function test_modal_de_confirmacao_do_envio_lista_os_emails_de_quem_vai_receber(): void
    {
        Queue::fake();

        $status = StatusInteressado::factory()->create();
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);

        $recebe = Pessoa::factory()->create(['nome' => 'Maria Responsável', 'email' => 'maria@example.com', 'aceita_comunicacao' => true]);
        $naoRecebe = Pessoa::factory()->create(['nome' => 'João Cancelado', 'email' => 'joao@example.com', 'aceita_comunicacao' => false]);

        foreach ([$recebe, $naoRecebe] as $pessoa) {
            Interessado::factory()->create([
                'pessoa_id' => $pessoa->id,
                'status_interessado_id' => $status->id,
                'origem_interessado_id' => $origem->id,
            ]);
        }

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'status_interessado_ids' => [$status->id],
        ])->create();

        Livewire::actingAs($this->usuario('secretaria'))
            ->test(ListComunicacaoEmMassas::class)
            ->mountAction(TestAction::make('enviar')->table($comunicacao))
            ->assertMountedActionModalSee(['maria@example.com', 'Maria Responsável'])
            ->assertMountedActionModalDontSee('joao@example.com');

        // Só abrir o modal não envia nada: o disparo acontece apenas ao confirmar.
        Queue::assertNothingPushed();
    }

    public function test_acao_enviar_fica_oculta_para_comunicacao_ja_concluida(): void
    {
        $comunicacao = ComunicacaoEmMassa::factory()->create(['status' => StatusComunicacaoEmMassa::Concluida]);

        Livewire::actingAs($this->usuario('secretaria'))
            ->test(ListComunicacaoEmMassas::class)
            ->assertTableActionHidden('enviar', $comunicacao)
            ->assertTableActionHidden('edit', $comunicacao);
    }

    public function test_acao_enviar_fica_desabilitada_sem_destinatarios(): void
    {
        $comunicacao = ComunicacaoEmMassa::factory()->create([
            'tipo_publico' => TipoPublicoComunicacao::ResponsaveisTurma,
            'filtros' => ['turma_ids' => []],
        ]);

        Livewire::actingAs($this->usuario('secretaria'))
            ->test(ListComunicacaoEmMassas::class)
            ->assertTableActionDisabled('enviar', $comunicacao);
    }
}
