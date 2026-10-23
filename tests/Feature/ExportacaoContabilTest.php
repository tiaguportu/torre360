<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FormatoExportacaoContabil;
use App\Enums\RegimeContabil;
use App\Filament\Pages\ExportacaoContabil;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\LoteContabil;
use App\Models\Matricula;
use App\Models\ParametroContabil;
use App\Models\Pessoa;
use App\Models\PlanoConta;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\User;
use App\Services\Contabil\ExportacaoContabilService;
use App\Services\Pendencias\Fontes\ClassificacaoContabilPendente;
use App\Support\Contabil\LancamentoContabil;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Exportação para a contabilidade (Onda 73): partidas dobradas nos dois regimes, pendências de classificação que impedem o
 * arquivo, os leiautes, o histórico com a impressão digital, o download e a fila da Mesa de pendências.
 *
 * "Hoje" é quinta-feira, 05/11/2026; o mês de referência é outubro de 2026.
 */
class ExportacaoContabilTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaria;

    private Banco $banco;

    private PlanoConta $mensalidades;

    private PlanoConta $material;

    private TransacaoBancaria $recebimento;

    private TransacaoBancaria $pagamentoDoMaterial;

    private TransacaoBancaria $pagamentoAvulso;

    private Fatura $fatura;

    private ContaPagar $contaPagar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-05 09:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['View:ExportacaoContabil', 'Exportar:ExportacaoContabil'] as $permissao) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $this->admin = $this->usuario('admin');
        $this->secretaria = $this->usuario('secretaria');

        $this->mensalidades = PlanoConta::create(['codigo' => '1.1', 'codigo_contabil' => '4.1.01.001', 'nome' => 'Mensalidades', 'tipo' => 'receita']);
        $this->material = PlanoConta::create(['codigo' => '2.1', 'codigo_contabil' => '5.1.02.007', 'nome' => 'Material de escritório', 'tipo' => 'despesa']);
        $this->banco = Banco::create(['nome' => 'Itaú - conta corrente', 'conta_contabil' => '1.1.01.002', 'is_active' => true]);

        ParametroContabil::create([
            'conta_clientes' => '1.1.02.001',
            'conta_fornecedores' => '2.1.01.001',
            'plano_conta_receita_id' => $this->mensalidades->id,
            'plano_conta_despesa_id' => $this->material->id,
        ]);

        // A fatura tem o nome do aluno por trás; o arquivo não pode vazá-lo.
        $aluno = Pessoa::create(['nome' => 'Fulano Secreto da Silva']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'turma_id' => Turma::factory()->create()->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
        $this->fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-10-10', 'status' => 'pendente']);
        ItemFatura::create(['fatura_id' => $this->fatura->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => 1000, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        $cancelada = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-10-15', 'status' => 'cancelado']);
        ItemFatura::create(['fatura_id' => $cancelada->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => 777, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        $this->recebimento = $this->transacao('entrada', 1000, '2026-10-05', 'Baixa — Fatura', null, $this->fatura->id);
        $this->pagamentoDoMaterial = $this->transacao('saida', 300, '2026-10-10', 'Papel A4 e toner', $this->material->id);
        $this->pagamentoAvulso = $this->transacao('saida', 150, '2026-10-12', 'Pagamento sem classificação');
        $this->transacao('saida', 80, '2026-09-30', 'Fora do período', $this->material->id);

        $this->contaPagar = ContaPagar::factory()->create([
            'descricao' => 'Papel A4 e toner',
            'valor' => 300,
            'vencimento' => '2026-10-08',
            'plano_conta_id' => $this->material->id,
            'transacao_bancaria_id' => $this->pagamentoDoMaterial->id,
            'status' => 'pago',
        ]);
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

    private function transacao(string $tipo, float $valor, string $data, string $descricao, ?int $planoId = null, ?int $faturaId = null): TransacaoBancaria
    {
        return TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'fatura_id' => $faturaId,
            'plano_conta_id' => $planoId,
            'tipo' => $tipo,
            'valor' => $valor,
            'data_transacao' => $data,
            'descricao' => $descricao,
            'conciliado' => true,
        ]);
    }

    private function servico(): ExportacaoContabilService
    {
        return app(ExportacaoContabilService::class);
    }

    private function apurar(RegimeContabil $regime = RegimeContabil::Caixa): array
    {
        return $this->servico()->apurar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), $regime);
    }

    /**
     * Os lançamentos como texto "débito > crédito = valor", para comparar o conjunto sem depender da ordem.
     *
     * @param  list<LancamentoContabil>  $lancamentos
     * @return list<string>
     */
    private function resumo(array $lancamentos): array
    {
        $linhas = array_map(fn ($l): string => "{$l->contaDebito} > {$l->contaCredito} = ".number_format($l->valor, 2, '.', '').' ['.$l->origem.']', $lancamentos);
        sort($linhas);

        return $linhas;
    }

    // --- Regime de caixa ---------------------------------------------------------------------------------------------

    public function test_caixa_gera_um_lancamento_por_movimento_do_periodo_com_a_conta_padrao_no_que_nao_foi_classificado(): void
    {
        $apuracao = $this->apurar();

        $this->assertSame([], $apuracao['pendencias']);
        $this->assertSame([
            '1.1.01.002 > 4.1.01.001 = 1000.00 [banco]',
            '5.1.02.007 > 1.1.01.002 = 150.00 [banco]',
            '5.1.02.007 > 1.1.01.002 = 300.00 [banco]',
        ], $this->resumo($apuracao['lancamentos']));
        $this->assertSame(3, $apuracao['resumo']['quantidade']);
        $this->assertSame(1450.0, $apuracao['resumo']['total']);
        $this->assertSame(['banco' => 3], $apuracao['resumo']['por_origem']);
    }

    public function test_lancamento_leva_data_historico_documento_e_so_a_referencia_da_fatura(): void
    {
        $recebimento = collect($this->apurar()['lancamentos'])->first(fn ($l): bool => $l->valor === 1000.0);

        $this->assertSame('2026-10-05', $recebimento->data->toDateString());
        $this->assertSame("FAT-{$this->fatura->id}", $recebimento->documento);
        $this->assertSame('Baixa — Fatura', $recebimento->historico);
    }

    public function test_movimento_zerado_ou_fora_do_periodo_nao_entra(): void
    {
        $this->transacao('entrada', 0, '2026-10-20', 'Zerado');
        $this->transacao('entrada', 55, '2026-11-01', 'Novembro');

        $this->assertSame(3, $this->apurar()['resumo']['quantidade']);
    }

    // --- Regime de competência ---------------------------------------------------------------------------------------

    public function test_competencia_leva_fatura_e_conta_a_pagar_e_o_banco_baixa_clientes_e_fornecedores(): void
    {
        $apuracao = $this->apurar(RegimeContabil::Competencia);

        $this->assertSame([], $apuracao['pendencias']);
        // O resumo vem ordenado como texto.
        $this->assertSame([
            // recebimento da fatura: o banco baixa clientes
            '1.1.01.002 > 1.1.02.001 = 1000.00 [banco]',
            // fatura emitida: clientes contra receita (a cancelada não entra)
            '1.1.02.001 > 4.1.01.001 = 1000.00 [fatura]',
            // pagamento da conta a pagar: o banco baixa fornecedores
            '2.1.01.001 > 1.1.01.002 = 300.00 [banco]',
            // pagamento sem conta a pagar: despesa direta pela conta padrão
            '5.1.02.007 > 1.1.01.002 = 150.00 [banco]',
            // conta a pagar: despesa contra fornecedores
            '5.1.02.007 > 2.1.01.001 = 300.00 [conta_a_pagar]',
        ], $this->resumo($apuracao['lancamentos']));
        $this->assertSame(['banco' => 3, 'conta_a_pagar' => 1, 'fatura' => 1], $apuracao['resumo']['por_origem']);
        $this->assertSame(2750.0, $apuracao['resumo']['total']);
    }

    public function test_conta_a_pagar_cancelada_nao_entra_na_competencia(): void
    {
        $this->contaPagar->update(['status' => 'cancelado']);

        $this->assertNotContains('5.1.02.007 > 2.1.01.001 = 300.00 [conta_a_pagar]', $this->resumo($this->apurar(RegimeContabil::Competencia)['lancamentos']));
    }

    public function test_no_regime_de_caixa_fatura_e_conta_a_pagar_nao_geram_lancamento(): void
    {
        $origens = array_keys($this->apurar(RegimeContabil::Caixa)['resumo']['por_origem']);

        $this->assertSame(['banco'], $origens);
    }

    // --- Pendências --------------------------------------------------------------------------------------------------

    public function test_banco_sem_conta_contabil_e_pendencia_e_trava_o_arquivo(): void
    {
        $this->banco->update(['conta_contabil' => null]);

        $apuracao = $this->apurar();

        $this->assertCount(1, $apuracao['pendencias']);
        $this->assertStringContainsString('Banco sem a conta contábil', $apuracao['pendencias'][0]['mensagem']);
        $this->assertSame(3, $apuracao['pendencias'][0]['quantidade']);
        $this->assertSame(['Itaú - conta corrente'], $apuracao['pendencias'][0]['exemplos']);

        try {
            $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);
            $this->fail('com pendência o arquivo não pode sair');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('3 pendência(s)', $e->getMessage());
        }

        $this->assertSame(0, LoteContabil::count());
        $this->assertSame([], Storage::disk('local')->allFiles('exportacoes_contabeis'));
    }

    public function test_conta_do_plano_sem_codigo_do_escritorio_nao_cai_na_conta_padrao_em_silencio(): void
    {
        $this->material->update(['codigo_contabil' => null]);

        $pendencias = $this->apurar()['pendencias'];
        $chaves = array_column($pendencias, 'chave');

        $this->assertContains("plano_{$this->material->id}", $chaves, 'a conta explícita sem código é pendência');
        $this->assertStringContainsString('2.1 - Material de escritório', implode(' ', array_merge(...array_column($pendencias, 'exemplos'))));
    }

    public function test_sem_conta_padrao_o_movimento_nao_classificado_e_pendencia(): void
    {
        ParametroContabil::query()->update(['plano_conta_despesa_id' => null]);

        $pendencias = $this->apurar()['pendencias'];

        $this->assertSame(['sem_padrao_despesa'], array_column($pendencias, 'chave'));
        $this->assertSame(1, $pendencias[0]['quantidade']);
    }

    public function test_competencia_exige_as_contas_de_clientes_e_de_fornecedores(): void
    {
        ParametroContabil::query()->update(['conta_clientes' => null, 'conta_fornecedores' => null]);

        $chaves = array_column($this->apurar(RegimeContabil::Competencia)['pendencias'], 'chave');

        $this->assertContains('sem_conta_clientes', $chaves);
        $this->assertContains('sem_conta_fornecedores', $chaves);

        // No caixa essas duas contas não são usadas.
        $this->assertSame([], $this->apurar(RegimeContabil::Caixa)['pendencias']);
    }

    public function test_plano_de_natureza_trocada_e_aviso_e_nao_trava(): void
    {
        $this->transacao('entrada', 40, '2026-10-20', 'Estorno', $this->material->id);

        $apuracao = $this->apurar();

        $this->assertSame([], $apuracao['pendencias']);
        $this->assertCount(1, $apuracao['avisos']);
        $this->assertStringContainsString('Conta de despesa usada em movimento de receita', $apuracao['avisos'][0]['mensagem']);
    }

    // --- Arquivos ----------------------------------------------------------------------------------------------------

    public function test_exportar_guarda_o_arquivo_o_lote_e_a_impressao_digital(): void
    {
        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);

        $this->assertSame(3, $lote->lancamentos);
        $this->assertSame('1450.00', (string) $lote->total);
        $this->assertSame($this->admin->id, $lote->gerado_por_user_id);
        Storage::disk('local')->assertExists($lote->arquivo_path);

        $conteudo = Storage::disk('local')->get($lote->arquivo_path);
        $this->assertSame(hash('sha256', $conteudo), $lote->arquivo_hash);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo, 'BOM para o Excel');
        $this->assertSame('contabilidade-20261001-a-20261031-caixa.csv', $lote->nomeDoArquivo());

        $linhas = array_values(array_filter(explode("\n", $conteudo)));
        $this->assertSame("\xEF\xBB\xBFData;\"Conta débito\";\"Conta crédito\";Valor;Histórico;Documento;\"Centro de custo\";Origem", $linhas[0]);
        $this->assertContains("05/10/2026;1.1.01.002;4.1.01.001;1000,00;\"Baixa — Fatura\";FAT-{$this->fatura->id};;banco", $linhas);
        $this->assertContains('10/10/2026;5.1.02.007;1.1.01.002;300,00;"Papel A4 e toner";MOV-'.$this->pagamentoDoMaterial->id.';;banco', $linhas);
    }

    public function test_arquivo_nao_leva_nome_de_aluno_nem_de_responsavel(): void
    {
        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Competencia, FormatoExportacaoContabil::CsvLancamentos, $this->admin);

        $this->assertStringNotContainsString('Fulano', Storage::disk('local')->get($lote->arquivo_path));
    }

    public function test_texto_do_extrato_nao_vira_formula_na_planilha(): void
    {
        $this->transacao('saida', 25, '2026-10-21', '=HYPERLINK("http://mal.example","clique")', $this->material->id);

        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);

        $conteudo = Storage::disk('local')->get($lote->arquivo_path);

        $this->assertStringContainsString("'=HYPERLINK", $conteudo);
        $this->assertStringNotContainsString(';"=HYPERLINK', $conteudo);
    }

    public function test_balancete_soma_debitos_e_creditos_por_conta_com_o_saldo_do_lado_que_sobra(): void
    {
        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvBalancete, $this->admin);

        $linhas = array_values(array_filter(explode("\n", Storage::disk('local')->get($lote->arquivo_path))));

        $this->assertStringContainsString('Conta;"Total a débito";"Total a crédito";"Saldo devedor";"Saldo credor"', $linhas[0]);
        // Banco: entrou 1000, saíram 450 → saldo devedor de 550.
        $this->assertContains('1.1.01.002;1000,00;450,00;550,00;0,00', $linhas);
        $this->assertContains('4.1.01.001;0,00;1000,00;0,00;1000,00', $linhas);
        $this->assertContains('5.1.02.007;450,00;0,00;450,00;0,00', $linhas);
    }

    public function test_balancete_fecha_o_total_a_debito_com_o_total_a_credito(): void
    {
        $arquivo = $this->servico()->gerarArquivo($this->apurar(RegimeContabil::Competencia)['lancamentos'], FormatoExportacaoContabil::CsvBalancete);

        $debito = 0.0;
        $credito = 0.0;

        foreach (array_slice(array_values(array_filter(explode("\n", $arquivo))), 1) as $linha) {
            $colunas = explode(';', $linha);
            $debito += (float) str_replace(',', '.', $colunas[1]);
            $credito += (float) str_replace(',', '.', $colunas[2]);
        }

        $this->assertEqualsWithDelta($debito, $credito, 0.001);
        $this->assertEqualsWithDelta(2750.0, $debito, 0.001);
    }

    public function test_periodo_invalido_ou_vazio_e_recusado(): void
    {
        $exportar = fn (string $inicio, string $fim) => $this->servico()->exportar(Carbon::parse($inicio), Carbon::parse($fim), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);

        foreach ([['2026-10-31', '2026-10-01', 'anterior'], ['2025-01-01', '2026-12-31', 'passa de um ano'], ['2026-07-01', '2026-07-31', 'Não há lançamentos']] as [$inicio, $fim, $trecho]) {
            try {
                $exportar($inicio, $fim);
                $this->fail("o período {$inicio} a {$fim} devia ser recusado");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }
    }

    public function test_lote_de_periodo_sobreposto_no_mesmo_regime_e_apontado(): void
    {
        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);

        $this->assertSame([$lote->id], array_map(fn ($l) => $l->id, $this->servico()->lotesQueSeSobrepoem(Carbon::parse('2026-10-15'), Carbon::parse('2026-11-15'), RegimeContabil::Caixa)));
        $this->assertSame([], $this->servico()->lotesQueSeSobrepoem(Carbon::parse('2026-10-15'), Carbon::parse('2026-11-15'), RegimeContabil::Competencia));
        $this->assertSame([], $this->servico()->lotesQueSeSobrepoem(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), RegimeContabil::Caixa));
    }

    // --- Download ----------------------------------------------------------------------------------------------------

    public function test_download_entrega_o_arquivo_a_quem_exporta_e_nega_a_quem_nao(): void
    {
        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);

        $this->como($this->admin);
        $this->actingAs($this->admin)->get(route('financeiro.exportacao-contabil.download', $lote))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="contabilidade-20261001-a-20261031-caixa.csv"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->como($this->secretaria);
        $this->actingAs($this->secretaria)->get(route('financeiro.exportacao-contabil.download', $lote))->assertForbidden();
    }

    public function test_download_recusa_arquivo_alterado_ou_ausente(): void
    {
        $lote = $this->servico()->exportar(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), RegimeContabil::Caixa, FormatoExportacaoContabil::CsvLancamentos, $this->admin);
        $this->como($this->admin);

        Storage::disk('local')->put($lote->arquivo_path, "adulterado\n");
        $this->actingAs($this->admin)->get(route('financeiro.exportacao-contabil.download', $lote))->assertStatus(409);

        Storage::disk('local')->delete($lote->arquivo_path);
        $this->actingAs($this->admin)->get(route('financeiro.exportacao-contabil.download', $lote))->assertNotFound();
    }

    // --- Tela e Mesa -------------------------------------------------------------------------------------------------

    public function test_tela_abre_no_mes_anterior_e_so_para_quem_pode_ver(): void
    {
        Livewire::actingAs($this->como($this->admin))
            ->test(ExportacaoContabil::class)
            ->assertSuccessful()
            ->assertSet('dataInicio', '2026-10-01')
            ->assertSet('dataFim', '2026-10-31')
            ->assertSee('Lançamentos')
            ->assertActionVisible('exportar')
            ->assertActionVisible('parametros');

        $this->actingAs($this->como($this->secretaria));
        $this->assertFalse(ExportacaoContabil::canAccess());

        $this->actingAs($this->como($this->admin));
        $this->assertTrue(ExportacaoContabil::canAccess());
    }

    public function test_tela_mostra_o_que_falta_classificar(): void
    {
        $this->banco->update(['conta_contabil' => null]);

        Livewire::actingAs($this->como($this->admin))
            ->test(ExportacaoContabil::class)
            ->assertSee('O que falta classificar')
            ->assertSee('Banco sem a conta contábil do escritório')
            ->assertSee('Itaú - conta corrente');
    }

    public function test_botao_gera_o_arquivo_e_registra_no_historico(): void
    {
        Livewire::actingAs($this->como($this->admin))
            ->test(ExportacaoContabil::class)
            ->callAction('exportar')
            ->assertNotified('Arquivo gerado')
            ->assertSee('Arquivos gerados');

        $this->assertSame(1, LoteContabil::count());
    }

    public function test_botao_avisa_a_pendencia_em_vez_de_gerar(): void
    {
        $this->banco->update(['conta_contabil' => null]);

        Livewire::actingAs($this->como($this->admin))
            ->test(ExportacaoContabil::class)
            ->callAction('exportar')
            ->assertNotified('Não foi possível gerar o arquivo');

        $this->assertSame(0, LoteContabil::count());
    }

    public function test_parametros_sao_salvos_pela_tela(): void
    {
        Livewire::actingAs($this->como($this->admin))
            ->test(ExportacaoContabil::class)
            ->callAction('parametros', [
                'conta_clientes' => '1.1.02.099',
                'conta_fornecedores' => '2.1.01.099',
                'plano_conta_receita_id' => $this->mensalidades->id,
                'plano_conta_despesa_id' => $this->material->id,
            ])
            ->assertHasNoActionErrors();

        $parametros = ParametroContabil::atual();
        $this->assertSame('1.1.02.099', $parametros->conta_clientes);
        $this->assertSame('2.1.01.099', $parametros->conta_fornecedores);
        $this->assertSame(1, ParametroContabil::count(), 'a configuração é uma linha só');
    }

    public function test_fila_da_mesa_conta_as_pendencias_do_mes_anterior_e_atrasa_depois_do_dia_limite(): void
    {
        $fonte = new ClassificacaoContabilPendente;
        Cache::flush();

        $this->assertSame(0, $fonte->contar($this->admin));

        $this->banco->update(['conta_contabil' => null]);
        $fonte->esquecer();

        $this->assertSame(3, $fonte->contar($this->admin));
        $this->assertSame(0, $fonte->atrasados($this->admin), 'dia 5: ainda dentro do prazo do escritório');
        $this->assertSame('View:ExportacaoContabil', $fonte->permissao());

        $this->travelTo(Carbon::parse('2026-11-12 09:00:00'));
        $this->assertSame(3, $fonte->atrasados($this->admin));

        $itens = $fonte->itens($this->admin, 5);
        $this->assertCount(1, $itens);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertStringContainsString('Banco sem a conta contábil', $itens[0]->titulo);
    }
}
