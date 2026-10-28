<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\CreateInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class InteressadoFichaOrganizadaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $admin;

    private StatusInteressado $statusNovo;

    private OrigemInteressado $origem;

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

        $this->origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
    }

    private function criarLead(?User $consultor = null, array $atributos = []): Interessado
    {
        $pessoa = Pessoa::factory()->create(['telefone' => '(11) 98888-7777', 'email' => 'lead@exemplo.com']);

        return Interessado::create(array_merge([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $consultor?->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->statusNovo->id,
        ], $atributos));
    }

    private function usuarioComPermissoes(array $permissoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $usuario = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);

        foreach ($permissoes as $permissao) {
            $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        return $usuario;
    }

    // ─── 1. Cabeçalho: agrupamento, visibilidade e chamabilidade ───────

    public function test_acoes_do_cabecalho_existem_e_sao_chamaveis_pelo_nome(): void
    {
        $lead = $this->criarLead($this->admin);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertActionExists('battlecards')
            ->assertActionExists('dossieIa')
            ->assertActionExists('copilotoIa')
            ->assertActionExists('resumoConversaIa')
            ->assertActionExists('termometroVagas')
            ->assertActionExists('mesclarLead')
            ->assertActionExists('ajuda')
            ->mountAction('ajuda')
            ->assertHasNoErrors()
            ->mountAction('termometroVagas')
            ->assertHasNoErrors();
    }

    public function test_grupo_apoio_ao_atendimento_nao_aparece_para_quem_nao_tem_permissao_dos_filhos(): void
    {
        $leitor = $this->usuarioComPermissoes(['ViewAny:Interessado', 'View:Interessado']);
        $lead = $this->criarLead(null);

        // O usuário leitor não tem Update:Interessado nem Create:HistoricoContato
        Livewire::actingAs($leitor)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertDontSee('Apoio ao atendimento');

        // Um operador com Update:Interessado vê o grupo
        $operador = $this->usuarioComPermissoes(['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado']);
        Livewire::actingAs($operador)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertSee('Apoio ao atendimento');
    }

    public function test_assumir_lead_continua_solto_e_so_aparece_sem_consultor(): void
    {
        $operador = $this->usuarioComPermissoes(['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado']);

        // Com consultor atribuído (o próprio operador): ação assumirLead oculta
        $leadComConsultor = $this->criarLead($operador);
        Livewire::actingAs($operador)
            ->test(EditInteressado::class, ['record' => $leadComConsultor->id])
            ->assertActionHidden('assumirLead');

        // Sem consultor atribuído: ação assumirLead visível
        $leadSemConsultor = $this->criarLead(null);
        Livewire::actingAs($operador)
            ->test(EditInteressado::class, ['record' => $leadSemConsultor->id])
            ->assertActionVisible('assumirLead');
    }

    // ─── 2. Formulário: seções e estados de recolhimento ───────────────

    public function test_formulario_ordem_e_estado_das_secoes_na_edicao_e_na_criacao(): void
    {
        $lead = $this->criarLead($this->admin, ['redes_sociais' => []]);

        $testable = Livewire::actingAs($this->admin)->test(EditInteressado::class, ['record' => $lead->id]);
        $pagina = $testable->instance();
        $form = $pagina->getSchema('form');

        /** @var Tabs $tabs */
        $tabs = collect($form->getComponents())->first(fn ($c) => $c instanceof Tabs);
        $tabNegocio = collect($tabs->getChildSchema()->getComponents())->first(fn ($t) => $t->getLabel() === 'Dados do Negócio');
        $secoes = collect($tabNegocio->getChildSchema()->getComponents())->filter(fn ($c) => $c instanceof Section)->values();

        $titulos = $secoes->map(fn (Section $s) => $s->getHeading())->all();
        $this->assertEquals([
            'Resumo do Lead',
            'Acompanhamento',
            'Contato e origem',
            'Qualificação',
            'Redes sociais',
            'Observações',
        ], $titulos);

        // Seção Acompanhamento vem antes de Contato e origem
        $idxAcomp = array_search('Acompanhamento', $titulos, true);
        $idxContato = array_search('Contato e origem', $titulos, true);
        $this->assertLessThan($idxContato, $idxAcomp);

        // Verificar estado recolhido/aberto na edição de lead sem redes sociais
        $secaoQualif = $secoes->first(fn (Section $s) => $s->getHeading() === 'Qualificação');
        $secaoRedes = $secoes->first(fn (Section $s) => $s->getHeading() === 'Redes sociais');
        $secaoAcomp = $secoes->first(fn (Section $s) => $s->getHeading() === 'Acompanhamento');
        $secaoContato = $secoes->first(fn (Section $s) => $s->getHeading() === 'Contato e origem');
        $secaoObs = $secoes->first(fn (Section $s) => $s->getHeading() === 'Observações');

        $this->assertTrue($secaoQualif->isCollapsed());
        $this->assertTrue($secaoRedes->isCollapsed());
        $this->assertFalse($secaoAcomp->isCollapsed());
        $this->assertFalse($secaoContato->isCollapsed());
        $this->assertFalse($secaoObs->isCollapsed());

        // Edição com redes sociais preenchidas: Redes sociais aberta
        $leadComRedes = $this->criarLead($this->admin, [
            'redes_sociais' => [['rede' => 'instagram', 'url' => 'https://instagram.com/teste']],
        ]);
        $testableComRedes = Livewire::actingAs($this->admin)->test(EditInteressado::class, ['record' => $leadComRedes->id]);
        $tabsComRedes = collect($testableComRedes->instance()->getSchema('form')->getComponents())->first(fn ($c) => $c instanceof Tabs);
        $tabNegocioComRedes = collect($tabsComRedes->getChildSchema()->getComponents())->first(fn ($t) => $t->getLabel() === 'Dados do Negócio');
        $secaoRedesComRedes = collect($tabNegocioComRedes->getChildSchema()->getComponents())->first(fn ($c) => $c instanceof Section && $c->getHeading() === 'Redes sociais');
        $this->assertFalse($secaoRedesComRedes->isCollapsed());

        // Na criação (CreateInteressado): todas as seções abertas
        $testableCreate = Livewire::actingAs($this->admin)->test(CreateInteressado::class);
        $tabsCreate = collect($testableCreate->instance()->getSchema('form')->getComponents())->first(fn ($c) => $c instanceof Tabs);
        $tabNegocioCreate = collect($tabsCreate->getChildSchema()->getComponents())->first(fn ($t) => $t->getLabel() === 'Dados do Negócio');
        $secoesCreate = collect($tabNegocioCreate->getChildSchema()->getComponents())->filter(fn ($c) => $c instanceof Section)->values();

        foreach ($secoesCreate as $secao) {
            $this->assertFalse($secao->isCollapsed(), "A seção {$secao->getHeading()} deve estar aberta na criação.");
        }
    }

    public function test_campos_obrigatorios_ficam_em_secoes_abertas(): void
    {
        $lead = $this->criarLead($this->admin);

        $testable = Livewire::actingAs($this->admin)->test(EditInteressado::class, ['record' => $lead->id]);
        $form = $testable->instance()->getSchema('form');

        /** @var Tabs $tabs */
        $tabs = collect($form->getComponents())->first(fn ($c) => $c instanceof Tabs);
        $tabNegocio = collect($tabs->getChildSchema()->getComponents())->first(fn ($t) => $t->getLabel() === 'Dados do Negócio');
        $secoes = collect($tabNegocio->getChildSchema()->getComponents())->filter(fn ($c) => $c instanceof Section)->values();

        $secaoAcomp = $secoes->first(fn (Section $s) => $s->getHeading() === 'Acompanhamento');
        $secaoContato = $secoes->first(fn (Section $s) => $s->getHeading() === 'Contato e origem');

        $camposAcomp = collect($secaoAcomp->getChildSchema()->getComponents())->map(fn ($c) => $c->getName())->all();
        $camposContato = collect($secaoContato->getChildSchema()->getComponents())->map(fn ($c) => $c->getName())->all();

        // status_interessado_id e usuario_id estão em Acompanhamento (aberta)
        $this->assertContains('status_interessado_id', $camposAcomp);
        $this->assertContains('usuario_id', $camposAcomp);

        // pessoa_id e origem_interessado_id estão em Contato e origem (aberta)
        $this->assertContains('pessoa_id', $camposContato);
        $this->assertContains('origem_interessado_id', $camposContato);
    }

    // ─── 3. Persistência: campos em seção recolhida e criação ──────────

    public function test_persistencia_campos_em_secao_recolhida_e_aberta_sao_salvos(): void
    {
        $lead = $this->criarLead($this->admin, [
            'valor_estimado' => 1200.00,
            'proxima_acao' => 'Ação inicial',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->fillForm([
                'valor_estimado' => 3800.50,
                'proxima_acao' => 'Enviar proposta pedagógica e agendar tour',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertEquals(3800.50, $lead->valor_estimado);
        $this->assertEquals('Enviar proposta pedagógica e agendar tour', $lead->proxima_acao);
    }

    public function test_criacao_de_lead_pela_pagina_create_interessado_continua_valida(): void
    {
        $pessoa = Pessoa::factory()->create();

        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']));

        Livewire::actingAs($this->admin)
            ->test(CreateInteressado::class)
            ->fillForm([
                'pessoa_id' => $pessoa->id,
                'status_interessado_id' => $this->statusNovo->id,
                'origem_interessado_id' => $this->origem->id,
                'usuario_id' => $this->admin->id,
                'valor_estimado' => 2500.00,
                'proxima_acao' => 'Primeiro contato telefônico',
                'dependentes' => [
                    [
                        'nome_crianca' => 'Lucas Filho',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('interessado', [
            'pessoa_id' => $pessoa->id,
            'valor_estimado' => 2500.00,
            'proxima_acao' => 'Primeiro contato telefônico',
        ]);
    }

    // ─── 4. Resumo do lead: detalhamento recolhido por padrão ──────────

    public function test_resumo_do_lead_detalhamento_recolhido_por_padrao_e_contem_12_barras(): void
    {
        $lead = $this->criarLead($this->admin);

        $response = $this->actingAs($this->admin)
            ->get("/admin/interessados/{$lead->id}/edit")
            ->assertOk();

        // Controle claro para expandir
        $response->assertSee('Ver como o score foi calculado');
        $response->assertSee('Detalhamento do score');
        $response->assertSee('x-data="{ aberto: false }"', false);
        $response->assertSee('wire:ignore.self', false);
        $response->assertSee('aria-expanded="aberto.toString()"', false);

        // As barras e fatores continuam presentes no DOM
        $response->assertSee('lead-score-fatores', false);
    }

    // ─── 5. Alerta de contato urgente compacto ─────────────────────────

    public function test_alerta_urgente_compacto_sem_classes_gigantes_e_acao_funciona(): void
    {
        $consultor = User::factory()->create(['name' => 'Paula Lima', 'email' => 'paula@escola.com.br']);
        $leadAtrasado = $this->criarLead($consultor, [
            'data_proximo_contato' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/interessados/{$leadAtrasado->id}/edit")
            ->assertOk();

        // Faixa compacta presente
        $response->assertSee('crm-alerta-urgente', false);
        $response->assertSee('Alertar consultor');

        // Não deve ter a combinação de classes antigas do botão gigante pulsante
        $response->assertDontSee('animate-pulse text-lg font-bold');
        $response->assertDontSee('w-full justify-center py-4');

        // Ação continua funcionando
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $leadAtrasado->id])
            ->callAction(TestAction::make('alerta_contato')->schemaComponent('alertaContato'))
            ->assertNotified('Consultor Notificado!');
    }

    // ─── 6. Aviso de duplicados: botão para mesclar ────────────────────

    public function test_aviso_de_duplicados_exibe_botao_que_monta_acao_mesclar_lead(): void
    {
        $leadPrincipal = $this->criarLead($this->admin);

        // Criar um segundo lead com mesmo telefone para gerar duplicidade
        $pessoaDuplicada = Pessoa::factory()->create(['telefone' => '(11) 98888-7777']);
        Interessado::create([
            'pessoa_id' => $pessoaDuplicada->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->statusNovo->id,
            'usuario_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/interessados/{$leadPrincipal->id}/edit")
            ->assertOk();

        $response->assertSee('Atenção: 1 possível(is) lead(s) duplicado(s) detectado(s)');
        $response->assertSee('wire:click="mountAction(\'mesclarLead\')"', false);

        // O botão abre a ação registrada mesclarLead
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $leadPrincipal->id])
            ->mountAction('mesclarLead')
            ->assertMountedActionModalSee(['Mesclar Lead Duplicado']);
    }
}
