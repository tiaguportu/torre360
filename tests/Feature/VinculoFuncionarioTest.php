<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MotivoDesligamentoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Filament\Resources\Funcionarios\Pages\CreateFuncionario;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\RelationManagers\ContratosTrabalhoRelationManager;
use App\Filament\Resources\Funcionarios\RelationManagers\ProcessosRelationManager;
use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use App\Models\PeriodoFerias;
use App\Models\Pessoa;
use App\Models\PontoRegistro;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ProcessoFuncionarioService;
use App\Services\VinculoFuncionarioService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VinculoFuncionarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'professor'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['Funcionario', 'ContratoTrabalho', 'ProcessoFuncionario'] as $modelo) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Concluir'] as $acao) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:{$modelo}", 'guard_name' => 'web']);
                Role::findByName('admin')->givePermissionTo($permissao);
            }
        }

        $this->admin = $this->usuario('admin');
        $this->como($this->admin);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        $this->actingAs($usuario);
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function funcionario(array $atributos = []): Funcionario
    {
        return Funcionario::factory()->create($atributos + [
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza'])->id,
            'cargo' => 'Professora',
            'data_admissao' => '2026-02-02',
            'data_desligamento' => null,
        ]);
    }

    private function servico(): VinculoFuncionarioService
    {
        return app(VinculoFuncionarioService::class);
    }

    // --- VinculoFuncionarioService: abrirContratoInicial -------------------------------------------------------------

    public function test_abrir_contrato_inicial_com_sucesso(): void
    {
        $funcionario = $this->funcionario();
        $contrato = $this->servico()->abrirContratoInicial($funcionario, '3500.50', $this->admin);

        $this->assertSame('2026-02-02', $contrato->vigencia_inicio->toDateString());
        $this->assertNull($contrato->vigencia_fim);
        $this->assertSame('3500.50', $contrato->salario);

        $log = Activity::query()->where('log_name', 'rh')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('Contrato inicial registrado.', $log->description);
    }

    public function test_abrir_contrato_inicial_recusa_salario_invalido(): void
    {
        $funcionario = $this->funcionario();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('salário inicial deve ser maior que zero');

        $this->servico()->abrirContratoInicial($funcionario, 0, $this->admin);
    }

    public function test_abrir_contrato_inicial_recusa_funcionario_desligado(): void
    {
        $funcionario = $this->funcionario(['data_desligamento' => '2026-09-01']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não é possível abrir contrato inicial para funcionário desligado.');

        $this->servico()->abrirContratoInicial($funcionario, 4000, $this->admin);
    }

    public function test_abrir_contrato_inicial_recusa_quando_ja_existe_contrato(): void
    {
        $funcionario = $this->funcionario();
        $this->servico()->abrirContratoInicial($funcionario, 3000, $this->admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Este funcionário já possui contrato de trabalho cadastrado.');

        $this->servico()->abrirContratoInicial($funcionario, 3500, $this->admin);
    }

    // --- VinculoFuncionarioService: encerrarContratos ----------------------------------------------------------------

    public function test_encerrar_contratos_fecha_abertos_e_posteriores_mantem_anteriores_e_avisa_futuros(): void
    {
        $funcionario = $this->funcionario();

        // 1. Contrato anterior já encerrado em 2026-05-01
        $c1 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-02-02',
            'vigencia_fim' => '2026-05-01',
            'salario' => 2000,
        ]);

        // 2. Contrato vigente aberto (vigencia_fim = null)
        $c2 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-05-02',
            'vigencia_fim' => null,
            'salario' => 2500,
        ]);

        // 3. Contrato futuro que começa após o desligamento
        $c3 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-11-01',
            'vigencia_fim' => null,
            'salario' => 3000,
        ]);

        $resumo = $this->servico()->encerrarContratos($funcionario, Carbon::parse('2026-10-20'));

        $this->assertSame(1, $resumo['encerrados']);
        $this->assertCount(1, $resumo['avisos']);
        $this->assertStringContainsString('com início depois do desligamento', $resumo['avisos'][0]);

        $this->assertSame('2026-05-01', $c1->fresh()->vigencia_fim->toDateString(), 'vigência anterior mantida');
        $this->assertSame('2026-10-20', $c2->fresh()->vigencia_fim->toDateString(), 'vigência aberta encerrada');
        $this->assertNull($c3->fresh()->vigencia_fim, 'vigência futura permanece aberta para revisão');
    }

    public function test_encerrar_contratos_sem_contratos_nao_da_erro(): void
    {
        $funcionario = $this->funcionario();
        $resumo = $this->servico()->encerrarContratos($funcionario, Carbon::parse('2026-10-20'));

        $this->assertSame(0, $resumo['encerrados']);
        $this->assertSame([], $resumo['avisos']);
    }

    // --- VinculoFuncionarioService: corrigirDataAdmissao -------------------------------------------------------------

    public function test_corrigir_data_admissao_atualiza_funcionario_e_primeira_vigencia_com_log(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $c1 = $this->servico()->abrirContratoInicial($funcionario, 3000, $this->admin);

        $resultado = $this->servico()->corrigirDataAdmissao(
            $funcionario,
            '2026-01-15',
            'Correção conforme CTPS física apresentada pelo colaborador',
            $this->admin
        );

        $this->assertSame('2026-01-15', $funcionario->fresh()->data_admissao->toDateString());
        $this->assertSame('2026-01-15', $c1->fresh()->vigencia_inicio->toDateString());
        $this->assertSame([], $resultado['avisos']);

        $log = Activity::query()->where('log_name', 'rh')->where('description', 'Data de admissão corrigida.')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('2026-02-02', $log->properties['data_anterior']);
        $this->assertSame('2026-01-15', $log->properties['data_nova']);
        $this->assertStringContainsString('CTPS física', $log->properties['justificativa']);
    }

    public function test_corrigir_data_admissao_recusas_de_validacao(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);

        // 1. Justificativa curta
        try {
            $this->servico()->corrigirDataAdmissao($funcionario, '2026-01-15', 'Curto', $this->admin);
            $this->fail('justificativa curta');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('pelo menos 10 caracteres', $e->getMessage());
        }

        // 2. Data igual
        try {
            $this->servico()->corrigirDataAdmissao($funcionario, '2026-02-02', 'Justificativa longa o bastante', $this->admin);
            $this->fail('data igual');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('igual à data atual', $e->getMessage());
        }

        // 3. Funcionário desligado
        $desligado = $this->funcionario(['data_desligamento' => '2026-09-01']);
        try {
            $this->servico()->corrigirDataAdmissao($desligado, '2026-01-15', 'Justificativa longa o bastante', $this->admin);
            $this->fail('desligado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('funcionário desligado', $e->getMessage());
        }

        // 4. Admissão em andamento
        app(ProcessoFuncionarioService::class)->iniciar($funcionario, TipoProcessoFuncionario::Admissao, null, $this->admin);
        try {
            $this->servico()->corrigirDataAdmissao($funcionario, '2026-01-15', 'Justificativa longa o bastante', $this->admin);
            $this->fail('admissão em andamento');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('processo de admissão em andamento', $e->getMessage());
        }
    }

    public function test_corrigir_data_admissao_recusa_data_posterior_ao_fim_ou_inicio_da_proxima_vigencia(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $c1 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-02-02',
            'vigencia_fim' => '2026-04-30',
            'salario' => 2000,
        ]);
        $c2 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-05-01',
            'vigencia_fim' => null,
            'salario' => 2500,
        ]);

        // Data posterior ao término da 1ª vigência
        try {
            $this->servico()->corrigirDataAdmissao($funcionario, '2026-05-10', 'Justificativa válida com mais de dez caracteres', $this->admin);
            $this->fail('posterior ao término');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não pode ser posterior ao término da primeira vigência', $e->getMessage());
        }
    }

    public function test_corrigir_data_admissao_gera_avisos_para_ferias_e_ponto_anteriores(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        PeriodoFerias::factory()->create([
            'funcionario_id' => $funcionario->id,
            'periodo_aquisitivo_inicio' => '2026-02-15',
            'periodo_aquisitivo_fim' => '2027-02-14',
        ]);
        $unidade = Unidade::create(['nome' => 'Sede', 'cnpj' => '12.345.678/0001-90', 'situacao_funcionamento' => '1']);

        PontoRegistro::query()->insert([
            'unidade_id' => $unidade->id,
            'funcionario_id' => $funcionario->id,
            'nsr' => 1,
            'momento' => '2026-02-20 08:00:00',
            'fuso' => '-03:00',
            'origem' => 'web',
            'hora_legal_ok' => true,
            'hash' => str_repeat('a', 64),
            'created_at' => now(),
        ]);

        // Mudar admissão para DEPOIS do início das férias e do ponto
        $resultado = $this->servico()->corrigirDataAdmissao($funcionario, '2026-03-01', 'Retificação de data posterior', $this->admin);

        $this->assertCount(2, $resultado['avisos']);
        $this->assertStringContainsString('férias', $resultado['avisos'][0]);
        $this->assertStringContainsString('ponto eletrônico', $resultado['avisos'][1]);
    }

    // --- VinculoFuncionarioService: removerUltimaVigencia ------------------------------------------------------------

    public function test_remover_ultima_vigencia_reabre_a_anterior(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $c1 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-02-02',
            'vigencia_fim' => '2026-04-30',
            'salario' => 2000,
        ]);
        $c2 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-05-01',
            'vigencia_fim' => null,
            'salario' => 2500,
        ]);

        $this->servico()->removerUltimaVigencia($c2);

        $this->assertDatabaseMissing('contratos_trabalho', ['id' => $c2->id]);
        $this->assertNull($c1->fresh()->vigencia_fim, 'a vigência anterior é reaberta');
    }

    public function test_remover_ultima_vigencia_recusa_se_nao_for_a_mais_recente_ou_se_desligado(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $c1 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-02-02',
            'vigencia_fim' => '2026-04-30',
            'salario' => 2000,
        ]);
        $c2 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => '2026-05-01',
            'vigencia_fim' => null,
            'salario' => 2500,
        ]);

        try {
            $this->servico()->removerUltimaVigencia($c1);
            $this->fail('não é o mais recente');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('mais recente pode ser removida', $e->getMessage());
        }

        $funcionario->update(['data_desligamento' => '2026-09-01']);
        try {
            $this->servico()->removerUltimaVigencia($c2);
            $this->fail('funcionário desligado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('funcionário desligado', $e->getMessage());
        }
    }

    // --- ProcessoFuncionarioService: iniciar e concluir com vínculo --------------------------------------------------

    public function test_iniciar_admissao_sem_data_usa_data_do_cadastro_e_recusa_data_diferente(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $service = app(ProcessoFuncionarioService::class);

        // Sem data: usa 2026-02-02
        $p1 = $service->iniciar($funcionario, TipoProcessoFuncionario::Admissao, null, $this->admin);
        $this->assertSame('2026-02-02', $p1->data_prevista->toDateString());
        $service->cancelar($p1, $this->admin);

        // Com data igual: aceita
        $p2 = $service->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-02-02', $this->admin);
        $this->assertSame('2026-02-02', $p2->data_prevista->toDateString());
        $service->cancelar($p2, $this->admin);

        // Com data diferente: recusa
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('A data da admissão é a do cadastro (02/02/2026)');

        $service->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-03-01', $this->admin);
    }

    public function test_iniciar_admissao_recusa_desligado(): void
    {
        $desligado = $this->funcionario(['data_desligamento' => '2026-09-01']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não é possível iniciar processo de admissão para funcionário desligado.');

        app(ProcessoFuncionarioService::class)->iniciar($desligado, TipoProcessoFuncionario::Admissao, null, $this->admin);
    }

    public function test_iniciar_desligamento_recusa_data_anterior_a_admissao(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não pode ser anterior à data de admissão (02/02/2026)');

        app(ProcessoFuncionarioService::class)->iniciar(
            $funcionario,
            TipoProcessoFuncionario::Desligamento,
            '2026-01-31',
            $this->admin,
            MotivoDesligamentoFuncionario::PedidoDemissao
        );
    }

    public function test_concluir_desligamento_encerra_contrato_e_desfaz_tudo_se_falhar(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $contrato = $this->servico()->abrirContratoInicial($funcionario, 3000, $this->admin);

        $processo = app(ProcessoFuncionarioService::class)->iniciar(
            $funcionario,
            TipoProcessoFuncionario::Desligamento,
            '2026-10-20',
            $this->admin,
            MotivoDesligamentoFuncionario::PedidoDemissao
        );

        foreach ($processo->itens()->where('obrigatorio', true)->get() as $item) {
            app(ProcessoFuncionarioService::class)->marcarItem($item, $this->admin);
        }

        $resumo = app(ProcessoFuncionarioService::class)->concluir($processo, $this->admin);

        $this->assertSame(1, $resumo['contratos_encerrados']);
        $this->assertSame('2026-10-20', $funcionario->fresh()->data_desligamento->toDateString());
        $this->assertSame('2026-10-20', $contrato->fresh()->vigencia_fim->toDateString());
    }

    // --- ContratoTrabalho: registrarAditivo --------------------------------------------------------------------------

    public function test_registrar_aditivo_recusado_para_desligado_ou_fora_da_vigencia(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $contrato = $this->servico()->abrirContratoInicial($funcionario, 2000, $this->admin);

        // Data do aditivo anterior ao início
        try {
            $contrato->registrarAditivo('2500', '2026-01-10');
            $this->fail('aditivo antes do início');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não pode ser anterior ao início', $e->getMessage());
        }

        // Aditivo para desligado
        $funcionario->update(['data_desligamento' => '2026-09-01']);
        try {
            $contrato->registrarAditivo('2500', '2026-08-01');
            $this->fail('aditivo para desligado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Não é possível registrar aditivo para funcionário desligado.', $e->getMessage());
        }
    }

    // --- Telas Livewire / Filament -----------------------------------------------------------------------------------

    public function test_edicao_funcionario_datas_somente_exibicao_e_forjar_desligamento_nao_altera_banco(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02', 'data_desligamento' => null]);

        $component = Livewire::test(EditFuncionario::class, ['record' => $funcionario->getRouteKey()]);

        // Não deve haver inputs editáveis para data_desligamento
        $component->assertFormFieldDoesNotExist('data_desligamento');

        // Tenta submeter data_desligamento forjada
        $component->fillForm([
            'cargo' => 'Coordenadora Geral',
            'data_desligamento' => '2026-05-01',
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Coordenadora Geral', $funcionario->fresh()->cargo);
        $this->assertNull($funcionario->fresh()->data_desligamento, 'data_desligamento não deve ser alterada pelo form');
    }

    public function test_criacao_funcionario_cria_contrato_inicial_quando_salario_informado(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::test(CreateFuncionario::class)
            ->fillForm([
                'pessoa_id' => $pessoa->id,
                'cargo' => 'Secretário Escolar',
                'regime' => 'clt',
                'data_admissao' => '2026-03-01',
                'salario_inicial' => '3200.00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $funcionario = Funcionario::query()->where('pessoa_id', $pessoa->id)->firstOrFail();
        $this->assertNull($funcionario->data_desligamento);

        $contrato = $funcionario->contratosTrabalho()->first();
        $this->assertNotNull($contrato);
        $this->assertSame('2026-03-01', $contrato->vigencia_inicio->toDateString());
        $this->assertSame('3200.00', $contrato->salario);
    }

    public function test_criacao_funcionario_sem_salario_nao_cria_contrato(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::test(CreateFuncionario::class)
            ->fillForm([
                'pessoa_id' => $pessoa->id,
                'cargo' => 'Auxiliar',
                'regime' => 'clt',
                'data_admissao' => '2026-03-01',
                'salario_inicial' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $funcionario = Funcionario::query()->where('pessoa_id', $pessoa->id)->firstOrFail();
        $this->assertCount(0, $funcionario->contratosTrabalho);
    }

    public function test_acao_corrigir_admissao_visivel_para_ativo_e_oculta_para_desligado_inclusive_super_admin(): void
    {
        $ativo = $this->funcionario();
        $desligado = $this->funcionario(['data_desligamento' => '2026-09-01']);

        // Admin em funcionário ativo: ação visível
        Livewire::test(EditFuncionario::class, ['record' => $ativo->getRouteKey()])
            ->assertActionVisible('corrigir_admissao');

        // Admin em funcionário desligado: ação oculta
        Livewire::test(EditFuncionario::class, ['record' => $desligado->getRouteKey()])
            ->assertActionHidden('corrigir_admissao');

        // Super Admin em funcionário desligado: ação oculta
        $superAdmin = $this->usuario('super_admin');
        Livewire::actingAs($this->como($superAdmin))
            ->test(EditFuncionario::class, ['record' => $desligado->getRouteKey()])
            ->assertActionHidden('corrigir_admissao');
    }

    public function test_acao_corrigir_admissao_executa_e_atualiza(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-02-02']);
        $contrato = $this->servico()->abrirContratoInicial($funcionario, 3000, $this->admin);

        Livewire::test(EditFuncionario::class, ['record' => $funcionario->getRouteKey()])
            ->callAction('corrigir_admissao', data: [
                'nova_data' => '2026-01-10',
                'justificativa' => 'Retificação oficial solicitada pelo RH com CTPS',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('2026-01-10', $funcionario->fresh()->data_admissao->toDateString());
        $this->assertSame('2026-01-10', $contrato->fresh()->vigencia_inicio->toDateString());
    }

    public function test_aba_contratos_descricao_e_acoes_bloqueadas_para_desligado(): void
    {
        $desligado = $this->funcionario(['data_admissao' => '2026-02-02', 'data_desligamento' => '2026-09-01']);
        $contrato = ContratoTrabalho::factory()->create([
            'funcionario_id' => $desligado->id,
            'vigencia_inicio' => '2026-02-02',
            'vigencia_fim' => '2026-09-01',
            'salario' => 2000,
        ]);

        Livewire::test(ContratosTrabalhoRelationManager::class, [
            'ownerRecord' => $desligado,
            'pageClass' => EditFuncionario::class,
        ])
            ->assertSee('Admissão: 02/02/2026 · Desligamento: 01/09/2026')
            ->assertActionHidden(TestAction::make('create')->table())
            ->assertTableActionHidden('aditivo', $contrato)
            ->assertTableActionHidden('delete', $contrato);
    }

    public function test_aba_processos_descricao_e_botoes_ocultos_para_desligado(): void
    {
        $desligado = $this->funcionario(['data_admissao' => '2026-02-02', 'data_desligamento' => '2026-09-01']);

        Livewire::test(ProcessosRelationManager::class, [
            'ownerRecord' => $desligado,
            'pageClass' => EditFuncionario::class,
        ])
            ->assertSee('Admissão: 02/02/2026 · Desligamento: 01/09/2026')
            ->assertActionHidden(TestAction::make('iniciar_admissao')->table())
            ->assertActionHidden(TestAction::make('iniciar_desligamento')->table());
    }

    // --- Comando rh:auditar-vinculo ---------------------------------------------------------------------------------

    public function test_comando_auditar_sem_corrigir_apenas_relata_categorias(): void
    {
        // A e E: Desligado com contrato vigente sem fim e sem processo formal
        $f1 = $this->funcionario(['data_admissao' => '2026-02-02', 'data_desligamento' => '2026-08-01']);
        $c1 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $f1->id,
            'vigencia_inicio' => '2026-02-02',
            'vigencia_fim' => null,
            'salario' => 2000,
        ]);

        // B: 1ª vigência diferente da admissão
        $f2 = $this->funcionario(['data_admissao' => '2026-02-02']);
        $c2 = ContratoTrabalho::factory()->create([
            'funcionario_id' => $f2->id,
            'vigencia_inicio' => '2026-03-01',
            'vigencia_fim' => null,
            'salario' => 2500,
        ]);

        // C: Sem nenhum contrato
        $f3 = $this->funcionario();

        $this->artisan('rh:auditar-vinculo')
            ->expectsOutputToContain('Auditoria concluída')
            ->assertSuccessful();

        $this->assertNull($c1->fresh()->vigencia_fim, 'sem --corrigir nada é alterado');
    }

    public function test_comando_auditar_com_corrigir_em_modo_nao_interativo_recusa(): void
    {
        $this->artisan('rh:auditar-vinculo', ['--corrigir' => true, '--no-interaction' => true])
            ->expectsOutputToContain('A opção --corrigir não pode ser executada em modo não-interativo')
            ->assertFailed();
    }
}
