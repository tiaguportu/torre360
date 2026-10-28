<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\FontePendencia;
use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\StatusCancelamentoMatricula;
use App\Enums\StatusDocumentoAssinatura;
use App\Enums\StatusRematricula;
use App\Enums\TipoProcessoFuncionario;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Pages\MesaDePendencias as PaginaDaMesa;
use App\Models\CancelamentoMatricula;
use App\Models\Contrato;
use App\Models\DocumentoAssinatura;
use App\Models\Funcionario;
use App\Models\ItemProcessoFuncionario;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\TemplateDocumento;
use App\Models\TransferenciaEscolar;
use App\Models\Turma;
use App\Models\User;
use App\Services\CancelamentoMatriculaService;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\GradeAssistidaService;
use App\Services\Pendencias\Fontes\AssinaturasAtrasadas;
use App\Services\Pendencias\Fontes\CancelamentosParaAnalisar;
use App\Services\Pendencias\Fontes\CancelamentosParaConcluir;
use App\Services\Pendencias\Fontes\DevolucoesPendentes;
use App\Services\Pendencias\Fontes\DocumentosParaAssinatura;
use App\Services\Pendencias\Fontes\GradesIncompletas;
use App\Services\Pendencias\Fontes\ProvidenciasRhVencidas;
use App\Services\Pendencias\Fontes\RematriculasAguardandoTurma;
use App\Services\Pendencias\Fontes\TransferenciasEmAndamento;
use App\Services\Pendencias\MesaDePendencias;
use App\Services\ProcessoFuncionarioService;
use App\Support\Pendencias\ItemPendencia;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Mesa de pendências por papel (Onda 71): as filas, o que cada papel enxerga, os prazos, o selo do menu e a tela.
 *
 * "Hoje" é quinta-feira, 22/10/2026.
 */
class MesaDePendenciasTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;

    private User $admin;

    private User $professor;

    private int $sequencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
        Storage::fake('local');
        Cache::flush();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel', 'aluno'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        // A secretaria resolve tudo da escola, menos o RH.
        foreach ([
            'Aprovar:CancelamentoMatricula', 'Concluir:CancelamentoMatricula', 'Enviar:DocumentoAssinatura', 'View:DocumentoAssinatura',
            'ViewAny:DocumentoAssinatura', 'Create:DocumentoAssinatura', 'Cancelar:DocumentoAssinatura', 'Update:Rematricula',
            'Update:TransferenciaEscolar', 'GradeAssistida:Turma',
        ] as $permissao) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        // A administração soma o RH.
        foreach (['ViewAny:ProcessoFuncionario', 'View:ProcessoFuncionario', 'Create:ProcessoFuncionario', 'Update:ProcessoFuncionario', 'Concluir:ProcessoFuncionario'] as $permissao) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        Role::findByName('admin')->syncPermissions(Role::findByName('secretaria')->permissions->merge(Role::findByName('admin')->permissions));

        $this->secretaria = $this->usuario('secretaria');
        $this->admin = $this->usuario('admin');
        $this->professor = $this->usuario('professor');
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

    private function mesa(): MesaDePendencias
    {
        return app(MesaDePendencias::class);
    }

    private function matricula(string $nome): Matricula
    {
        $matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => $nome, 'email' => 'p'.(++$this->sequencia).'@teste.com'])->id,
            'turma_id' => Turma::factory()->create()->id,
            'situacao' => 'ativa',
        ]);
        Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);

        return $matricula;
    }

    /**
     * Um pedido de cancelamento no estado pedido, mexendo nas datas direto no banco (o que interessa aqui são as filas).
     *
     * @param  array<string, mixed>  $atributos
     */
    private function pedido(string $nome, StatusCancelamentoMatricula $status, array $atributos = []): CancelamentoMatricula
    {
        $pedido = app(CancelamentoMatriculaService::class)->solicitar($this->matricula($nome), [
            'motivo' => MotivoCancelamentoMatricula::MudancaCidade,
            'data_efetiva' => '2026-10-31',
        ], null, OrigemCancelamentoMatricula::Escola);

        $pedido->forceFill(['status' => $status->value] + $atributos)->save();

        return $pedido->refresh();
    }

    // --- Cadastro das filas ------------------------------------------------------------------------------------------

    public function test_toda_fila_cadastrada_se_descreve_por_inteiro(): void
    {
        $fontes = $this->mesa()->fontes();

        $this->assertNotEmpty($fontes);
        $this->assertCount(count($fontes), array_unique(array_map(fn (FontePendencia $f): string => $f->chave(), $fontes)), 'as chaves são únicas');

        foreach ($fontes as $fonte) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $fonte->chave());
            $this->assertNotSame('', $fonte->titulo());
            $this->assertNotSame('', $fonte->area());
            $this->assertNotSame('', $fonte->orientacao());
            $this->assertMatchesRegularExpression('/^[A-Za-z]+:[A-Za-z]+$/', $fonte->permissao(), "{$fonte->chave()} usa uma permissão no formato Acao:Entidade");
            $this->assertStringStartsWith('heroicon-', $fonte->icone());
            $this->assertStringStartsWith('http', $fonte->url(), "{$fonte->chave()} tem uma tela onde resolver");
        }
    }

    public function test_as_permissoes_das_filas_existem_no_sistema_ou_sao_criadas_por_migration(): void
    {
        // As permissões das filas de Onda 61/65/69 são criadas por migration; as demais, pelo Shield dos recursos.
        $criadasPorMigration = Permission::query()->pluck('name')->all();

        foreach (['Aprovar:CancelamentoMatricula', 'Concluir:CancelamentoMatricula', 'Enviar:DocumentoAssinatura', 'View:DocumentoAssinatura', 'ViewAny:ProcessoFuncionario'] as $permissao) {
            $this->assertContains($permissao, $criadasPorMigration, "falta a permissão {$permissao}");
        }
    }

    // --- Quem enxerga o quê ------------------------------------------------------------------------------------------

    public function test_cada_papel_so_enxerga_as_filas_que_pode_resolver(): void
    {
        $this->como($this->secretaria);
        $daSecretaria = array_map(fn (FontePendencia $f): string => $f->chave(), $this->mesa()->fontesDe($this->secretaria));

        $this->assertContains('cancelamentos_para_analisar', $daSecretaria);
        $this->assertContains('documentos_para_assinatura', $daSecretaria);
        $this->assertContains('grades_incompletas', $daSecretaria);
        $this->assertNotContains('providencias_rh_vencidas', $daSecretaria, 'RH é da administração');

        $this->como($this->admin);
        $daAdmin = array_map(fn (FontePendencia $f): string => $f->chave(), $this->mesa()->fontesDe($this->admin));

        $this->assertContains('providencias_rh_vencidas', $daAdmin);
        $this->assertContains('cancelamentos_para_analisar', $daAdmin);

        $this->como($this->professor);
        $this->assertSame([], $this->mesa()->fontesDe($this->professor));
        $this->assertFalse($this->mesa()->temAlgumaFila($this->professor));
    }

    public function test_o_papel_ativo_manda_e_nao_a_soma_dos_papeis_do_usuario(): void
    {
        $this->secretaria->assignRole('professor');

        $this->como($this->secretaria);
        $this->assertTrue($this->mesa()->temAlgumaFila($this->secretaria));

        session(['active_role' => 'professor']);
        $this->assertFalse($this->mesa()->temAlgumaFila($this->secretaria), 'atuando como professor, a pessoa não vê as filas da secretaria');
    }

    public function test_pagina_nao_abre_para_quem_nao_tem_fila(): void
    {
        $this->actingAs($this->como($this->professor))->get('/admin/minha-mesa')->assertForbidden();
        $this->actingAs($this->como($this->secretaria))->get('/admin/minha-mesa')->assertOk();
    }

    // --- Pedidos de cancelamento ------------------------------------------------------------------------------------

    public function test_pedidos_para_analisar_contam_so_os_solicitados_e_marcam_o_atraso(): void
    {
        $velho = $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado, ['created_at' => now()->subDays(5)]);
        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Solicitado);
        $this->pedido('Caio Dias', StatusCancelamentoMatricula::Aprovado);

        $fonte = new CancelamentosParaAnalisar;

        $this->assertSame(2, $fonte->contar($this->secretaria));
        $this->assertSame(1, $fonte->atrasados($this->secretaria));

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertSame(['Ana Lima', 'Bia Souza'], array_map(fn (ItemPendencia $i): string => $i->titulo, $itens), 'o mais antigo primeiro');
        $this->assertTrue($itens[0]->atrasado);
        $this->assertFalse($itens[1]->atrasado);
        $this->assertStringContainsString('saída em 31/10/2026', $itens[0]->detalhe);
        $this->assertNotNull($velho);
    }

    public function test_limite_de_itens_e_respeitado(): void
    {
        foreach (['A', 'B', 'C'] as $letra) {
            $this->pedido("Aluno {$letra}", StatusCancelamentoMatricula::Solicitado);
        }

        $this->assertCount(2, (new CancelamentosParaAnalisar)->itens($this->secretaria, 2));
        $this->assertSame(3, (new CancelamentosParaAnalisar)->contar($this->secretaria));
    }

    public function test_cancelamentos_prontos_para_concluir_sao_os_aprovados_com_a_saida_chegada(): void
    {
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Aprovado, ['data_efetiva' => '2026-10-20']);
        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Aprovado, ['data_efetiva' => '2026-10-22']);
        $this->pedido('Caio Dias', StatusCancelamentoMatricula::Aprovado, ['data_efetiva' => '2026-10-31']);
        $this->pedido('Duda Reis', StatusCancelamentoMatricula::Solicitado, ['data_efetiva' => '2026-10-20']);

        $fonte = new CancelamentosParaConcluir;

        $this->assertSame(2, $fonte->contar($this->secretaria), 'a saída de hoje conta; a futura e a não aprovada não');
        $this->assertSame(1, $fonte->atrasados($this->secretaria), 'só a que já passou do último dia');

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertSame(['Ana Lima', 'Bia Souza'], array_map(fn (ItemPendencia $i): string => $i->titulo, $itens));
        $this->assertStringContainsString('falta o distrato assinado', $itens[0]->detalhe);
    }

    public function test_devolucoes_pendentes_so_contam_concluidos_com_valor_e_sem_devolucao_registrada(): void
    {
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 450, 'concluido_em' => now()->subDays(15)]);
        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 300, 'concluido_em' => now()->subDays(2)]);
        $this->pedido('Caio Dias', StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 300, 'concluido_em' => now()->subDays(20), 'devolucao_realizada_em' => '2026-10-10']);
        $this->pedido('Duda Reis', StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 0, 'concluido_em' => now()->subDays(20)]);
        $this->pedido('Edu Lima', StatusCancelamentoMatricula::Aprovado, ['valor_a_devolver' => 100]);

        $fonte = new DevolucoesPendentes;

        $this->assertSame(2, $fonte->contar($this->secretaria));
        $this->assertSame(1, $fonte->atrasados($this->secretaria), 'mais de 10 dias sem devolver');

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertSame('Ana Lima', $itens[0]->titulo);
        $this->assertStringContainsString('Devolver R$ 450,00', $itens[0]->detalhe);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertFalse($itens[1]->atrasado);
    }

    public function test_o_escopo_de_devolucao_pendente_concorda_com_a_regra_do_modelo(): void
    {
        $pendente = $this->pedido('Ana Lima', StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 450]);
        $feita = $this->pedido('Bia Souza', StatusCancelamentoMatricula::Concluido, ['valor_a_devolver' => 450, 'devolucao_realizada_em' => '2026-10-10']);

        $ids = CancelamentoMatricula::query()->comDevolucaoPendente()->pluck('id')->all();

        $this->assertSame([$pendente->id], $ids);
        $this->assertTrue($pendente->devolucaoPendente());
        $this->assertFalse($feita->devolucaoPendente());
    }

    // --- Documentos para assinatura ---------------------------------------------------------------------------------

    private function documento(string $nome, ?StatusDocumentoAssinatura $status = null, array $atributos = [], bool $deRh = false): DocumentoAssinatura
    {
        $servico = app(AssinaturaDocumentoService::class);

        if ($deRh) {
            $funcionario = Funcionario::factory()->create(['pessoa_id' => Pessoa::factory()->create(['nome' => $nome, 'email' => 'f'.(++$this->sequencia).'@teste.com'])->id]);
            $modelo = TemplateDocumento::create(['nome' => 'Termo '.$this->sequencia, 'tipo' => TipoTemplateDocumento::TermoConfidencialidade, 'conteudo' => '<p>{{FUNCIONARIO_NOME}}</p>', 'validade_dias' => 0, 'is_ativo' => true]);
            $documento = $servico->criar($modelo, $funcionario, [['nome' => $nome, 'email' => $funcionario->pessoa->email]], $this->admin);
        } else {
            $modelo = TemplateDocumento::create(['nome' => 'Declaração '.$this->sequencia, 'tipo' => TipoTemplateDocumento::Personalizado, 'conteudo' => '<p>{{ALUNO_NOME}}</p>', 'validade_dias' => 0, 'is_ativo' => true]);
            $documento = $servico->criar($modelo, $this->matricula($nome), [['nome' => 'Mãe de '.$nome, 'email' => 'm'.(++$this->sequencia).'@teste.com']], $this->secretaria);
        }

        if ($status || $atributos) {
            $documento->forceFill(($status ? ['status' => $status->value] : []) + $atributos)->save();
        }

        return $documento->refresh();
    }

    public function test_documentos_para_enviar_sao_os_rascunhos_e_os_envios_com_erro(): void
    {
        $this->como($this->secretaria);
        $this->documento('Ana Lima', StatusDocumentoAssinatura::Erro);
        $this->documento('Bia Souza', null, ['created_at' => now()->subDays(4)]);
        $this->documento('Caio Dias');
        $this->documento('Duda Reis', StatusDocumentoAssinatura::Enviado);
        $this->documento('Edu Lima', StatusDocumentoAssinatura::Cancelado);

        $fonte = new DocumentosParaAssinatura;

        $this->assertSame(3, $fonte->contar($this->secretaria));
        $this->assertSame(2, $fonte->atrasados($this->secretaria), 'o erro e o rascunho parado há mais de 2 dias');

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertStringContainsString('Ana Lima', $itens[0]->titulo, 'o envio com erro vem primeiro');
        $this->assertStringContainsString('deu erro', $itens[0]->detalhe);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertTrue($itens[1]->atrasado);
        $this->assertFalse($itens[2]->atrasado);
        $this->assertStringContainsString('ainda não enviado', $itens[2]->detalhe);
    }

    public function test_assinaturas_atrasadas_sao_as_enviadas_ha_muito_ou_com_prazo_acabando(): void
    {
        $this->como($this->secretaria);
        $this->documento('Ana Lima', StatusDocumentoAssinatura::Enviado, ['enviado_em' => now()->subDays(10)]);
        $this->documento('Bia Souza', StatusDocumentoAssinatura::Enviado, ['enviado_em' => now()->subDay(), 'expira_em' => '2026-10-24']);
        $this->documento('Caio Dias', StatusDocumentoAssinatura::Enviado, ['enviado_em' => now()->subDay(), 'expira_em' => '2026-11-30']);
        $this->documento('Duda Reis', StatusDocumentoAssinatura::Enviado, ['enviado_em' => now()->subDays(2)]);
        $this->documento('Edu Lima', StatusDocumentoAssinatura::Assinado, ['enviado_em' => now()->subDays(20)]);

        $fonte = new AssinaturasAtrasadas;

        $this->assertSame(2, $fonte->contar($this->secretaria));
        $this->assertSame(2, $fonte->atrasados($this->secretaria), 'toda a fila é atraso');

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertStringContainsString('Ana Lima', $itens[0]->titulo);
        $this->assertStringContainsString('0 de 1 assinaram', $itens[0]->detalhe);
        $this->assertStringContainsString('prazo até 24/10/2026', $itens[1]->detalhe);
    }

    public function test_documento_de_rh_so_aparece_para_quem_ve_admissao_e_desligamento(): void
    {
        $this->documento('Marta Souza', StatusDocumentoAssinatura::Erro, deRh: true);
        $this->documento('Ana Lima', StatusDocumentoAssinatura::Erro);

        $this->como($this->secretaria);
        $this->assertSame(1, (new DocumentosParaAssinatura)->contar($this->secretaria));

        $this->como($this->admin);
        $this->assertSame(2, (new DocumentosParaAssinatura)->contar($this->admin));
    }

    // --- Rematrículas, transferências, RH e grades -------------------------------------------------------------------

    public function test_rematriculas_aguardando_turma_seguem_o_escopo_do_modulo(): void
    {
        $origem = PeriodoLetivo::create(['nome' => 'Origem 2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $destino = PeriodoLetivo::create(['nome' => 'Destino 2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027', 'periodo_letivo_origem_id' => $origem->id, 'periodo_letivo_destino_id' => $destino->id,
            'valor_taxa' => 1200, 'quantidade_parcelas_padrao' => 4, 'valor_entrada_padrao' => 0,
            'data_inicio' => '2026-10-01', 'data_fim' => '2026-11-30', 'is_ativo' => true,
        ]);

        $nova = function (string $nome, StatusRematricula $status, ?CarbonInterface $atualizada = null) use ($campanha): Rematricula {
            $rematricula = Rematricula::create([
                'periodo_rematricula_id' => $campanha->id,
                'matricula_origem_id' => $this->matricula($nome)->id,
                'solicitante_user_id' => $this->secretaria->id,
                'status' => $status->value,
            ]);

            if ($atualizada) {
                Rematricula::query()->whereKey($rematricula->id)->toBase()->update(['updated_at' => $atualizada]);
            }

            return $rematricula;
        };

        $nova('Ana Lima', StatusRematricula::DadosConfirmados, now()->subDays(8));
        $nova('Bia Souza', StatusRematricula::DadosConfirmados);
        $nova('Caio Dias', StatusRematricula::Iniciada);
        $nova('Duda Reis', StatusRematricula::Confirmada);

        $fonte = new RematriculasAguardandoTurma;

        $this->assertSame(Rematricula::query()->aguardandoTurma()->count(), $fonte->contar($this->secretaria), 'a mesma contagem do selo do menu de Rematrículas');
        $this->assertSame(2, $fonte->contar($this->secretaria));
        $this->assertSame(1, $fonte->atrasados($this->secretaria));
        $this->assertSame('Ana Lima', $fonte->itens($this->secretaria, 5)[0]->titulo);
        $this->assertStringContainsString('Rematrícula 2027', $fonte->itens($this->secretaria, 5)[0]->detalhe);
    }

    public function test_transferencias_em_andamento_marcam_as_que_passaram_da_data(): void
    {
        TransferenciaEscolar::factory()->create(['matricula_id' => $this->matricula('Ana Lima')->id, 'tipo' => 'saida', 'escola_externa_nome' => 'Colégio Aurora', 'data' => '2026-10-15']);
        TransferenciaEscolar::factory()->create(['matricula_id' => $this->matricula('Bia Souza')->id, 'tipo' => 'entrada', 'escola_externa_nome' => 'Escola Mar', 'data' => '2026-10-30', 'historico_recebido' => false]);
        TransferenciaEscolar::factory()->create(['matricula_id' => $this->matricula('Caio Dias')->id, 'status' => 'concluida', 'data' => '2026-10-01']);
        TransferenciaEscolar::factory()->create(['matricula_id' => $this->matricula('Duda Reis')->id, 'status' => 'cancelada', 'data' => '2026-10-01']);

        $fonte = new TransferenciasEmAndamento;

        $this->assertSame(2, $fonte->contar($this->secretaria));
        $this->assertSame(1, $fonte->atrasados($this->secretaria));

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertSame('Ana Lima', $itens[0]->titulo);
        $this->assertStringContainsString('Saída para Colégio Aurora', $itens[0]->detalhe);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertStringContainsString('Entrada vinda de Escola Mar', $itens[1]->detalhe);
        $this->assertStringContainsString('falta o histórico', $itens[1]->detalhe);
    }

    public function test_providencias_de_rh_vencidas_vem_do_escopo_do_processo(): void
    {
        $funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza'])->id,
            'data_admissao' => '2026-10-10',
        ]);
        $this->como($this->admin);
        $processo = app(ProcessoFuncionarioService::class)->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-10-10', $this->admin);

        $fonte = new ProvidenciasRhVencidas;

        $this->assertSame(ItemProcessoFuncionario::query()->atrasados()->count(), $fonte->contar($this->admin), 'a mesma contagem do selo do menu de Admissão e Desligamento');
        $this->assertGreaterThan(0, $fonte->contar($this->admin));
        $this->assertSame($fonte->contar($this->admin), $fonte->atrasados($this->admin));

        $item = $fonte->itens($this->admin, 1)[0];

        $this->assertStringContainsString('Marta Souza', $item->detalhe);
        $this->assertStringContainsString('de atraso', $item->detalhe);
        $this->assertStringContainsString((string) $processo->id, (string) $item->url, 'leva ao processo');

        // Processo cancelado sai da fila.
        app(ProcessoFuncionarioService::class)->cancelar($processo, $this->admin);
        $this->assertSame(0, $fonte->contar($this->admin));
    }

    public function test_grades_incompletas_usam_o_periodo_em_curso_e_a_visao_geral_da_grade(): void
    {
        PeriodoLetivo::create(['nome' => '2025', 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-15']);
        $atual = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $completa = Turma::factory()->create(['nome' => '6º A', 'periodo_letivo_id' => $atual->id]);
        $faltando = Turma::factory()->create(['nome' => '7º A', 'periodo_letivo_id' => $atual->id]);
        $semTempos = Turma::factory()->create(['nome' => '8º A', 'periodo_letivo_id' => $atual->id]);

        $this->mock(GradeAssistidaService::class, function ($mock) use ($atual, $completa, $faltando, $semTempos): void {
            $mock->shouldReceive('visaoGeral')
                ->withArgs(fn (PeriodoLetivo $periodo): bool => $periodo->id === $atual->id)
                ->andReturn(collect([
                    ['turma' => $completa, 'sem_tempos' => false, 'esperadas' => 30, 'alocadas' => 30, 'faltam' => 0, 'excedem' => 0, 'fora_da_matriz' => 0],
                    ['turma' => $faltando, 'sem_tempos' => false, 'esperadas' => 30, 'alocadas' => 22, 'faltam' => 8, 'excedem' => 0, 'fora_da_matriz' => 0],
                    ['turma' => $semTempos, 'sem_tempos' => true, 'esperadas' => 30, 'alocadas' => 0, 'faltam' => 30, 'excedem' => 0, 'fora_da_matriz' => 0],
                ]));
        });

        $fonte = new GradesIncompletas;

        $this->assertSame(2, $fonte->contar($this->secretaria));
        $this->assertSame(2, $fonte->atrasados($this->secretaria), 'o período já começou');

        $itens = $fonte->itens($this->secretaria, 5);

        $this->assertSame(['7º A', '8º A'], array_map(fn (ItemPendencia $i): string => $i->titulo, $itens));
        $this->assertStringContainsString('Faltam 8 de 30 aulas', $itens[0]->detalhe);
        $this->assertStringContainsString('não tem tempos de aula', $itens[1]->detalhe);
        $this->assertStringContainsString('turma='.$faltando->id, $itens[0]->url);
    }

    public function test_grade_de_periodo_que_ainda_nao_comecou_nao_e_atraso(): void
    {
        $futuro = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $turma = Turma::factory()->create(['nome' => '7º A', 'periodo_letivo_id' => $futuro->id]);

        $this->mock(GradeAssistidaService::class, function ($mock) use ($turma): void {
            $mock->shouldReceive('visaoGeral')->andReturn(collect([
                ['turma' => $turma, 'sem_tempos' => false, 'esperadas' => 30, 'alocadas' => 10, 'faltam' => 20, 'excedem' => 0, 'fora_da_matriz' => 0],
            ]));
        });

        $fonte = new GradesIncompletas;

        $this->assertSame(1, $fonte->contar($this->secretaria));
        $this->assertSame(0, $fonte->atrasados($this->secretaria));
        $this->assertFalse($fonte->itens($this->secretaria, 5)[0]->atrasado);
    }

    public function test_sem_periodo_letivo_a_fila_de_grades_fica_vazia(): void
    {
        $this->assertSame(0, (new GradesIncompletas)->contar($this->secretaria));
    }

    public function test_grades_incompletas_ficam_guardadas_para_todos_os_usuarios_ate_atualizar(): void
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::factory()->create(['nome' => '7º A', 'periodo_letivo_id' => $periodo->id]);
        $linha = ['turma' => $turma, 'sem_tempos' => false, 'esperadas' => 30, 'alocadas' => 22, 'faltam' => 8, 'excedem' => 0, 'fora_da_matriz' => 0];

        // A visão geral é cara (consulta turma a turma): só é calculada de novo depois de "Atualizar".
        $this->mock(GradeAssistidaService::class, function ($mock) use ($linha): void {
            $mock->shouldReceive('visaoGeral')->twice()->andReturn(collect([$linha]), collect([$linha, $linha]));
        });

        $this->como($this->secretaria);
        $this->assertSame(1, (new GradesIncompletas)->contar($this->secretaria), 'a primeira consulta calcula');
        $this->assertSame(1, (new GradesIncompletas)->contar($this->admin), 'outro usuário reaproveita o resultado guardado');

        $this->mesa()->esquecer($this->secretaria);

        $this->assertSame(2, (new GradesIncompletas)->contar($this->secretaria), 'depois de atualizar, calcula de novo');
    }

    // --- A Mesa ------------------------------------------------------------------------------------------------------

    public function test_a_mesa_junta_as_filas_do_papel_com_total_e_atrasados(): void
    {
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado, ['created_at' => now()->subDays(5)]);
        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Aprovado, ['data_efetiva' => '2026-10-20']);

        $filas = $this->mesa()->filas($this->como($this->secretaria));

        $porChave = $filas->keyBy(fn ($fila) => $fila->fonte->chave());

        $this->assertSame(1, $porChave['cancelamentos_para_analisar']->total);
        $this->assertSame(1, $porChave['cancelamentos_para_analisar']->atrasados);
        $this->assertSame(1, $porChave['cancelamentos_para_concluir']->total);
        $this->assertSame(0, $porChave['devolucoes_pendentes']->total);
        $this->assertSame([], $porChave['devolucoes_pendentes']->itens, 'fila vazia não consulta os itens');
        $this->assertFalse($porChave['devolucoes_pendentes']->temItens());
        $this->assertSame(0, $porChave['cancelamentos_para_analisar']->restantes());
    }

    public function test_uma_fila_que_falha_nao_derruba_a_mesa(): void
    {
        $quebrada = new class implements FontePendencia
        {
            public function chave(): string
            {
                return 'fila_quebrada';
            }

            public function titulo(): string
            {
                return 'Fila quebrada';
            }

            public function area(): string
            {
                return 'Teste';
            }

            public function icone(): string
            {
                return 'heroicon-o-bug-ant';
            }

            public function orientacao(): string
            {
                return 'Nada a fazer.';
            }

            public function permissao(): string
            {
                return 'Aprovar:CancelamentoMatricula';
            }

            public function url(): string
            {
                return 'https://exemplo.test';
            }

            public function contar(User $usuario): int
            {
                throw new \RuntimeException('banco indisponível');
            }

            public function atrasados(User $usuario): int
            {
                return 0;
            }

            public function itens(User $usuario, int $limite): array
            {
                return [];
            }
        };

        $this->app->instance($quebrada::class, $quebrada);
        config(['mesa_pendencias.fontes' => [$quebrada::class, CancelamentosParaAnalisar::class]]);
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado);

        $filas = $this->mesa()->filas($this->como($this->secretaria));

        $this->assertCount(2, $filas);
        $this->assertTrue($filas[0]->erro);
        $this->assertSame(0, $filas[0]->total);
        $this->assertFalse($filas[1]->erro);
        $this->assertSame(1, $filas[1]->total);
    }

    /**
     * O fabricante de turmas dos testes cria o período letivo padrão, e turma sem tempos de aula já é grade incompleta:
     * os testes que contam o total isolam a fila de pedidos.
     */
    private function somenteFilaDePedidos(): void
    {
        config(['mesa_pendencias.fontes' => [CancelamentosParaAnalisar::class]]);
    }

    public function test_resumo_do_selo_fica_guardado_por_usuario_e_papel_ate_atualizar(): void
    {
        $this->somenteFilaDePedidos();
        $this->como($this->secretaria);
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado, ['created_at' => now()->subDays(5)]);

        $this->assertSame(['total' => 1, 'atrasados' => 1], $this->mesa()->resumo($this->secretaria));

        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Solicitado);
        $this->assertSame(['total' => 1, 'atrasados' => 1], $this->mesa()->resumo($this->secretaria), 'ainda a contagem guardada');

        $this->mesa()->esquecer($this->secretaria);
        $this->assertSame(['total' => 2, 'atrasados' => 1], $this->mesa()->resumo($this->secretaria));

        // O outro usuário e o outro papel têm a sua própria contagem.
        $this->como($this->admin);
        $this->assertSame(2, $this->mesa()->resumo($this->admin)['total']);
    }

    // --- A tela ------------------------------------------------------------------------------------------------------

    public function test_a_tela_mostra_as_filas_agrupadas_por_area_com_os_itens_e_o_que_esta_em_dia(): void
    {
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado, ['created_at' => now()->subDays(5)]);
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $letra) {
            $this->pedido("Aluno {$letra}", StatusCancelamentoMatricula::Solicitado);
        }

        Livewire::actingAs($this->como($this->secretaria))
            ->test(PaginaDaMesa::class)
            ->assertSuccessful()
            ->assertSee('Secretaria')
            ->assertSee('Pedidos de cancelamento para analisar')
            ->assertSee('Ana Lima')
            ->assertSee('atrasado')
            ->assertSee('e mais 2')
            ->assertSee('Abrir a fila')
            ->assertSee('Em dia:')
            ->assertSee('Devoluções a fazer')
            ->assertDontSee('Providências de RH vencidas');
    }

    public function test_a_tela_sem_pendencia_diz_que_esta_tudo_em_dia(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(PaginaDaMesa::class)
            ->assertSee('Nada esperando ação sua agora')
            ->assertSee('Em dia:');
    }

    public function test_a_tela_avisa_da_fila_que_nao_carregou_e_mostra_as_outras(): void
    {
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado);

        $this->mock(GradeAssistidaService::class, function ($mock): void {
            $mock->shouldReceive('visaoGeral')->andThrow(new \RuntimeException('falha'));
        });
        PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(PaginaDaMesa::class)
            ->assertSee('Ana Lima')
            ->assertSee('Não foi possível carregar')
            ->assertSee('Grades horárias incompletas');
    }

    public function test_o_selo_do_menu_mostra_os_atrasados_em_vermelho_ou_o_total_em_amarelo(): void
    {
        $this->somenteFilaDePedidos();
        $this->actingAs($this->como($this->secretaria));

        $this->assertNull(PaginaDaMesa::getNavigationBadge());

        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado);
        $this->mesa()->esquecer($this->secretaria);

        $this->assertSame('1', PaginaDaMesa::getNavigationBadge());
        $this->assertSame('warning', PaginaDaMesa::getNavigationBadgeColor());

        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Solicitado, ['created_at' => now()->subDays(6)]);
        $this->mesa()->esquecer($this->secretaria);

        $this->assertSame('1', PaginaDaMesa::getNavigationBadge(), 'passa a contar só os atrasados');
        $this->assertSame('danger', PaginaDaMesa::getNavigationBadgeColor());
        $this->assertStringContainsString('2 item(ns) esperando ação, 1 atrasado(s)', (string) PaginaDaMesa::getNavigationBadgeTooltip());
    }

    public function test_o_botao_atualizar_esquece_a_contagem_guardada(): void
    {
        $this->somenteFilaDePedidos();
        $this->como($this->secretaria);
        $this->pedido('Ana Lima', StatusCancelamentoMatricula::Solicitado);
        $this->mesa()->resumo($this->secretaria);
        $this->pedido('Bia Souza', StatusCancelamentoMatricula::Solicitado);

        Livewire::actingAs($this->secretaria)->test(PaginaDaMesa::class)->callAction('atualizar');

        $this->assertSame(2, $this->mesa()->resumo($this->secretaria)['total']);
    }

    public function test_o_selo_e_a_pagina_somem_para_quem_nao_tem_fila(): void
    {
        $this->actingAs($this->como($this->professor));

        $this->assertNull(PaginaDaMesa::getNavigationBadge());
        $this->assertNull(PaginaDaMesa::getNavigationBadgeTooltip());
        $this->assertFalse(PaginaDaMesa::canAccess());
    }
}
