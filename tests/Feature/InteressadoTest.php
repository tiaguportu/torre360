<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class InteressadoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private function criarStatusBasicos(): array
    {
        $novo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $emAtendimento = StatusInteressado::create([
            'nome' => 'Em Atendimento',
            'cor' => 'warning',
            'ordem' => 2,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $matriculado = StatusInteressado::create([
            'nome' => 'Matriculado',
            'cor' => 'success',
            'ordem' => 3,
            'is_final' => true,
            'is_ganho' => true,
        ]);

        $perdido = StatusInteressado::create([
            'nome' => 'Perdido',
            'cor' => 'danger',
            'ordem' => 4,
            'is_final' => true,
            'is_ganho' => false,
        ]);

        return compact('novo', 'emAtendimento', 'matriculado', 'perdido');
    }

    private function criarInteressado(array $atributos = []): Interessado
    {
        $statuses = $this->criarStatusBasicos();
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $pessoa = Pessoa::factory()->create();
        $usuario = User::factory()->create();

        return Interessado::create(array_merge([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $usuario->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $statuses['novo']->id,
        ], $atributos));
    }

    // ─── Testes de Criação ─────────────────────────────────────────

    public function test_criar_interessado_com_sucesso(): void
    {
        $interessado = $this->criarInteressado();

        $this->assertDatabaseHas('interessado', [
            'id' => $interessado->id,
        ]);
        $this->assertNotNull($interessado->pessoa);
        $this->assertNotNull($interessado->usuario);
        $this->assertNotNull($interessado->origem);
        $this->assertNotNull($interessado->status);
    }

    public function test_criar_interessado_com_todos_os_campos(): void
    {
        $interessado = $this->criarInteressado([
            'valor_estimado' => 2500.00,
            'temperatura' => 'quente',
            'data_proximo_contato' => now()->addDays(3),
            'observacoes' => 'Lead muito interessado',
        ]);

        $this->assertDatabaseHas('interessado', [
            'id' => $interessado->id,
            'valor_estimado' => 2500.00,
            'temperatura' => 'quente',
        ]);
    }

    // ─── Testes de Atualização de Status ───────────────────────────

    public function test_atualizar_status_do_interessado(): void
    {
        $interessado = $this->criarInteressado();
        $statusAtendimento = StatusInteressado::where('nome', 'Em Atendimento')->first();

        $interessado->update(['status_interessado_id' => $statusAtendimento->id]);

        $this->assertEquals($statusAtendimento->id, $interessado->fresh()->status_interessado_id);
    }

    public function test_marcar_como_matriculado_registra_data_conversao(): void
    {
        $interessado = $this->criarInteressado();
        $statusMatriculado = StatusInteressado::where('nome', 'Matriculado')->first();

        $interessado->update([
            'status_interessado_id' => $statusMatriculado->id,
            'data_conversao' => now(),
        ]);

        $this->assertNotNull($interessado->fresh()->data_conversao);
    }

    public function test_marcar_como_perdido_registra_motivo_perda(): void
    {
        $interessado = $this->criarInteressado();
        $statusPerdido = StatusInteressado::where('nome', 'Perdido')->first();

        $interessado->update([
            'status_interessado_id' => $statusPerdido->id,
            'motivo_perda' => 'Preço',
        ]);

        $this->assertEquals('Preço', $interessado->fresh()->motivo_perda);
    }

    // ─── Testes do método precisaDeContato() ───────────────────────

    public function test_precisa_de_contato_retorna_falso_sem_data_agendada(): void
    {
        $interessado = $this->criarInteressado([
            'data_proximo_contato' => null,
        ]);

        $this->assertFalse($interessado->precisaDeContato());
    }

    public function test_precisa_de_contato_retorna_verdadeiro_quando_atrasado(): void
    {
        $interessado = $this->criarInteressado([
            'data_proximo_contato' => now()->subDays(2),
        ]);

        $this->assertTrue($interessado->precisaDeContato());
    }

    public function test_precisa_de_contato_retorna_falso_quando_futuro(): void
    {
        $interessado = $this->criarInteressado([
            'data_proximo_contato' => now()->addDays(5),
        ]);

        $this->assertFalse($interessado->precisaDeContato());
    }

    // ─── Testes do método diasNoFunil() ────────────────────────────

    public function test_dias_no_funil_para_lead_recente(): void
    {
        $interessado = $this->criarInteressado();

        $this->assertEquals(0, $interessado->diasNoFunil());
    }

    public function test_dias_no_funil_para_lead_antigo(): void
    {
        $interessado = $this->criarInteressado();
        // Força a data de criação para 15 dias atrás ('created_at' não é mass-assignable, por isso forceFill)
        $interessado->forceFill(['created_at' => now()->subDays(15)])->save();

        $this->assertEquals(15, $interessado->fresh()->diasNoFunil());
    }

    // ─── Testes dos Scopes ─────────────────────────────────────────

    public function test_scope_ativos_filtra_status_finais(): void
    {
        $interessadoAtivo = $this->criarInteressado();

        $statusPerdido = StatusInteressado::where('nome', 'Perdido')->first();
        $interessadoPerdido = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => User::factory()->create()->id,
            'origem_interessado_id' => OrigemInteressado::first()->id,
            'status_interessado_id' => $statusPerdido->id,
        ]);

        $ativos = Interessado::ativos()->get();

        $this->assertTrue($ativos->contains($interessadoAtivo));
        $this->assertFalse($ativos->contains($interessadoPerdido));
    }

    public function test_scope_precisa_contato_filtra_atrasados(): void
    {
        $interessadoAtrasado = $this->criarInteressado([
            'data_proximo_contato' => now()->subDays(2),
        ]);

        $interessadoEmDia = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => User::factory()->create()->id,
            'origem_interessado_id' => OrigemInteressado::first()->id,
            'status_interessado_id' => StatusInteressado::first()->id,
            'data_proximo_contato' => now()->addDays(5),
        ]);

        $pendentes = Interessado::precisaContato()->get();

        $this->assertTrue($pendentes->contains($interessadoAtrasado));
        $this->assertFalse($pendentes->contains($interessadoEmDia));
    }

    public function test_scope_do_consultor_filtra_por_usuario(): void
    {
        $interessado = $this->criarInteressado();
        $outroUsuario = User::factory()->create();

        $doConsultor = Interessado::doConsultor($interessado->usuario_id)->get();
        $doOutro = Interessado::doConsultor($outroUsuario->id)->get();

        $this->assertTrue($doConsultor->contains($interessado));
        $this->assertTrue($doOutro->isEmpty());
    }

    // ─── Testes de Histórico de Contato ────────────────────────────

    public function test_registrar_historico_de_contato(): void
    {
        $interessado = $this->criarInteressado();
        $tipoContato = TipoContatoInteressado::create(['nome' => 'Telefone']);

        $historico = $interessado->historicos()->create([
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => 'Conversamos sobre vagas disponíveis.',
            'data_contato' => now(),
            'usuario_id' => $interessado->usuario_id,
            'duracao_minutos' => 15,
            'resultado' => 'agendou_visita',
        ]);

        $this->assertDatabaseHas('historico_contato', [
            'id' => $historico->id,
            'interessado_id' => $interessado->id,
            'usuario_id' => $interessado->usuario_id,
            'duracao_minutos' => 15,
            'resultado' => 'agendou_visita',
        ]);
    }

    public function test_total_contatos_retorna_contagem(): void
    {
        $interessado = $this->criarInteressado();
        $tipoContato = TipoContatoInteressado::create(['nome' => 'Telefone']);

        $interessado->historicos()->create([
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => 'Primeiro contato.',
            'data_contato' => now(),
        ]);

        $interessado->historicos()->create([
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => 'Segundo contato.',
            'data_contato' => now(),
        ]);

        $this->assertEquals(2, $interessado->totalContatos());
    }

    // ─── Testes dos Relacionamentos ────────────────────────────────

    public function test_relacionamento_pessoa(): void
    {
        $interessado = $this->criarInteressado();

        $this->assertInstanceOf(Pessoa::class, $interessado->pessoa);
    }

    public function test_relacionamento_usuario(): void
    {
        $interessado = $this->criarInteressado();

        $this->assertInstanceOf(User::class, $interessado->usuario);
    }

    public function test_relacionamento_status(): void
    {
        $interessado = $this->criarInteressado();

        $this->assertInstanceOf(StatusInteressado::class, $interessado->status);
    }

    public function test_relacionamento_origem(): void
    {
        $interessado = $this->criarInteressado();

        $this->assertInstanceOf(OrigemInteressado::class, $interessado->origem);
    }

    public function test_ultimo_historico_retorna_o_mais_recente(): void
    {
        $interessado = $this->criarInteressado();
        $tipoContato = TipoContatoInteressado::create(['nome' => 'WhatsApp']);

        $interessado->historicos()->create([
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => 'Primeiro contato.',
            'data_contato' => now()->subDays(5),
        ]);

        $ultimo = $interessado->historicos()->create([
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => 'Contato mais recente.',
            'data_contato' => now(),
        ]);

        $this->assertEquals($ultimo->id, $interessado->fresh()->ultimoHistorico->id);
    }

    // ─── Teste de Flags do Status ──────────────────────────────────

    public function test_status_final_e_ganho(): void
    {
        $this->criarStatusBasicos();

        $matriculado = StatusInteressado::where('nome', 'Matriculado')->first();
        $perdido = StatusInteressado::where('nome', 'Perdido')->first();
        $novo = StatusInteressado::where('nome', 'Novo')->first();

        $this->assertTrue($matriculado->is_final);
        $this->assertTrue($matriculado->is_ganho);
        $this->assertTrue($perdido->is_final);
        $this->assertFalse($perdido->is_ganho);
        $this->assertFalse($novo->is_final);
        $this->assertFalse($novo->is_ganho);
    }

    public function test_pagina_de_edicao_renderiza_o_resumo_do_lead(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $interessado = $this->criarInteressado();

        $this->actingAs($admin)
            ->get("/admin/interessados/{$interessado->id}/edit")
            ->assertOk()
            ->assertSee('Resumo do Lead')
            ->assertSee('Detalhamento do score')
            ->assertSee('Dias no funil');
    }

    public function test_listagem_renderiza_com_abas_e_filtra_por_situacao(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $atrasado = $this->criarInteressado(['data_proximo_contato' => now()->subDays(3)]);
        $emDia = $this->criarInteressado(['data_proximo_contato' => now()->addDays(3), 'temperatura' => 'quente']);

        $this->actingAs($admin);

        $this->get('/admin/interessados')
            ->assertOk()
            ->assertSee('Precisa de contato')
            ->assertSee('Estagnados');

        Livewire::test(ListInteressados::class)
            ->set('activeTab', 'precisa_contato')
            ->assertCanSeeTableRecords([$atrasado])
            ->assertCanNotSeeTableRecords([$emDia])
            ->set('activeTab', 'quentes')
            ->assertCanSeeTableRecords([$emDia])
            ->assertCanNotSeeTableRecords([$atrasado]);
    }

    public function test_modal_de_ajuda_da_listagem_explica_abas_colunas_e_acoes(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $pagina = new ListInteressados;
        $html = (new \ReflectionMethod($pagina, 'getHelpContent'))->invoke($pagina)->render();

        foreach (['Para que serve cada aba', 'Como ler a tabela', 'Ações em cada linha', 'Rotina sugerida', 'Precisa de contato', 'Estagnados'] as $trecho) {
            $this->assertStringContainsString($trecho, $html);
        }

        Livewire::test(ListInteressados::class)
            ->mountAction('ajuda')
            ->assertHasNoErrors();
    }

    public function test_bulk_action_editar_lote_atualiza_campos_selecionados_e_recalcula_score(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $lead1 = $this->criarInteressado(['temperatura' => 'frio', 'motivo_perda' => 'Preço inicial']);
        $lead2 = $this->criarInteressado(['temperatura' => 'frio', 'motivo_perda' => 'Distância inicial']);
        $lead3 = $this->criarInteressado(['temperatura' => 'frio', 'motivo_perda' => 'Intocado']);

        $novoStatus = StatusInteressado::create([
            'nome' => 'Qualificado',
            'cor' => 'info',
            'ordem' => 10,
            'is_final' => false,
            'is_ganho' => false,
        ]);
        // Só usuários ativos com permissão de atender leads podem ser consultor (ver `User::consultoresCrm()`).
        $novoConsultor = User::factory()->create(['activated_at' => now()->subDay()]);
        $novoConsultor->givePermissionTo(Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']));
        $novaOrigem = OrigemInteressado::create(['nome' => 'Indicação de Aluno']);

        Livewire::actingAs($admin)
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', [$lead1, $lead2], data: [
                'status_interessado_id' => $novoStatus->id,
                'usuario_id' => $novoConsultor->id,
                'temperatura' => 'quente',
                'origem_interessado_id' => $novaOrigem->id,
                'faixa_distancia_escola' => 'ate_2km',
                'meio_transporte' => 'carro_proprio',
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        $lead1->refresh();
        $lead2->refresh();
        $lead3->refresh();

        $this->assertEquals($novoStatus->id, $lead1->status_interessado_id);
        $this->assertEquals($novoConsultor->id, $lead1->usuario_id);
        $this->assertEquals('quente', $lead1->temperatura);
        $this->assertEquals($novaOrigem->id, $lead1->origem_interessado_id);
        $this->assertEquals('ate_2km', $lead1->faixa_distancia_escola);
        $this->assertEquals('carro_proprio', $lead1->meio_transporte);
        $this->assertEquals('Preço inicial', $lead1->motivo_perda); // preservado

        $this->assertEquals($novoStatus->id, $lead2->status_interessado_id);
        $this->assertEquals($novoConsultor->id, $lead2->usuario_id);
        $this->assertEquals('quente', $lead2->temperatura);
        $this->assertEquals($novaOrigem->id, $lead2->origem_interessado_id);
        $this->assertEquals('ate_2km', $lead2->faixa_distancia_escola);
        $this->assertEquals('carro_proprio', $lead2->meio_transporte);
        $this->assertEquals('Distância inicial', $lead2->motivo_perda); // preservado

        // Lead 3 não selecionado não foi alterado
        $this->assertEquals('frio', $lead3->temperatura);
        $this->assertEquals('Intocado', $lead3->motivo_perda);
    }

    public function test_bulk_action_editar_lote_com_campos_vazios_emite_aviso_e_nao_altera_dados(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $lead1 = $this->criarInteressado(['temperatura' => 'frio']);
        $lead2 = $this->criarInteressado(['temperatura' => 'morno']);

        Livewire::actingAs($admin)
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', [$lead1, $lead2], data: [
                'status_interessado_id' => null,
                'usuario_id' => null,
                'temperatura' => null,
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertEquals('frio', $lead1->fresh()->temperatura);
        $this->assertEquals('morno', $lead2->fresh()->temperatura);
    }
}
