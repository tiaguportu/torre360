<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MotivoDesligamentoFuncionario;
use App\Enums\StatusProcessoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\RelationManagers\ProcessosRelationManager;
use App\Filament\Resources\ProcessosFuncionario\Pages\ListProcessosFuncionario;
use App\Filament\Resources\ProcessosFuncionario\Pages\ViewProcessoFuncionario;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Filament\Resources\ProcessosFuncionario\RelationManagers\ItensRelationManager;
use App\Models\Funcionario;
use App\Models\GradeHorario;
use App\Models\ItemProcessoFuncionario;
use App\Models\Pessoa;
use App\Models\ProcessoFuncionario;
use App\Models\User;
use App\Services\ProcessoFuncionarioService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Admissão e desligamento de funcionários (Onda 65).
 *
 * "Hoje" é quarta-feira, 21/10/2026.
 */
class ProcessoFuncionarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        Storage::fake('local');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel', 'aluno'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Concluir'] as $acao) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:ProcessoFuncionario", 'guard_name' => 'web']));
        }

        $this->admin = $this->usuario('admin');
    }

    private function servico(): ProcessoFuncionarioService
    {
        return app(ProcessoFuncionarioService::class);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /**
     * As permissões valem pelo papel ativo da sessão, que é global no teste: quem age é declarado antes de cada chamada.
     */
    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function funcionario(array $atributos = []): Funcionario
    {
        return Funcionario::factory()->create($atributos + [
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza'])->id,
            'cargo' => 'Professora',
            'data_admissao' => '2026-02-02',
        ]);
    }

    private function admissao(?Funcionario $funcionario = null, string $data = '2026-10-10'): ProcessoFuncionario
    {
        return $this->servico()->iniciar($funcionario ?? $this->funcionario(), TipoProcessoFuncionario::Admissao, $data, $this->como($this->admin));
    }

    private function desligamento(?Funcionario $funcionario = null, string $data = '2026-10-20'): ProcessoFuncionario
    {
        return $this->servico()->iniciar($funcionario ?? $this->funcionario(), TipoProcessoFuncionario::Desligamento, $data, $this->como($this->admin), MotivoDesligamentoFuncionario::PedidoDemissao);
    }

    /**
     * Resolve todas as providências obrigatórias do processo.
     */
    private function resolverObrigatorias(ProcessoFuncionario $processo): void
    {
        foreach ($processo->itens()->where('obrigatorio', true)->get() as $item) {
            $this->servico()->marcarItem($item, $this->como($this->admin));
        }
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('comprovante.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    // --- Início ------------------------------------------------------------------------------------------------------

    public function test_iniciar_admissao_cria_a_lista_padrao_com_os_prazos_a_partir_do_primeiro_dia(): void
    {
        $processo = $this->admissao(null, '2026-11-02');

        $itens = $processo->itens()->get();
        $modelo = config('rh_processos.admissao');

        $this->assertSame(StatusProcessoFuncionario::EmAndamento, $processo->status);
        $this->assertCount(count($modelo), $itens);
        $this->assertSame(range(1, count($modelo)), $itens->pluck('ordem')->all());
        $this->assertSame('Documentos pessoais', $itens->first()->titulo);
        $this->assertSame('2026-10-30', $itens->first()->prazo->toDateString(), 'três dias antes do primeiro dia de trabalho');
        $this->assertTrue($itens->first()->obrigatorio);
        $this->assertSame('2026-11-02', $itens->firstWhere('titulo', 'Contrato de trabalho assinado')->prazo->toDateString());
        $this->assertNull($itens->firstWhere('titulo', 'E-mail institucional e grupos de comunicação')->concluido_em);
        $this->assertSame($this->admin->id, $processo->iniciado_por_user_id);
    }

    public function test_desligamento_tem_lista_propria_motivo_e_observacao_criptografada(): void
    {
        $processo = $this->servico()->iniciar($this->funcionario(), TipoProcessoFuncionario::Desligamento, '2026-11-30', $this->admin, MotivoDesligamentoFuncionario::JustaCausa, 'Faltas injustificadas em setembro');

        $this->assertSame(count(config('rh_processos.desligamento')), $processo->itens()->count());
        $this->assertSame(MotivoDesligamentoFuncionario::JustaCausa, $processo->fresh()->motivo);
        $this->assertSame('2026-10-31', $processo->itens()->orderBy('ordem')->first()->prazo->toDateString(), 'aviso prévio: 30 dias antes');

        $this->assertSame('Faltas injustificadas em setembro', $processo->fresh()->observacao);
        $this->assertStringNotContainsString('Faltas', (string) \DB::table('processos_funcionario')->where('id', $processo->id)->value('observacao'));

        $logs = Activity::query()->get()->map(fn (Activity $a): string => $a->description.json_encode($a->properties, JSON_UNESCAPED_UNICODE))->implode(' ');

        $this->assertStringContainsString('Desligamento iniciado.', $logs);
        $this->assertStringNotContainsString('Faltas', $logs);
        $this->assertStringNotContainsString('justa_causa', $logs, 'o motivo do desligamento também fica fora do log');
    }

    public function test_desligamento_exige_motivo_e_funcionario_ativo(): void
    {
        try {
            $this->servico()->iniciar($this->funcionario(), TipoProcessoFuncionario::Desligamento, '2026-11-30', $this->admin);
            $this->fail('sem motivo');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo do desligamento', $e->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já foi desligado em 15/09/2026');

        $this->desligamento($this->funcionario(['data_desligamento' => '2026-09-15']));
    }

    public function test_so_um_processo_aberto_por_tipo_e_funcionario(): void
    {
        $funcionario = $this->funcionario();
        $admissao = $this->admissao($funcionario);

        try {
            $this->admissao($funcionario);
            $this->fail('segunda admissão aberta');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('uma admissão em andamento', $e->getMessage());
        }

        $this->desligamento($funcionario);
        $this->assertSame(2, $funcionario->processos()->count(), 'admissão e desligamento convivem');

        $this->servico()->cancelar($admissao, $this->admin);
        $this->assertSame(StatusProcessoFuncionario::EmAndamento, $this->admissao($funcionario)->status, 'depois de cancelada, pode abrir de novo');
    }

    // --- Providências ------------------------------------------------------------------------------------------------

    public function test_marcar_registra_quem_e_quando_e_guarda_o_comprovante_em_area_privada(): void
    {
        $processo = $this->admissao();
        $item = $processo->itens()->firstOrFail();

        $this->servico()->marcarItem($item, $this->admin, 'Conferido pela contadora', $this->pdf());

        $item = $item->fresh();

        $this->assertTrue($item->estaResolvido());
        $this->assertFalse($item->nao_se_aplica);
        $this->assertSame($this->admin->id, $item->concluido_por_user_id);
        $this->assertSame('Conferido pela contadora', $item->observacao);
        $this->assertStringStartsWith("processos_funcionario/{$processo->id}/item-{$item->id}-", $item->arquivo_path);
        $this->assertStringEndsWith('.pdf', $item->arquivo_path);
        Storage::disk('local')->assertExists($item->arquivo_path);
    }

    public function test_comprovante_invalido_e_recusado_e_a_providencia_continua_pendente(): void
    {
        $item = $this->admissao()->itens()->firstOrFail();

        try {
            $this->servico()->marcarItem($item, $this->admin, null, UploadedFile::fake()->createWithContent('rg.pdf', '<html><script>alert(1)</script></html>'));
            $this->fail('HTML disfarçado de PDF');
        } catch (\DomainException) {
            $this->assertFalse($item->fresh()->estaResolvido());
            $this->assertNull($item->fresh()->arquivo_path);
        }
    }

    public function test_trocar_o_comprovante_apaga_o_anterior(): void
    {
        $item = $this->admissao()->itens()->firstOrFail();

        $this->servico()->anexarArquivo($item, $this->pdf(), $this->admin);
        $primeiro = $item->fresh()->arquivo_path;

        $this->travel(2)->seconds();
        $this->servico()->anexarArquivo($item->fresh(), $this->pdf(), $this->admin);

        Storage::disk('local')->assertMissing($primeiro);
        Storage::disk('local')->assertExists($item->fresh()->arquivo_path);
        $this->assertFalse($item->fresh()->estaResolvido(), 'anexar não resolve a providência');
    }

    public function test_nao_se_aplica_exige_justificativa_e_conta_como_resolvido(): void
    {
        $item = $this->desligamento()->itens()->firstWhere('titulo', 'Comunicação do desligamento e aviso prévio');

        try {
            $this->servico()->dispensarItem($item, $this->admin, '   ');
            $this->fail('justificativa vazia');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Explique por que', $e->getMessage());
        }

        $this->servico()->dispensarItem($item, $this->admin, 'Contrato por prazo determinado: não há aviso prévio.');

        $item = $item->fresh();

        $this->assertTrue($item->estaResolvido());
        $this->assertTrue($item->nao_se_aplica);
        $this->assertSame('Contrato por prazo determinado: não há aviso prévio.', $item->observacao);
    }

    public function test_reabrir_volta_a_providencia_para_pendente(): void
    {
        $item = $this->admissao()->itens()->firstOrFail();
        $this->servico()->marcarItem($item, $this->admin);

        $this->servico()->reabrirItem($item->fresh(), $this->admin);

        $item = $item->fresh();

        $this->assertFalse($item->estaResolvido());
        $this->assertNull($item->concluido_por_user_id);
    }

    public function test_acrescentar_providencia_vai_para_o_fim_da_lista(): void
    {
        $processo = $this->admissao();
        $total = $processo->itens()->count();

        $item = $this->servico()->adicionarItem($processo, ['titulo' => 'Assinar termo de sigilo', 'categoria' => 'documento', 'obrigatorio' => true, 'prazo' => '2026-10-15'], $this->admin);

        $this->assertSame($total + 1, $item->ordem);
        $this->assertTrue($item->obrigatorio);
        $this->assertSame('2026-10-15', $item->prazo->toDateString());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('título da providência');

        $this->servico()->adicionarItem($processo, ['titulo' => ' '], $this->admin);
    }

    public function test_processo_encerrado_nao_aceita_mudancas_nas_providencias(): void
    {
        $processo = $this->admissao();
        $item = $processo->itens()->firstOrFail();
        $this->servico()->cancelar($processo, $this->admin);

        foreach ([
            fn () => $this->servico()->marcarItem($item, $this->admin),
            fn () => $this->servico()->dispensarItem($item, $this->admin, 'x'),
            fn () => $this->servico()->reabrirItem($item, $this->admin),
            fn () => $this->servico()->anexarArquivo($item, $this->pdf(), $this->admin),
            fn () => $this->servico()->adicionarItem($processo, ['titulo' => 'Novo'], $this->admin),
        ] as $tentativa) {
            try {
                $tentativa();
                $this->fail('processo cancelado não aceita mudança');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('já foi encerrado', $e->getMessage());
            }
        }
    }

    public function test_progresso_conta_resolvidos_obrigatorios_pendentes_e_atrasados(): void
    {
        $processo = $this->admissao(null, '2026-10-10'); // hoje é 21/10: o que vence até 7 dias depois do início já passou
        $modelo = collect(config('rh_processos.admissao'));
        $atrasadosEsperados = $modelo->filter(fn (array $i): bool => Carbon::parse('2026-10-10')->addDays($i['prazo_dias'])->lt(today()))->count();
        $obrigatoriosEsperados = $modelo->where('obrigatorio', true)->count();

        $antes = $this->servico()->progresso($processo);

        $this->assertSame($modelo->count(), $antes['total']);
        $this->assertSame(0, $antes['resolvidos']);
        $this->assertSame($atrasadosEsperados, $antes['atrasados']);
        $this->assertSame($obrigatoriosEsperados, $antes['obrigatorios_pendentes']);
        $this->assertSame(0, $antes['percentual']);

        $this->servico()->marcarItem($processo->itens()->firstOrFail(), $this->admin);

        $depois = $this->servico()->progresso($processo);

        $this->assertSame(1, $depois['resolvidos']);
        $this->assertSame($atrasadosEsperados - 1, $depois['atrasados']);
        $this->assertSame($obrigatoriosEsperados - 1, $depois['obrigatorios_pendentes']);
    }

    // --- Conclusão ---------------------------------------------------------------------------------------------------

    public function test_concluir_exige_as_obrigatorias_resolvidas_e_diz_o_que_falta(): void
    {
        $processo = $this->admissao();

        try {
            $this->servico()->concluir($processo, $this->admin);
            $this->fail('faltam obrigatórias');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Faltam providências obrigatórias: Documentos pessoais', $e->getMessage());
            $this->assertStringContainsString('e mais', $e->getMessage());
        }

        $this->assertSame(StatusProcessoFuncionario::EmAndamento, $processo->fresh()->status);

        $this->resolverObrigatorias($processo);
        $this->servico()->concluir($processo, $this->admin);

        $concluido = $processo->fresh();

        $this->assertSame(StatusProcessoFuncionario::Concluido, $concluido->status);
        $this->assertSame($this->admin->id, $concluido->concluido_por_user_id);
        $this->assertNotNull($concluido->concluido_em);
    }

    public function test_opcionais_nao_impedem_a_conclusao_e_a_admissao_nao_mexe_no_cadastro(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $processo = $this->admissao($funcionario, '2026-10-10');
        $this->resolverObrigatorias($processo);

        $this->assertGreaterThan(0, $processo->itens()->where('obrigatorio', false)->whereNull('concluido_em')->count());

        $this->servico()->concluir($processo, $this->admin);

        $this->assertSame('2026-02-02', $funcionario->fresh()->data_admissao->toDateString());
        $this->assertTrue($funcionario->fresh()->isAtivo());
    }

    public function test_concluir_so_uma_vez(): void
    {
        $processo = $this->admissao();
        $this->resolverObrigatorias($processo);
        $this->servico()->concluir($processo, $this->admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já foi encerrado');

        $this->servico()->concluir($processo, $this->admin);
    }

    // --- Desligamento e acessos --------------------------------------------------------------------------------------

    private function funcionarioComUsuario(array $papeis): array
    {
        $funcionario = $this->funcionario();
        $usuario = User::factory()->create(['activated_at' => now()->subYear(), 'email_verified_at' => now(), 'email' => 'marta@escola.test']);

        foreach ($papeis as $papel) {
            $usuario->assignRole($papel);
        }

        $funcionario->pessoa->users()->save($usuario);

        return [$funcionario, $usuario->fresh()];
    }

    public function test_concluir_desligamento_grava_a_data_e_retira_os_acessos_de_equipe(): void
    {
        [$funcionario, $usuario] = $this->funcionarioComUsuario(['professor', 'secretaria']);
        $usuario->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:Nota', 'guard_name' => 'web']));
        $processo = $this->desligamento($funcionario, '2026-10-20');
        $this->resolverObrigatorias($processo);

        $resumo = $this->servico()->concluir($processo, $this->como($this->admin));

        $usuario = $usuario->fresh();

        $this->assertSame('2026-10-20', $funcionario->fresh()->data_desligamento->toDateString());
        $this->assertFalse($funcionario->fresh()->isAtivo());
        $this->assertSame([], $usuario->roles->pluck('name')->all());
        $this->assertSame([], $usuario->permissions->pluck('name')->all(), 'a permissão direta também sai');
        $this->assertNotNull($usuario->deactivated_at, 'sem nenhum papel sobrando, a conta é desativada');
        $this->assertFalse($usuario->is_active);
        $this->assertSame(1, $resumo['usuarios_sem_acesso']);
        $this->assertSame(1, $resumo['usuarios_desativados']);
    }

    public function test_quem_tambem_e_responsavel_ou_aluno_continua_com_a_conta(): void
    {
        [$funcionario, $usuario] = $this->funcionarioComUsuario(['professor', 'responsavel']);
        $processo = $this->desligamento($funcionario);
        $this->resolverObrigatorias($processo);

        $resumo = $this->servico()->concluir($processo, $this->como($this->admin));

        $usuario = $usuario->fresh();

        $this->assertSame(['responsavel'], $usuario->roles->pluck('name')->all());
        $this->assertNull($usuario->deactivated_at);
        $this->assertTrue($usuario->is_active);
        $this->assertSame(1, $resumo['usuarios_sem_acesso']);
        $this->assertSame(0, $resumo['usuarios_desativados']);
    }

    public function test_super_admin_nao_e_alterado_e_vira_aviso(): void
    {
        [$funcionario, $superAdmin] = $this->funcionarioComUsuario(['super_admin', 'professor']);
        $processo = $this->desligamento($funcionario);
        $this->resolverObrigatorias($processo);

        $resumo = $this->servico()->concluir($processo, $this->como($this->admin));

        $this->assertEqualsCanonicalizing(['super_admin', 'professor'], $superAdmin->fresh()->roles->pluck('name')->all());
        $this->assertNull($superAdmin->fresh()->deactivated_at);
        $this->assertSame(0, $resumo['usuarios_sem_acesso']);
        $this->assertStringContainsString('super administrador', implode(' ', $resumo['avisos']));
        $this->assertSame('2026-10-20', $funcionario->fresh()->data_desligamento->toDateString(), 'a data é gravada mesmo assim');
    }

    public function test_desligamento_so_conclui_a_partir_do_ultimo_dia_de_trabalho(): void
    {
        [$funcionario, $usuario] = $this->funcionarioComUsuario(['professor']);
        $processo = $this->desligamento($funcionario, '2026-11-30');
        $this->resolverObrigatorias($processo);

        try {
            $this->servico()->concluir($processo, $this->como($this->admin));
            $this->fail('o último dia ainda não chegou');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Conclua a partir dessa data', $e->getMessage());
        }

        $this->assertTrue($funcionario->fresh()->isAtivo());
        $this->assertSame(['professor'], $usuario->fresh()->roles->pluck('name')->all(), 'o acesso segue até o último dia');

        $this->travelTo(Carbon::parse('2026-11-30 18:00:00'));
        $this->servico()->concluir($processo, $this->como($this->admin));

        $this->assertSame([], $usuario->fresh()->roles->pluck('name')->all());
    }

    public function test_desligamento_avisa_das_aulas_que_ficam_sem_professor(): void
    {
        [$funcionario] = $this->funcionarioComUsuario(['professor']);
        GradeHorario::factory()->count(2)->create(['professor_id' => $funcionario->pessoa_id]);
        $processo = $this->desligamento($funcionario);
        $this->resolverObrigatorias($processo);

        $resumo = $this->servico()->concluir($processo, $this->como($this->admin));

        $this->assertStringContainsString('2 aula(s) na grade horária', implode(' ', $resumo['avisos']));
    }

    public function test_cancelar_nao_mexe_no_cadastro_nem_nos_acessos(): void
    {
        [$funcionario, $usuario] = $this->funcionarioComUsuario(['professor']);
        $processo = $this->desligamento($funcionario);

        $this->servico()->cancelar($processo, $this->admin);

        $this->assertSame(StatusProcessoFuncionario::Cancelado, $processo->fresh()->status);
        $this->assertTrue($funcionario->fresh()->isAtivo());
        $this->assertSame(['professor'], $usuario->fresh()->roles->pluck('name')->all());
    }

    public function test_escopo_atrasados_ignora_itens_resolvidos_e_processos_encerrados(): void
    {
        $emAndamento = $this->admissao(null, '2026-10-10');
        $cancelado = $this->admissao($this->funcionario(['pessoa_id' => Pessoa::factory()->create()->id]), '2026-10-10');
        $this->servico()->cancelar($cancelado, $this->admin);

        $atrasadosAntes = ItemProcessoFuncionario::query()->atrasados()->count();
        $esperados = $this->servico()->progresso($emAndamento)['atrasados'];

        $this->assertSame($esperados, $atrasadosAntes, 'só os do processo em andamento');

        $this->servico()->marcarItem($emAndamento->itens()->firstOrFail(), $this->admin);

        $this->assertSame($esperados - 1, ItemProcessoFuncionario::query()->atrasados()->count());
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_lista_abre_para_o_admin_e_nao_para_secretaria_ou_professor(): void
    {
        $this->admissao();

        Livewire::actingAs($this->como($this->admin))->test(ListProcessosFuncionario::class)->assertSuccessful()->assertSee('Marta Souza');
        Livewire::actingAs($this->como($this->usuario('secretaria')))->test(ListProcessosFuncionario::class)->assertForbidden();
        Livewire::actingAs($this->como($this->usuario('professor')))->test(ListProcessosFuncionario::class)->assertForbidden();
    }

    public function test_iniciar_admissao_pela_lista_leva_para_o_processo(): void
    {
        $funcionario = $this->funcionario();

        $tela = Livewire::actingAs($this->como($this->admin))
            ->test(ListProcessosFuncionario::class)
            ->callAction('iniciar_admissao', data: ['funcionario_id' => $funcionario->id, 'data_prevista' => '2026-11-02'])
            ->assertHasNoActionErrors();

        $processo = ProcessoFuncionario::firstOrFail();

        $tela->assertRedirect(ProcessoFuncionarioResource::getUrl('view', ['record' => $processo]));
        $this->assertSame(TipoProcessoFuncionario::Admissao, $processo->tipo);
    }

    public function test_iniciar_desligamento_pela_lista_pede_motivo_e_so_oferece_quem_esta_ativo(): void
    {
        $ativo = $this->funcionario();
        $desligado = $this->funcionario(['pessoa_id' => Pessoa::factory()->create(['nome' => 'Joana Antiga'])->id, 'data_desligamento' => '2026-01-31']);

        Livewire::actingAs($this->como($this->admin))
            ->test(ListProcessosFuncionario::class)
            ->callAction('iniciar_desligamento', data: ['funcionario_id' => $ativo->id, 'data_prevista' => '2026-11-30'])
            ->assertHasActionErrors(['motivo' => 'required']);

        $this->assertSame(0, ProcessoFuncionario::count());

        Livewire::actingAs($this->como($this->admin))
            ->test(ListProcessosFuncionario::class)
            ->callAction('iniciar_desligamento', data: ['funcionario_id' => $ativo->id, 'data_prevista' => '2026-11-30', 'motivo' => 'pedido_demissao'])
            ->assertHasNoActionErrors();

        $this->assertSame(1, ProcessoFuncionario::count());

        // O desligado nem aparece na busca, e o serviço também recusa.
        $this->expectException(\DomainException::class);
        $this->servico()->iniciar($desligado, TipoProcessoFuncionario::Desligamento, '2026-11-30', $this->admin, MotivoDesligamentoFuncionario::Outro);
    }

    public function test_pagina_do_funcionario_lista_os_processos_e_inicia_um_novo(): void
    {
        $funcionario = $this->funcionario();
        $existente = $this->admissao($funcionario);

        $tela = Livewire::actingAs($this->como($this->admin))
            ->test(ProcessosRelationManager::class, ['ownerRecord' => $funcionario, 'pageClass' => EditFuncionario::class])
            ->assertCanSeeTableRecords([$existente])
            ->assertTableActionExists('abrir', null, $existente);

        $tela->callAction(TestAction::make('iniciar_desligamento')->table(), data: ['data_prevista' => '2026-11-30', 'motivo' => 'acordo'])->assertHasNoActionErrors();

        $this->assertSame(2, $funcionario->processos()->count());
        $this->assertSame(MotivoDesligamentoFuncionario::Acordo, $funcionario->processos()->first()->motivo);
    }

    private function itens(ProcessoFuncionario $processo, ?User $usuario = null)
    {
        return Livewire::actingAs($this->como($usuario ?? $this->admin))
            ->test(ItensRelationManager::class, ['ownerRecord' => $processo, 'pageClass' => ViewProcessoFuncionario::class]);
    }

    public function test_marcar_dispensar_reabrir_e_anexar_pela_lista_de_providencias(): void
    {
        $processo = $this->admissao();
        [$primeiro, $segundo, $terceiro] = $processo->itens()->limit(3)->get()->all();

        $this->itens($processo)
            ->assertCanSeeTableRecords([$primeiro])
            ->callTableAction('marcar', $primeiro, data: ['observacao' => 'Cópias conferidas', 'arquivo' => $this->pdf()])
            ->assertHasNoTableActionErrors()
            ->callTableAction('dispensar', $segundo, data: ['justificativa' => 'Já consta no cadastro'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('anexar', $terceiro, data: ['arquivo' => $this->pdf()])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($primeiro->fresh()->estaResolvido());
        $this->assertNotNull($primeiro->fresh()->arquivo_path);
        $this->assertTrue($segundo->fresh()->nao_se_aplica);
        $this->assertNotNull($terceiro->fresh()->arquivo_path);
        $this->assertFalse($terceiro->fresh()->estaResolvido());

        $this->itens($processo)
            ->assertTableActionHidden('marcar', $primeiro)
            ->assertTableActionVisible('reabrir', $primeiro)
            ->callTableAction('reabrir', $primeiro);

        $this->assertFalse($primeiro->fresh()->estaResolvido());
    }

    public function test_justificativa_e_obrigatoria_na_tela_de_nao_se_aplica(): void
    {
        $processo = $this->admissao();
        $item = $processo->itens()->firstOrFail();

        $this->itens($processo)
            ->callTableAction('dispensar', $item, data: ['justificativa' => ''])
            ->assertHasTableActionErrors(['justificativa' => 'required']);

        $this->assertFalse($item->fresh()->estaResolvido());
    }

    public function test_acrescentar_providencia_pela_tela(): void
    {
        $processo = $this->admissao();

        $this->itens($processo)
            ->callAction(TestAction::make('adicionar')->table(), data: ['titulo' => 'Entregar manual do professor', 'categoria' => 'treinamento', 'obrigatorio' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue($processo->itens()->where('titulo', 'Entregar manual do professor')->where('obrigatorio', true)->exists());
    }

    public function test_acoes_somem_quando_o_processo_ja_foi_encerrado(): void
    {
        $processo = $this->admissao();
        $item = $processo->itens()->firstOrFail();
        $this->servico()->cancelar($processo, $this->admin);

        $this->itens($processo->fresh())
            ->assertTableActionHidden('marcar', $item)
            ->assertTableActionHidden('dispensar', $item)
            ->assertTableActionHidden('anexar', $item)
            ->assertActionHidden(TestAction::make('adicionar')->table());
    }

    public function test_concluir_e_cancelar_pela_pagina_do_processo(): void
    {
        $processo = $this->admissao();

        $tela = Livewire::actingAs($this->como($this->admin))
            ->test(ViewProcessoFuncionario::class, ['record' => $processo->getRouteKey()])
            ->assertActionVisible('concluir')
            ->callAction('concluir');

        $this->assertSame(StatusProcessoFuncionario::EmAndamento, $processo->fresh()->status, 'faltam obrigatórias: a tela avisa e nada muda');

        $this->resolverObrigatorias($processo);

        $tela = Livewire::actingAs($this->como($this->admin))
            ->test(ViewProcessoFuncionario::class, ['record' => $processo->getRouteKey()])
            ->callAction('concluir');

        $this->assertSame(StatusProcessoFuncionario::Concluido, $processo->fresh()->status);

        $tela->assertActionHidden('concluir')->assertActionHidden('cancelar');

        $outro = $this->admissao($this->funcionario(['pessoa_id' => Pessoa::factory()->create()->id]));

        Livewire::actingAs($this->como($this->admin))
            ->test(ViewProcessoFuncionario::class, ['record' => $outro->getRouteKey()])
            ->callAction('cancelar');

        $this->assertSame(StatusProcessoFuncionario::Cancelado, $outro->fresh()->status);
    }

    public function test_so_o_admin_abre_a_pagina_do_processo(): void
    {
        $processo = $this->admissao();

        Livewire::actingAs($this->como($this->usuario('secretaria')))
            ->test(ViewProcessoFuncionario::class, ['record' => $processo->getRouteKey()])
            ->assertForbidden();
    }

    public function test_selo_do_menu_conta_as_providencias_vencidas_e_se_atualiza(): void
    {
        $processo = $this->admissao(null, '2026-10-10');
        $esperado = $this->servico()->progresso($processo)['atrasados'];

        $this->actingAs($this->como($this->admin));
        $this->assertSame((string) $esperado, ProcessoFuncionarioResource::getNavigationBadge());

        $this->servico()->marcarItem($processo->itens()->firstOrFail(), $this->admin);

        $this->assertSame((string) ($esperado - 1), ProcessoFuncionarioResource::getNavigationBadge(), 'a mudança esquece o cache do selo');

        $this->actingAs($this->como($this->usuario('professor')));
        $this->assertNull(ProcessoFuncionarioResource::getNavigationBadge());
    }

    // --- Comprovantes ------------------------------------------------------------------------------------------------

    public function test_comprovante_so_abre_para_quem_pode_ver_o_rh(): void
    {
        $item = $this->admissao()->itens()->firstOrFail();
        $this->servico()->marcarItem($item, $this->admin, null, $this->pdf());
        $caminho = $item->fresh()->arquivo_path;

        $this->actingAs($this->como($this->admin))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();
        $this->actingAs($this->como($this->usuario('secretaria')))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
        $this->actingAs($this->como($this->usuario('coordenador')))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
        $this->actingAs($this->como($this->usuario('professor')))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
    }
}
