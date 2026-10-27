<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusListaEspera;
use App\Filament\Pages\SimuladorProximoAno;
use App\Models\CenarioProximoAno;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\PeriodoRematriculaPreco;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Pendencias\Fontes\ProjecaoDoProximoAno;
use App\Services\Relatorios\Fontes\SimulacaoProximoAno;
use App\Services\SimuladorProximoAnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Simulador do próximo ano letivo (Onda 76), telas: a página, o "e se", os cenários salvos, a fila da Minha Mesa e a fonte dos
 * relatórios.
 *
 * "Hoje" é segunda-feira, 26/10/2026. O 7º ano de 2027 tem uma turma de 4 vagas; 3 rematrículas confirmadas, 2 em andamento e 3 na
 * lista de espera: a projeção é de 4,8 alunos para 4 vagas (acima da capacidade).
 */
class SimuladorProximoAnoTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $diretor;

    private User $outro;

    private User $semAcesso;

    private PeriodoRematricula $campanha;

    private Serie $setimo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['diretor', 'outro', 'semacesso'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['diretor', 'outro'] as $papel) {
            Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => 'View:SimuladorProximoAno', 'guard_name' => 'web']));
        }

        Role::findByName('diretor')->givePermissionTo(Permission::firstOrCreate(['name' => 'Gerenciar:SimuladorProximoAno', 'guard_name' => 'web']));

        $this->diretor = $this->usuario('diretor');
        $this->outro = $this->usuario('outro');
        $this->semAcesso = $this->usuario('semacesso');

        $unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $sexto = Serie::create(['curso_id' => $curso->id, 'nome' => '6º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->setimo = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);

        $origem = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $destino = PeriodoLetivo::create(['nome' => '2027', 'ano' => 2027, 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $this->campanha = PeriodoRematricula::create(['nome' => 'Rematrícula 2027', 'periodo_letivo_origem_id' => $origem->id, 'periodo_letivo_destino_id' => $destino->id, 'data_inicio' => '2026-10-01', 'data_fim' => '2026-11-15', 'is_ativo' => true]);
        PeriodoRematriculaPreco::create(['periodo_rematricula_id' => $this->campanha->id, 'serie_id' => $this->setimo->id, 'valor_total' => 14400]);

        $turmaOrigem = Turma::create(['nome' => '6º Ano 2026', 'periodo_letivo_id' => $origem->id, 'serie_id' => $sexto->id]);

        foreach (range(1, 5) as $n) {
            $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => "Aluno {$n}"])->id, 'turma_id' => $turmaOrigem->id, 'situacao' => 'ativa']);
            Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
            Rematricula::create(['periodo_rematricula_id' => $this->campanha->id, 'matricula_origem_id' => $matricula->id, 'solicitante_user_id' => $this->diretor->id, 'serie_destino_id' => $this->setimo->id, 'status' => $n <= 3 ? 'confirmada' : 'dados_confirmados']);
        }

        $turma = Turma::create(['nome' => '7º Ano 2027', 'periodo_letivo_id' => $destino->id, 'serie_id' => $this->setimo->id, 'vagas_maximas' => 4, 'status' => 'planejada']);

        foreach (range(1, 3) as $n) {
            ListaEsperaMatricula::create(['turma_id' => $turma->id, 'periodo_letivo_id' => $destino->id, 'pessoa_id' => Pessoa::create(['nome' => "Espera {$n}"])->id, 'status' => StatusListaEspera::Aguardando]);
        }
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function tela(?User $usuario = null)
    {
        return Livewire::actingAs($this->como($usuario ?? $this->diretor))->test(SimuladorProximoAno::class);
    }

    // --- Página ------------------------------------------------------------------------------------------------------

    public function test_a_pagina_exige_permissao(): void
    {
        $this->actingAs($this->como($this->semAcesso))->get(SimuladorProximoAno::getUrl())->assertForbidden();
        $this->actingAs($this->como($this->diretor))->get(SimuladorProximoAno::getUrl())->assertOk();
    }

    public function test_abre_na_campanha_aberta_com_os_parametros_padrao_e_mostra_a_projecao(): void
    {
        $this->tela()
            ->assertSet('periodoId', $this->campanha->id)
            ->assertSet('taxaAndamento', 90.0)
            ->assertSet('corteRisco', 60)
            ->assertSee('Isto é uma projeção com os dados de hoje, não um compromisso.')
            ->assertSee('Ensino Fundamental — 7º Ano')
            ->assertSee('Acima da capacidade')
            ->assertSee('espera maior que as vagas que sobram')
            ->assertSee('4,8')
            ->assertSee('Sem série de destino definida');
    }

    public function test_sem_campanha_a_pagina_avisa(): void
    {
        PeriodoRematricula::query()->delete();

        $this->tela()->assertSet('periodoId', null)->assertSee('Cadastre uma campanha de rematrícula para simular o próximo ano.');
    }

    public function test_mexer_nas_taxas_recalcula_a_projecao(): void
    {
        $this->tela()->set('taxaAndamento', 100)->assertSee('5,0');
    }

    public function test_turmas_e_capacidade_resolvem_o_excesso(): void
    {
        $this->tela()
            ->set("turmas.{$this->setimo->id}", 2)
            ->set("capacidade.{$this->setimo->id}", 30)
            ->assertSee('Dentro do esperado')
            ->assertDontSee('Acima da capacidade');
    }

    public function test_abrir_turma_em_outra_serie_cria_a_linha(): void
    {
        $tela = $this->tela();

        $sexto = Serie::query()->where('nome', '6º Ano')->firstOrFail();

        $tela->assertSee('Abrir turma em outra série')
            ->set('novaSerie', $sexto->id)
            ->call('abrirTurma')
            ->assertSet("turmas.{$sexto->id}", 1)
            ->assertSet('novaSerie', null)
            ->assertSee('Ensino Fundamental — 6º Ano');
    }

    public function test_voltar_aos_padroes_limpa_o_e_se(): void
    {
        $this->tela()
            ->set('taxaAndamento', 40)
            ->set("turmas.{$this->setimo->id}", 3)
            ->call('restaurar')
            ->assertSet('taxaAndamento', 90.0)
            ->assertSet('turmas', [])
            ->assertSet('capacidade', []);
    }

    public function test_usar_as_taxas_da_campanha_anterior(): void
    {
        $antiga = PeriodoLetivo::create(['nome' => '2025', 'ano' => 2025, 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-15']);
        $anterior = PeriodoRematricula::create(['nome' => 'Rematrícula 2026', 'periodo_letivo_origem_id' => $antiga->id, 'periodo_letivo_destino_id' => $this->campanha->periodo_letivo_origem_id, 'data_inicio' => '2025-10-01', 'data_fim' => '2025-11-15', 'is_ativo' => false]);
        $turma = Turma::create(['nome' => 'Turma 2025', 'periodo_letivo_id' => $antiga->id, 'serie_id' => $this->setimo->id]);

        foreach (range(1, 4) as $n) {
            $m = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => "Antigo {$n}"])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
            Rematricula::create(['periodo_rematricula_id' => $anterior->id, 'matricula_origem_id' => $m->id, 'solicitante_user_id' => $this->diretor->id, 'status' => $n <= 3 ? 'confirmada' : 'iniciada']);
        }

        // 3 de 4 iniciadas terminaram (75%); 3 de 4 elegíveis renovaram (75%).
        $this->tela()
            ->assertSee('Usar taxas de Rematrícula 2026')
            ->call('usarSugestao')
            ->assertSet('taxaAndamento', 75.0)
            ->assertSet('taxaNaoIniciadas', 75.0);
    }

    // --- Cenários ----------------------------------------------------------------------------------------------------

    public function test_salvar_cenario_pela_tela_guarda_os_parametros_da_tela(): void
    {
        $this->tela()
            ->set('taxaAndamento', 60)
            ->set('reajuste', 8)
            ->callAction('salvar', ['nome' => 'Conservador'])
            ->assertNotified('Cenário salvo');

        $cenario = CenarioProximoAno::query()->firstOrFail();
        $this->assertSame('Conservador', $cenario->nome);
        $this->assertEquals(60.0, $cenario->parametros['taxa_andamento']);
        $this->assertEquals(8.0, $cenario->parametros['reajuste_percentual']);
        $this->assertSame($this->diretor->id, $cenario->criado_por_user_id);
    }

    public function test_cenario_sem_nome_nao_e_salvo(): void
    {
        $this->tela()->callAction('salvar', ['nome' => ''])->assertHasActionErrors(['nome' => 'required']);

        $this->assertSame(0, CenarioProximoAno::query()->count());
    }

    public function test_lista_os_cenarios_salvos_e_carrega_os_parametros_de_volta(): void
    {
        app(SimuladorProximoAnoService::class)->salvar($this->campanha, 'Otimista', ['taxa_andamento' => 100, 'taxa_nao_iniciadas' => 95, 'turmas' => [$this->setimo->id => 2], 'capacidade' => [$this->setimo->id => 30]], $this->diretor);
        $id = CenarioProximoAno::query()->firstOrFail()->id;

        $this->tela()
            ->assertSee('Cenários salvos')
            ->assertSee('Otimista')
            ->call('carregarCenario', $id)
            ->assertSet('taxaAndamento', 100.0)
            ->assertSet('taxaNaoIniciadas', 95.0)
            ->assertSet("turmas.{$this->setimo->id}", 2);
    }

    public function test_so_o_dono_ou_quem_gerencia_exclui_o_cenario(): void
    {
        $service = app(SimuladorProximoAnoService::class);
        $doDiretor = $service->salvar($this->campanha, 'Do diretor', [], $this->diretor);
        $doOutro = $service->salvar($this->campanha, 'Do outro', [], $this->outro);

        $this->tela($this->outro)->call('excluirCenario', $doDiretor->id)->assertForbidden();
        $this->assertNotNull($doDiretor->fresh());

        $this->tela($this->outro)->call('excluirCenario', $doOutro->id)->assertNotified('Cenário excluído');
        $this->assertNull($doOutro->fresh());

        // Quem tem "Gerenciar" exclui o de qualquer um.
        $outroDoOutro = $service->salvar($this->campanha, 'Outro do outro', [], $this->outro);
        $this->tela($this->diretor)->call('excluirCenario', $outroDoOutro->id)->assertNotified('Cenário excluído');
        $this->assertNull($outroDoOutro->fresh());
    }

    // --- Minha Mesa e relatórios -------------------------------------------------------------------------------------

    public function test_fila_da_mesa_lista_o_problema_da_serie_e_a_urgencia_vem_do_fim_da_campanha(): void
    {
        $fila = new ProjecaoDoProximoAno;

        $this->assertSame('View:SimuladorProximoAno', $fila->permissao());
        $this->assertSame(1, $fila->contar($this->diretor));
        $this->assertSame(0, $fila->atrasados($this->diretor), 'a campanha termina em 20 dias');

        $item = $fila->itens($this->diretor, 5)[0];
        $this->assertSame('Ensino Fundamental — 7º Ano · Acima da capacidade', $item->titulo);
        $this->assertStringContainsString('4,8 aluno(s) para 4 vaga(s)', $item->detalhe);

        $this->travel(8)->days();
        $this->assertSame(1, (new ProjecaoDoProximoAno)->atrasados($this->diretor));
    }

    public function test_fila_da_mesa_fica_vazia_sem_campanha_ou_sem_problema(): void
    {
        Turma::query()->where('nome', '7º Ano 2027')->update(['vagas_maximas' => 30]);
        ListaEsperaMatricula::query()->delete();

        $this->assertSame(0, (new ProjecaoDoProximoAno)->contar($this->diretor));

        PeriodoRematricula::query()->delete();
        $this->assertSame(0, (new ProjecaoDoProximoAno)->contar($this->diretor));
    }

    public function test_fonte_de_relatorio_entrega_a_projecao_de_hoje_e_a_de_um_cenario_salvo(): void
    {
        $fonte = new SimulacaoProximoAno;

        $hoje = $fonte->linhas([], 100);
        $this->assertSame('View:SimuladorProximoAno', $fonte->permissao());
        $this->assertCount(1, $hoje);
        $this->assertSame('Ensino Fundamental — 7º Ano', $hoje[0]['serie']);
        $this->assertEquals(4.8, $hoje[0]['projecao']);
        $this->assertSame('Acima da capacidade', $hoje[0]['situacao']);

        $cenario = app(SimuladorProximoAnoService::class)->salvar($this->campanha, 'Otimista', ['taxa_andamento' => 100], $this->diretor);
        $salvo = $fonte->linhas(['cenario' => (string) $cenario->id], 100);
        $this->assertEquals(5.0, $salvo[0]['projecao']);

        $this->assertArrayHasKey((string) $cenario->id, $fonte->filtros()['cenario']->opcoes);
    }
}
