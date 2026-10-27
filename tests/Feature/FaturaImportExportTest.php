<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Exports\FaturaExporter;
use App\Filament\Imports\FaturaImporter;
use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Contratos\RelationManagers\FaturasRelationManager;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TransacaoBancaria;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaturaImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria uma instância de FaturaImporter simulando o estado da linha importada.
     */
    private function createImporterInstance(array $data, array $options = []): FaturaImporter
    {
        $import = new Import;
        $import->id = 1;
        $import->total_rows = 1;
        $import->processed_rows = 0;
        $import->successful_rows = 0;

        $importer = new FaturaImporter($import, [], $options);

        $reflection = new ReflectionClass(FaturaImporter::class);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);
        $property->setValue($importer, $data);

        return $importer;
    }

    private function createContratoComAluno(array $contratoAttributes = []): Contrato
    {
        $aluno = Pessoa::factory()->create(['nome' => 'Carlos Drummond']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        return Contrato::create(array_merge([
            'matricula_id' => $matricula->id,
            'valor_total' => 5000.00,
            'data_aceite' => now()->toDateString(),
        ], $contratoAttributes));
    }

    public function test_importacao_cria_fatura_e_item_com_diferentes_formatos_e_ignora_contrato_da_planilha(): void
    {
        $contratoAlvo = $this->createContratoComAluno();
        $outroContrato = $this->createContratoComAluno();

        // Linha 1: valor 1234.56, data d/m/Y, contrato_id da planilha apontando para outro contrato
        $importer1 = $this->createImporterInstance([
            'contrato_id' => $outroContrato->id,
            'vencimento' => '15/03/2026',
            'descricao' => 'Mensalidade Março',
            'valor' => '1234.56',
            'desconto' => '50.00',
            'tipo_desconto' => 'R$',
            'status' => 'pendente',
        ], ['contrato_id' => $contratoAlvo->id]);

        $importer1([
            'contrato_id' => $outroContrato->id,
            'vencimento' => '15/03/2026',
            'descricao' => 'Mensalidade Março',
            'valor' => '1234.56',
            'desconto' => '50.00',
            'tipo_desconto' => 'R$',
            'status' => 'pendente',
        ]);

        $fatura1 = Fatura::where('contrato_id', $contratoAlvo->id)->first();
        $this->assertNotNull($fatura1);
        $this->assertEquals('2026-03-15', $fatura1->vencimento->toDateString());
        $this->assertEquals(StatusFatura::Pendente, $fatura1->status);
        $this->assertCount(1, $fatura1->itens);

        $item1 = $fatura1->itens->first();
        $this->assertEquals('Mensalidade Março', $item1->descricao);
        $this->assertEquals(1234.56, (float) $item1->valor_unitario);
        $this->assertEquals(1.0, (float) $item1->quantidade);
        $this->assertEquals(50.0, (float) $item1->desconto);
        $this->assertEquals('absoluto', $item1->tipo_desconto);

        // Faturas no outro contrato não foram criadas
        $this->assertDatabaseMissing('faturas', ['contrato_id' => $outroContrato->id]);

        // Linha 2: valor brasileiro 1.234,56 e data Y-m-d
        $importer2 = $this->createImporterInstance([
            'vencimento' => '2026-04-15',
            'descricao' => 'Mensalidade Abril',
            'valor' => '1.234,56',
            'desconto' => '10',
            'tipo_desconto' => '%',
            'status' => 'Atrasado',
        ], ['contrato_id' => $contratoAlvo->id]);

        $importer2([
            'vencimento' => '2026-04-15',
            'descricao' => 'Mensalidade Abril',
            'valor' => '1.234,56',
            'desconto' => '10',
            'tipo_desconto' => '%',
            'status' => 'Atrasado',
        ]);

        $fatura2 = Fatura::where('contrato_id', $contratoAlvo->id)->whereDate('vencimento', '2026-04-15')->first();
        $this->assertNotNull($fatura2);
        $this->assertEquals(StatusFatura::Atrasado, $fatura2->status);

        $item2 = $fatura2->itens->first();
        $this->assertEquals(1234.56, (float) $item2->valor_unitario);
        $this->assertEquals('relativo', $item2->tipo_desconto);
    }

    public function test_status_pago_parcial_e_invalido_sao_rejeitados_e_pendente_atrasado_cancelado_passam(): void
    {
        $contrato = $this->createContratoComAluno();

        // 1. Pago é rejeitado
        $importerPago = $this->createImporterInstance([
            'vencimento' => '10/05/2026',
            'descricao' => 'Mensalidade Maio',
            'valor' => 1000,
            'status' => 'pago',
        ], ['contrato_id' => $contrato->id]);

        $this->expectException(RowImportFailedException::class);
        $this->expectExceptionMessage("O status 'pago' não é permitido na importação");
        $importerPago->resolveRecord();
    }

    public function test_status_parcial_e_rejeitado(): void
    {
        $contrato = $this->createContratoComAluno();

        $importerParcial = $this->createImporterInstance([
            'vencimento' => '10/05/2026',
            'descricao' => 'Mensalidade Maio',
            'valor' => 1000,
            'status' => 'parcial',
        ], ['contrato_id' => $contrato->id]);

        $this->expectException(RowImportFailedException::class);
        $this->expectExceptionMessage("O status 'parcial' não é permitido na importação");
        $importerParcial->resolveRecord();
    }

    public function test_status_invalido_e_rejeitado(): void
    {
        $contrato = $this->createContratoComAluno();

        $importerInvalido = $this->createImporterInstance([
            'vencimento' => '10/05/2026',
            'descricao' => 'Mensalidade Maio',
            'valor' => 1000,
            'status' => 'outro_status_qualquer',
        ], ['contrato_id' => $contrato->id]);

        $this->expectException(RowImportFailedException::class);
        $this->expectExceptionMessage("Status 'outro_status_qualquer' inválido");
        $importerInvalido->resolveRecord();
    }

    public function test_atualizacao_por_id_funciona_sem_transacoes_e_falha_com_salvaguardas(): void
    {
        $contrato = $this->createContratoComAluno();
        $outroContrato = $this->createContratoComAluno();

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-05-10',
            'status' => StatusFatura::Pendente,
        ]);

        $item = ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Parcela Inicial',
            'valor_unitario' => 500.00,
            'quantidade' => 1,
            'desconto' => 0,
            'tipo_desconto' => 'absoluto',
        ]);

        // 1. Atualização bem-sucedida sem transação
        $importerOk = $this->createImporterInstance([
            'id' => $fatura->id,
            'vencimento' => '20/05/2026',
            'descricao' => 'Parcela Atualizada',
            'valor' => '600.00',
            'desconto' => '10.00',
            'tipo_desconto' => 'absoluto',
            'status' => 'atrasado',
        ], ['contrato_id' => $contrato->id]);

        $importerOk([
            'id' => $fatura->id,
            'vencimento' => '20/05/2026',
            'descricao' => 'Parcela Atualizada',
            'valor' => '600.00',
            'desconto' => '10.00',
            'tipo_desconto' => 'absoluto',
            'status' => 'atrasado',
        ]);

        $fatura->refresh();
        $this->assertEquals('2026-05-20', $fatura->vencimento->toDateString());
        $this->assertEquals(StatusFatura::Atrasado, $fatura->status);
        $this->assertEquals('Parcela Atualizada', $fatura->itens->first()->descricao);
        $this->assertEquals(600.00, (float) $fatura->itens->first()->valor_unitario);

        // 2. Salvaguarda: Falha se fatura possui transação
        $banco = Banco::create(['nome' => 'Banco Principal', 'agencia' => '001', 'conta' => '123']);
        TransacaoBancaria::create([
            'banco_id' => $banco->id,
            'fatura_id' => $fatura->id,
            'tipo' => 'entrada',
            'valor' => 100.00,
            'data_transacao' => now()->toDateString(),
            'descricao' => 'Recebimento Parcial',
        ]);

        $importerComTransacao = $this->createImporterInstance([
            'id' => $fatura->id,
            'vencimento' => '25/05/2026',
            'descricao' => 'Mudança bloqueada',
            'valor' => '700.00',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerComTransacao->resolveRecord();
            $this->fail('Deveria ter falhado pois possui transações.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('possui transações bancárias', $e->getMessage());
        }

        // Remove transação para testar próxima salvaguarda
        $fatura->transacoes()->delete();

        // 3. Salvaguarda: Falha se fatura possui gateway_id
        $fatura->update(['gateway_id' => 'gw_99999']);
        $importerComGateway = $this->createImporterInstance([
            'id' => $fatura->id,
            'vencimento' => '25/05/2026',
            'descricao' => 'Mudança bloqueada',
            'valor' => '700.00',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerComGateway->resolveRecord();
            $this->fail('Deveria ter falhado pois possui gateway_id.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('possui integração com gateway', $e->getMessage());
        }

        $fatura->update(['gateway_id' => null]);

        // 4. Salvaguarda: Falha se fatura pertence a outro contrato
        $importerOutroContrato = $this->createImporterInstance([
            'id' => $fatura->id,
            'vencimento' => '25/05/2026',
            'descricao' => 'Mudança',
            'valor' => '700.00',
        ], ['contrato_id' => $outroContrato->id]);

        try {
            $importerOutroContrato->resolveRecord();
            $this->fail('Deveria ter falhado pois a fatura pertence a outro contrato.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('não pertence ao contrato da tela', $e->getMessage());
        }

        // 5. Salvaguarda: Falha se fatura possui mais de 1 item
        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Segundo Item',
            'valor_unitario' => 100.00,
            'quantidade' => 1,
        ]);

        $importerMaisDeUmItem = $this->createImporterInstance([
            'id' => $fatura->id,
            'vencimento' => '25/05/2026',
            'descricao' => 'Mudança',
            'valor' => '700.00',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerMaisDeUmItem->resolveRecord();
            $this->fail('Deveria ter falhado pois a fatura possui mais de 1 item.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('possui 2 itens', $e->getMessage());
        }
    }

    public function test_atualizacao_sem_mudancas_e_aceita_sem_gravar(): void
    {
        $contrato = $this->createContratoComAluno();

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-06-10',
            'status' => StatusFatura::Pendente,
        ]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade Junho',
            'valor_unitario' => 800.00,
            'quantidade' => 1,
            'desconto' => 0.00,
            'tipo_desconto' => 'absoluto',
        ]);

        // Simula data idêntica, valor idêntico, status idêntico
        $importer = $this->createImporterInstance([
            'id' => $fatura->id,
            'vencimento' => '10/06/2026',
            'descricao' => 'Mensalidade Junho',
            'valor' => '800.00',
            'desconto' => '0.00',
            'tipo_desconto' => 'absoluto',
            'status' => 'Pendente',
        ], ['contrato_id' => $contrato->id]);

        $resolved = $importer->resolveRecord();
        $this->assertEquals($fatura->id, $resolved->id);

        $importer([
            'id' => $fatura->id,
            'vencimento' => '10/06/2026',
            'descricao' => 'Mensalidade Junho',
            'valor' => '800.00',
            'desconto' => '0.00',
            'tipo_desconto' => 'absoluto',
            'status' => 'Pendente',
        ]);

        $this->assertEquals(800.00, (float) $fatura->fresh()->itens->first()->valor_unitario);
    }

    public function test_duplicidade_e_dados_invalidos_falham_com_mensagem_clara(): void
    {
        $contrato = $this->createContratoComAluno();

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-07-15',
            'status' => StatusFatura::Pendente,
        ]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Taxa de Material',
            'valor_unitario' => 350.00,
            'quantidade' => 1,
        ]);

        // 1. Duplicidade (mesmo vencimento, descrição e valor para o mesmo contrato)
        $importerDuplicado = $this->createImporterInstance([
            'vencimento' => '15/07/2026',
            'descricao' => 'Taxa de Material',
            'valor' => '350.00',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerDuplicado->resolveRecord();
            $this->fail('Deveria ter falhado por duplicidade.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('Fatura idêntica já existe', $e->getMessage());
        }

        // 2. Data estrita inválida (31/02/2026)
        $importerDataInvalida = $this->createImporterInstance([
            'vencimento' => '31/02/2026',
            'descricao' => 'Data inexistente',
            'valor' => '100.00',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerDataInvalida->resolveRecord();
            $this->fail('Deveria ter falhado por data inválida.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('Data de vencimento inválida', $e->getMessage());
        }

        // 3. Valor inválido
        $importerValorInvalido = $this->createImporterInstance([
            'vencimento' => '10/08/2026',
            'descricao' => 'Valor lixo',
            'valor' => 'invalido_123',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerValorInvalido->resolveRecord();
            $this->fail('Deveria ter falhado por valor inválido.');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('Valor inválido', $e->getMessage());
        }
    }

    public function test_contrato_enviado_reseta_assinafy_ao_importar_mas_linha_com_falha_nao_reseta(): void
    {
        $contrato = $this->createContratoComAluno([
            'assinafy_id' => 'assinafy_doc_12345',
            'assinafy_status' => 'enviado',
            'assinafy_request_log' => ['url' => 'https://api.assinafy.com.br'],
        ]);

        $this->assertTrue($contrato->jaEnviadoAssinafy());

        // 1. Linha com falha NÃO reseta
        $importerFalha = $this->createImporterInstance([
            'vencimento' => '31/02/2026',
            'descricao' => 'Fatura com erro',
            'valor' => '100',
        ], ['contrato_id' => $contrato->id]);

        try {
            $importerFalha([
                'vencimento' => '31/02/2026',
                'descricao' => 'Fatura com erro',
                'valor' => '100',
            ]);
        } catch (RowImportFailedException) {
            // Esperado
        }

        $contrato->refresh();
        $this->assertTrue($contrato->jaEnviadoAssinafy(), 'Contrato não deve ser resetado se a linha falhou.');

        // 2. Linha válida com sucesso reseta a assinatura
        $importerSucesso = $this->createImporterInstance([
            'vencimento' => '10/09/2026',
            'descricao' => 'Fatura Válida',
            'valor' => '250.00',
        ], ['contrato_id' => $contrato->id]);

        $importerSucesso([
            'vencimento' => '10/09/2026',
            'descricao' => 'Fatura Válida',
            'valor' => '250.00',
        ]);

        $contrato->refresh();
        $this->assertFalse($contrato->jaEnviadoAssinafy(), 'Contrato deve ter sido resetado após importação com sucesso.');
        $this->assertNull($contrato->assinafy_id);
        $this->assertEquals('pending', $contrato->assinafy_status);
    }

    public function test_exportacao_traz_apenas_faturas_do_contrato_com_valores_e_sanitizacao(): void
    {
        $contratoA = $this->createContratoComAluno();
        $contratoB = $this->createContratoComAluno();

        // Aluno com fórmula potencial para testar CsvSanitizer
        $alunoMalicioso = Pessoa::factory()->create(['nome' => '=Soma(1+1)']);
        $matriculaA = Matricula::factory()->create(['pessoa_id' => $alunoMalicioso->id]);
        $contratoA->update(['matricula_id' => $matriculaA->id]);

        $faturaA1 = Fatura::create([
            'contrato_id' => $contratoA->id,
            'vencimento' => '2026-10-15',
            'status' => StatusFatura::Pendente,
        ]);
        ItemFatura::create([
            'fatura_id' => $faturaA1->id,
            'descricao' => '=FormulaNoItem',
            'valor_unitario' => 1200.00,
            'quantidade' => 1,
            'desconto' => 200.00,
            'tipo_desconto' => 'absoluto',
        ]);

        $faturaB = Fatura::create([
            'contrato_id' => $contratoB->id,
            'vencimento' => '2026-10-20',
            'status' => StatusFatura::Pendente,
        ]);
        ItemFatura::create([
            'fatura_id' => $faturaB->id,
            'descricao' => 'Fatura Contrato B',
            'valor_unitario' => 500.00,
            'quantidade' => 1,
        ]);

        // Simula exportação das faturas do contrato A
        $query = Fatura::where('contrato_id', $contratoA->id);
        $query = FaturaExporter::modifyQuery($query);
        $faturasExportadas = $query->get();

        $this->assertCount(1, $faturasExportadas);
        $this->assertEquals($faturaA1->id, $faturasExportadas->first()->id);

        // Executa exporter nas colunas
        $export = new Export;
        $exporter = new FaturaExporter($export, array_combine(
            array_map(fn ($c) => $c->getName(), FaturaExporter::getColumns()),
            array_map(fn ($c) => $c->getName(), FaturaExporter::getColumns())
        ), []);

        $row = $exporter($faturaA1);

        // Colunas: id, aluno, vencimento, descricao, valor, desconto, tipo_desconto, status, valor_a_pagar, valor_pago, saldo
        $this->assertEquals($faturaA1->id, $row[0]);
        $this->assertEquals("'=Soma(1+1)", $row[1]); // Sanitizado
        $this->assertEquals('15/10/2026', $row[2]);
        $this->assertEquals("'=FormulaNoItem", $row[3]); // Sanitizado
        $this->assertEquals('1200.00', $row[4]); // Valor bruto
        $this->assertEquals('200.00', $row[5]); // Desconto
        $this->assertEquals('absoluto', $row[6]); // Tipo desconto
        $this->assertEquals('Pendente', $row[7]); // Status formatado
        $this->assertEquals('1000.00', $row[8]); // Valor a pagar (líquido)
        $this->assertEquals('0.00', $row[9]); // Valor pago
        $this->assertEquals('1000.00', $row[10]); // Saldo
    }

    public function test_exportacao_eager_loading_evita_n_mais_um(): void
    {
        $contrato = $this->createContratoComAluno();

        for ($i = 1; $i <= 3; $i++) {
            $fatura = Fatura::create([
                'contrato_id' => $contrato->id,
                'vencimento' => "2026-0{$i}-10",
                'status' => StatusFatura::Pendente,
            ]);
            ItemFatura::create([
                'fatura_id' => $fatura->id,
                'descricao' => "Mensalidade 0{$i}",
                'valor_unitario' => 1000.00,
                'quantidade' => 1,
            ]);
        }

        // Executa com 3 faturas
        DB::enableQueryLog();
        DB::flushQueryLog();

        $query = Fatura::where('contrato_id', $contrato->id);
        $query = FaturaExporter::modifyQuery($query);
        $faturas3 = $query->get();

        $export = new Export;
        $exporter = new FaturaExporter($export, array_combine(
            array_map(fn ($c) => $c->getName(), FaturaExporter::getColumns()),
            array_map(fn ($c) => $c->getName(), FaturaExporter::getColumns())
        ), []);

        foreach ($faturas3 as $fatura) {
            $exporter($fatura);
        }

        $queriesCountCom3 = count(DB::getQueryLog());

        // Adiciona mais 5 faturas (total 8)
        for ($i = 4; $i <= 8; $i++) {
            $fatura = Fatura::create([
                'contrato_id' => $contrato->id,
                'vencimento' => "2026-0{$i}-10",
                'status' => StatusFatura::Pendente,
            ]);
            ItemFatura::create([
                'fatura_id' => $fatura->id,
                'descricao' => "Mensalidade 0{$i}",
                'valor_unitario' => 1000.00,
                'quantidade' => 1,
            ]);
        }

        DB::flushQueryLog();

        $query8 = Fatura::where('contrato_id', $contrato->id);
        $query8 = FaturaExporter::modifyQuery($query8);
        $faturas8 = $query8->get();

        foreach ($faturas8 as $fatura) {
            $exporter($fatura);
        }

        $queriesCountCom8 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // O número de queries com 8 faturas deve ser idêntico ao de 3 faturas (tempo O(1) de queries)
        $this->assertEquals($queriesCountCom3, $queriesCountCom8, 'O número de consultas deve ser constante independente do número de faturas.');
    }

    public function test_relation_manager_actions_visibilidade_e_execucao(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'Import:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Export:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:Fatura', 'guard_name' => 'web']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin_test', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo(['Import:Fatura', 'Export:Fatura', 'ViewAny:Fatura']);

        $user = User::factory()->create();
        $user->assignRole($roleAdmin);

        $this->actingAs($user);
        session(['active_role' => 'admin_test']);

        $contrato = $this->createContratoComAluno();

        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->assertTableActionExists('import')
            ->assertTableActionVisible('import')
            ->assertTableActionExists('export')
            ->assertTableActionVisible('export');
    }

    public function test_import_action_ponta_a_ponta_com_csv(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'Import:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:Fatura', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'admin_import_ponta_a_ponta', 'guard_name' => 'web']);
        $role->givePermissionTo(['Import:Fatura', 'ViewAny:Fatura']);

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);
        session(['active_role' => 'admin_import_ponta_a_ponta']);

        $contrato = $this->createContratoComAluno();

        $csvContent = "Nº,Aluno,Vencimento,Descrição,Valor,Desconto,Tipo de desconto,Status,Valor a pagar,Valor pago,Saldo\n";
        $csvContent .= ",Aluno Teste,20/12/2026,Mensalidade Dezembro Especial,1500.00,100.00,absoluto,Pendente,1400.00,0.00,1400.00\n";

        $file = UploadedFile::fake()->createWithContent('faturas_importacao.csv', $csvContent);

        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->mountTableAction('import')
            ->setTableActionData([
                'file' => $file,
                'columnMap' => [
                    'id' => 'Nº',
                    'aluno' => 'Aluno',
                    'vencimento' => 'Vencimento',
                    'descricao' => 'Descrição',
                    'valor' => 'Valor',
                    'desconto' => 'Desconto',
                    'tipo_desconto' => 'Tipo de desconto',
                    'status' => 'Status',
                    'valor_a_pagar' => 'Valor a pagar',
                    'valor_pago' => 'Valor pago',
                    'saldo' => 'Saldo',
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $faturaCriada = Fatura::where('contrato_id', $contrato->id)->first();
        $this->assertNotNull($faturaCriada);
        $this->assertEquals('2026-12-20', $faturaCriada->vencimento->toDateString());
        $this->assertEquals(StatusFatura::Pendente, $faturaCriada->status);
        $this->assertEquals(1500.00, (float) $faturaCriada->itens->first()->valor_unitario);
        $this->assertEquals('Mensalidade Dezembro Especial', $faturaCriada->itens->first()->descricao);
    }
}
