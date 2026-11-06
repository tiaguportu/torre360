<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracaoRoteiroQualificacao;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Jobs\ReavaliarQualificacaoJob;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\QualificacaoRoteiroConfiguracao;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Qualificacao\QualificacaoService;
use App\Support\Qualificacao\CampoQualificacao;
use App\Support\Qualificacao\Roteiro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Roteiro de qualificação editável (SDR, Lote 2): a secretaria muda o texto da pergunta, a ordem, o que é necessário para
 * a visita e quais campos estão ativos, sem mexer em código. A lista de campos continua fechada.
 */
class QualificacaoRoteiroConfiguracaoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private QualificacaoService $servico;

    private StatusInteressado $novo;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servico = app(QualificacaoService::class);
        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'View:ConfiguracaoRoteiroQualificacao', 'guard_name' => 'web']));

        $usuario = User::factory()->create(['activated_at' => now()]);
        $usuario->assignRole('super_admin');

        return $usuario;
    }

    private function lead(?User $consultor = null): Interessado
    {
        return Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => ($consultor ?? User::factory()->create())->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    private function serie(): Serie
    {
        $curso = Curso::first() ?? Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => Unidade::create(['nome' => 'Unidade Sede'])->id,
        ]);

        return Serie::create(['nome' => '2º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
    }

    /**
     * Responde todo o gate de fábrica, exceto os campos em `$menos`.
     *
     * @param  list<string>  $menos
     */
    private function qualificar(Interessado $lead, array $menos = []): void
    {
        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Lucas',
            'serie_id' => $this->serie()->id,
            'data_nascimento' => '2019-05-10',
            'turno_preferencia' => 'Manhã',
            'situacao_atual' => 'primeira_escola',
        ]);

        foreach (['bairro' => 'Centro', 'motivo_busca' => 'primeira_escola', 'decisor_tipo' => 'sozinho', 'decisor_vai_visita' => 'sim', 'prazo_decisao' => 'este_mes'] as $chave => $valor) {
            if (! in_array($chave, $menos, true)) {
                $this->servico->registrar($lead->fresh(), $chave, $valor, InteressadoQualificacao::FONTE_CONSULTOR);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $campos
     */
    private function personalizar(array $campos): void
    {
        QualificacaoRoteiroConfiguracao::create(['valores' => ['campos' => $campos]]);
    }

    /**
     * Itens do formulário da página, na ordem de fábrica (ou na do roteiro atual), para os testes mexerem neles.
     *
     * @return list<array{chave: string, ativo: bool, gate: bool, pergunta: string}>
     */
    private function itens(): array
    {
        return array_map(fn (CampoQualificacao $campo): array => [
            'chave' => $campo->chave,
            'ativo' => $campo->ativo,
            'gate' => $campo->gate,
            'pergunta' => $campo->pergunta,
        ], Roteiro::todos());
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return list<array<string, mixed>>
     */
    private function comItem(array $itens, string $chave, array $mudancas): array
    {
        return array_map(fn (array $item): array => $item['chave'] === $chave ? array_replace($item, $mudancas) : $item, $itens);
    }

    // ─── O roteiro de fábrica e a personalização ─────────────────

    public function test_sem_personalizacao_o_roteiro_tem_a_ordem_e_o_gate_de_fabrica(): void
    {
        $campos = Roteiro::campos();

        $this->assertCount(12, $campos);
        $this->assertSame('serie_id', $campos[0]->chave);
        $this->assertSame('faixa_mensalidade', $campos[11]->chave);
        $this->assertSame([10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120], array_map(fn ($c) => $c->ordem, $campos));
        $this->assertCount(9, Roteiro::gate());
        $this->assertTrue(Roteiro::estaAtivo('bairro'));
        $this->assertFalse(Roteiro::estaAtivo('renda_exata'), 'Campo fora da lista fechada não existe.');
    }

    public function test_personalizacao_muda_pergunta_ordem_gate_e_ativo(): void
    {
        $this->personalizar([
            'bairro' => ['pergunta' => '  Em que bairro vocês moram?  ', 'ordem' => 5, 'gate' => false, 'ativo' => true],
            'escola_atual' => ['ativo' => false],
            'faixa_mensalidade' => ['gate' => true],
            'decisor_tipo' => ['ordem' => 'abc', 'pergunta' => 123],
        ]);

        $campos = collect(Roteiro::campos())->keyBy('chave');

        $this->assertSame('bairro', Roteiro::campos()[0]->chave, 'Ordem 5 vai para a frente de todos.');
        $this->assertSame('Em que bairro vocês moram?', $campos['bairro']->pergunta);
        $this->assertFalse($campos['bairro']->gate);

        $this->assertFalse($campos->has('escola_atual'), 'Campo desligado sai do roteiro...');
        $this->assertNotNull(Roteiro::campo('escola_atual'), '...mas continua existindo para quem consulta pela chave.');
        $this->assertFalse(Roteiro::estaAtivo('escola_atual'));

        $this->assertFalse($campos['faixa_mensalidade']->gate, 'A mensalidade nunca entra no gate.');
        $this->assertSame(90, $campos['decisor_tipo']->ordem, 'Ordem inválida cai no padrão.');
        $this->assertSame('A decisão é sua ou vocês conversam com mais alguém da família?', $campos['decisor_tipo']->pergunta, 'Pergunta inválida cai no padrão.');
    }

    public function test_personalizacao_vale_na_hora_e_apagar_volta_ao_padrao(): void
    {
        $padrao = Roteiro::campo('prazo_decisao')->pergunta;

        $this->personalizar(['prazo_decisao' => ['pergunta' => 'Quando vocês pretendem matricular?']]);
        $this->assertSame('Quando vocês pretendem matricular?', Roteiro::campo('prazo_decisao')->pergunta);

        QualificacaoRoteiroConfiguracao::query()->delete();
        QualificacaoRoteiroConfiguracao::limparCache();
        $this->assertSame($padrao, Roteiro::campo('prazo_decisao')->pergunta);
    }

    public function test_a_linha_mais_recente_vale_e_a_pergunta_tem_tamanho_maximo(): void
    {
        $this->personalizar(['bairro' => ['pergunta' => 'Primeira versão.']]);
        $this->personalizar(['bairro' => ['pergunta' => str_repeat('a', 500)]]);

        $this->assertSame(Roteiro::MAX_PERGUNTA, mb_strlen(Roteiro::campo('bairro')->pergunta));
    }

    // ─── Efeito no serviço ───────────────────────────────────────

    public function test_campo_desligado_sai_do_painel_das_perguntas_e_do_gate(): void
    {
        $this->personalizar(['situacao_atual' => ['ativo' => false]]);

        $lead = $this->lead();
        $this->qualificar($lead);

        $this->assertArrayNotHasKey('situacao_atual', $this->servico->estados($lead->fresh()));
        $this->assertSame(8, $this->servico->avaliar($lead->fresh())['total']);

        $chaves = collect($this->servico->painel($lead->fresh())['dimensoes'])->flatMap(fn ($d) => collect($d['campos'])->pluck('chave'));
        $this->assertNotContains('situacao_atual', $chaves->all());
    }

    public function test_mudar_o_gate_muda_o_resultado_depois_de_reavaliar(): void
    {
        $lead = $this->lead();
        $this->qualificar($lead, menos: ['bairro']);
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $lead->qualificacao()->first()->resultado);

        $this->personalizar(['bairro' => ['gate' => false]]);

        // O resultado guardado só muda quando alguém reavalia (a página dispara isso em segundo plano).
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $lead->qualificacao()->first()->resultado);

        $this->assertSame(1, $this->servico->reavaliarTodos());
        $this->assertSame(InteressadoQualificacao::RESULTADO_QUALIFICADO, $lead->qualificacao()->first()->resultado);
    }

    public function test_sem_nenhum_campo_necessario_ninguem_se_qualifica(): void
    {
        $semGate = collect(Roteiro::padroes())->mapWithKeys(fn (CampoQualificacao $c) => [$c->chave => ['gate' => false]])->all();
        $this->personalizar($semGate);

        $lead = $this->lead();
        $this->qualificar($lead);

        $avaliacao = $this->servico->avaliar($lead->fresh());

        $this->assertSame(0, $avaliacao['total']);
        $this->assertFalse($avaliacao['gate_ok']);
        $this->assertNotSame(InteressadoQualificacao::RESULTADO_QUALIFICADO, $avaliacao['resultado']);
    }

    public function test_a_ordem_configurada_define_a_proxima_pergunta(): void
    {
        $this->personalizar([
            'prazo_decisao' => ['ordem' => 1],
            'decisor_tipo' => ['ordem' => 2],
        ]);

        $lead = $this->lead();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);

        $chaves = array_map(fn (CampoQualificacao $c) => $c->chave, $this->servico->proximasPerguntas($lead->fresh()));

        $this->assertSame(['prazo_decisao', 'decisor_tipo'], $chaves);
    }

    // ─── A página ────────────────────────────────────────────────

    public function test_pagina_carrega_para_quem_tem_permissao_e_nega_para_quem_nao_tem(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/crm/roteiro-qualificacao')
            ->assertOk()
            ->assertSee('Roteiro de qualificação do lead');

        $semPermissao = User::factory()->create(['activated_at' => now()]);

        $this->actingAs($semPermissao)
            ->get('/admin/crm/roteiro-qualificacao')
            ->assertForbidden();
    }

    public function test_formulario_abre_com_os_campos_na_ordem_atual(): void
    {
        $this->personalizar(['prazo_decisao' => ['ordem' => 1, 'pergunta' => 'Quando vocês decidem?']]);

        $itens = Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->assertOk()
            ->get('data.campos');

        $primeiro = array_values($itens)[0];

        $this->assertCount(12, $itens);
        $this->assertSame('prazo_decisao', $primeiro['chave']);
        $this->assertSame('Quando vocês decidem?', $primeiro['pergunta']);
    }

    public function test_salvar_grava_posicao_pergunta_gate_e_ativo(): void
    {
        Queue::fake();

        $itens = $this->itens();
        // Move "prazo" para o início, desliga "escola atual", tira "bairro" do gate e muda um texto.
        $prazo = collect($itens)->firstWhere('chave', 'prazo_decisao');
        $itens = array_values(array_filter($itens, fn (array $item) => $item['chave'] !== 'prazo_decisao'));
        array_unshift($itens, array_replace($prazo, ['pergunta' => 'Para quando precisam da vaga?']));
        $itens = $this->comItem($itens, 'escola_atual', ['ativo' => false]);
        $itens = $this->comItem($itens, 'bairro', ['gate' => false]);

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->fillForm(['campos' => $itens], 'content')
            ->call('salvar')
            ->assertNotified('Roteiro salvo');

        $campos = QualificacaoRoteiroConfiguracao::sole()->valores['campos'];

        $this->assertSame(10, $campos['prazo_decisao']['ordem']);
        $this->assertSame('Para quando precisam da vaga?', $campos['prazo_decisao']['pergunta']);
        $this->assertFalse($campos['escola_atual']['ativo']);
        $this->assertFalse($campos['bairro']['gate']);
        $this->assertTrue($campos['motivo_busca']['gate']);

        // Vale na hora para o roteiro.
        $this->assertSame('prazo_decisao', Roteiro::campos()[0]->chave);
        $this->assertFalse(Roteiro::estaAtivo('escola_atual'));
    }

    public function test_salvar_exige_ao_menos_um_campo_necessario_para_a_visita(): void
    {
        $itens = array_map(fn (array $item) => array_replace($item, ['gate' => false]), $this->itens());

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->fillForm(['campos' => $itens], 'content')
            ->call('salvar')
            ->assertNotified('Marque ao menos um campo');

        $this->assertSame(0, QualificacaoRoteiroConfiguracao::count());
    }

    public function test_um_campo_desligado_nao_conta_como_necessario(): void
    {
        // Só sobra "bairro" como necessário, e ele está desligado: ninguém poderia se qualificar.
        $itens = array_map(fn (array $item) => array_replace($item, ['gate' => $item['chave'] === 'bairro', 'ativo' => $item['chave'] !== 'bairro']), $this->itens());

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->fillForm(['campos' => $itens], 'content')
            ->call('salvar')
            ->assertNotified('Marque ao menos um campo');

        $this->assertSame(0, QualificacaoRoteiroConfiguracao::count());
    }

    public function test_a_mensalidade_nunca_entra_no_gate_mesmo_que_o_formulario_diga(): void
    {
        $itens = $this->comItem($this->itens(), 'faixa_mensalidade', ['gate' => true]);

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->fillForm(['campos' => $itens], 'content')
            ->call('salvar')
            ->assertNotified('Roteiro salvo');

        $this->assertFalse(QualificacaoRoteiroConfiguracao::sole()->valores['campos']['faixa_mensalidade']['gate']);
    }

    public function test_campo_desconhecido_no_formulario_e_ignorado(): void
    {
        $itens = $this->itens();
        $itens[] = ['chave' => 'renda_exata', 'ativo' => true, 'gate' => true, 'pergunta' => 'Qual a renda da família?'];

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->fillForm(['campos' => $itens], 'content')
            ->call('salvar')
            ->assertNotified('Roteiro salvo');

        $campos = QualificacaoRoteiroConfiguracao::sole()->valores['campos'];

        $this->assertArrayNotHasKey('renda_exata', $campos);
        $this->assertCount(12, $campos);
    }

    public function test_reavalia_os_leads_so_quando_o_que_e_necessario_muda(): void
    {
        Queue::fake();
        $pagina = Livewire::actingAs($this->admin())->test(ConfiguracaoRoteiroQualificacao::class);

        // Só o texto de uma pergunta: o resultado dos leads não muda, então nada é reprocessado.
        $pagina->fillForm(['campos' => $this->comItem($this->itens(), 'bairro', ['pergunta' => 'Qual o seu bairro?'])], 'content')
            ->call('salvar')
            ->assertNotified('Roteiro salvo');
        Queue::assertNotPushed(ReavaliarQualificacaoJob::class);

        // Mudou o que é necessário para a visita: reavalia em segundo plano.
        $pagina->fillForm(['campos' => $this->comItem($this->itens(), 'bairro', ['gate' => false])], 'content')
            ->call('salvar');
        Queue::assertPushed(ReavaliarQualificacaoJob::class, 1);
    }

    public function test_restaurar_padrao_apaga_a_personalizacao_e_reavalia(): void
    {
        Queue::fake();
        $this->personalizar(['bairro' => ['gate' => false, 'pergunta' => 'Outro texto.']]);

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoRoteiroQualificacao::class)
            ->call('restaurarPadrao')
            ->assertNotified('Roteiro padrão restaurado');

        $this->assertSame(0, QualificacaoRoteiroConfiguracao::count());
        $this->assertTrue(Roteiro::campo('bairro')->gate);
        Queue::assertPushed(ReavaliarQualificacaoJob::class, 1);
    }

    public function test_o_job_reavalia_os_leads_em_andamento(): void
    {
        $lead = $this->lead();
        $this->qualificar($lead, menos: ['bairro']);
        $this->personalizar(['bairro' => ['gate' => false]]);

        (new ReavaliarQualificacaoJob)->handle($this->servico);

        $this->assertSame(InteressadoQualificacao::RESULTADO_QUALIFICADO, $lead->qualificacao()->first()->resultado);
    }

    public function test_ficha_esconde_o_campo_desligado(): void
    {
        $lead = $this->lead();

        Livewire::actingAs($this->admin())
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertSee('Quem decide vai à visita?');

        $this->personalizar(['decisor_vai_visita' => ['ativo' => false]]);

        Livewire::actingAs($this->admin())
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertDontSee('Quem decide vai à visita?')
            ->assertSee('Quem decide');
    }

    public function test_a_migration_de_permissao_libera_a_pagina_para_admin(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        (include base_path('database/migrations/2026_11_05_140100_grant_configuracao_roteiro_qualificacao_permissions.php'))->up();

        $this->assertTrue($admin->fresh()->hasPermissionTo('View:ConfiguracaoRoteiroQualificacao'));
    }
}
