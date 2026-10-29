<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ViewPreceptoria;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\Turma;
use App\Models\User;
use App\Services\PreceptoriaAgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PreceptoriaHistoricoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissoes
     */
    private function usuarioCom(array $permissoes, string $papelAtivo = 'admin'): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => $papelAtivo, 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['name' => 'Maria da Secretaria']);
        $user->assignRole($papel);
        session(['active_role' => $papelAtivo]);

        return $user;
    }

    public function test_a_tela_lista_mudancas_e_avisos_com_autor_e_rotulo(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria']);
        $this->actingAs($user);
        $preceptoria = Preceptoria::factory()->create();

        $servico = app(PreceptoriaAgendaService::class);
        $servico->registrarHistorico($preceptoria, 'troca_professor_preceptoria', 'Professor alterado de Ana para Bia');
        $servico->registrarHistorico($preceptoria, 'notificacao_alteracao_preceptoria', 'Aviso de troca de professor enviado para 3 destinatário(s)');

        $html = view('filament.components.preceptoria-historico', ['preceptoria' => $preceptoria])->render();

        $this->assertStringContainsString('Troca de professor', $html);
        $this->assertStringContainsString('Professor alterado de Ana para Bia', $html);
        $this->assertStringContainsString('Aviso enviado', $html);
        $this->assertStringContainsString('Aviso de troca de professor enviado para 3 destinatário(s)', $html);
        $this->assertStringContainsString('por Maria da Secretaria', $html);
        // o mais recente (o aviso) vem antes
        $this->assertLessThan(strpos($html, 'Professor alterado de Ana'), strpos($html, 'Aviso de troca de professor'));
    }

    public function test_sem_registros_mostra_a_mensagem_de_vazio(): void
    {
        $html = view('filament.components.preceptoria-historico', ['preceptoria' => Preceptoria::factory()->create()])->render();

        $this->assertStringContainsString('Nenhuma mudança ou aviso registrado', $html);
    }

    public function test_o_limite_corta_para_as_ultimas_alteracoes(): void
    {
        $preceptoria = Preceptoria::factory()->create();
        $servico = app(PreceptoriaAgendaService::class);
        foreach (['um', 'dois', 'tres'] as $descricao) {
            $servico->registrarHistorico($preceptoria, 'reagendamento_preceptoria', "mudança {$descricao}");
        }

        $html = view('filament.components.preceptoria-historico', ['preceptoria' => $preceptoria, 'limite' => 2])->render();

        $this->assertStringContainsString('mudança tres', $html);
        $this->assertStringContainsString('mudança dois', $html);
        $this->assertStringNotContainsString('mudança um', $html);
    }

    public function test_botao_de_historico_no_registro_so_aparece_para_quem_edita(): void
    {
        $preceptoria = Preceptoria::factory()->create();

        $quemEdita = $this->usuarioCom(['ViewAny:Preceptoria', 'View:Preceptoria', 'Update:Preceptoria']);
        Livewire::actingAs($quemEdita)
            ->test(ViewPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->assertActionVisible('historicoAlteracoes');

        $somenteLeitura = $this->usuarioCom(['ViewAny:Preceptoria', 'View:Preceptoria'], 'secretaria');
        Livewire::actingAs($somenteLeitura)
            ->test(ViewPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->assertActionHidden('historicoAlteracoes');
    }

    public function test_agendar_e_liberar_pela_tela_de_agendamento_entram_no_historico(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno da Tela', 'email' => 'aluno@example.com']);
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $professor->id]);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id]);
        $preceptoria = Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => now()->addDays(3)->toDateString()]);

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $matricula->id, 'preceptoria_id' => $preceptoria->id])
            ->call('agendar');

        $this->assertSame($matricula->id, $preceptoria->fresh()->matricula_id);
        $this->assertSame('Aluno agendado: Aluno da Tela', $preceptoria->historico()->first()->description);

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->call('liberarHorario', $preceptoria->id);

        $this->assertNull($preceptoria->fresh()->matricula_id);
        $this->assertSame('Horário liberado (o aluno era Aluno da Tela)', $preceptoria->historico()->first()->description);
    }
}
