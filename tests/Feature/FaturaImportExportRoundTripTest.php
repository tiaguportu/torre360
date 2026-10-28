<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Exports\FaturaExporter;
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
use App\Services\GeracaoFaturasContratoService;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaturaImportExportRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSuperAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'Import:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Export:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:Fatura', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['Import:Fatura', 'Export:Fatura', 'ViewAny:Fatura']);

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    private function createContratoComAluno(array $contratoAttributes = []): Contrato
    {
        $aluno = Pessoa::factory()->create(['nome' => 'Carlos Drummond']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        return Contrato::create(array_merge([
            'matricula_id' => $matricula->id,
            'valor_total' => 10000.00,
            'data_aceite' => '2026-01-10',
        ], $contratoAttributes));
    }

    private function getExportFormData(): array
    {
        $columns = FaturaExporter::getColumns();
        $columnMap = [];
        foreach ($columns as $col) {
            $columnMap[$col->getName()] = [
                'isEnabled' => true,
                'label' => $col->getLabel(),
            ];
        }

        return [
            'columnMap' => $columnMap,
        ];
    }

    private function getExportedCsvContent(Export $export): string
    {
        $generator = app(CsvExportContentGenerator::class);
        $csv = '';
        foreach ($generator($export) as $chunk) {
            $csv .= $chunk;
        }

        return $csv;
    }

    /**
     * 1. Round-trip sem edição: contrato já enviado ao Assinafy com variados tipos de fatura.
     * Exporta via ExportAction real, reimporta via ImportAction real.
     * Verifica: zero falhas, zero faturas a mais, itens idênticos, Assinafy intacto e 0 logs adicionais.
     */
    public function test_round_trip_sem_edicao_com_contrato_assinafy_e_variados_tipos_de_fatura(): void
    {
        Storage::fake('local');
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno([
            'assinafy_id' => 'assinafy_doc_roundtrip_1',
            'assinafy_status' => 'pending',
            'assinafy_request_log' => ['criado_em' => now()->toIso8601String()],
        ]);

        $banco = Banco::create(['codigo' => '001', 'nome' => 'Banco do Brasil']);

        // 1. Simples pendente
        $f1 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $f1->id, 'descricao' => 'Mensalidade 01', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);

        // 2. Com bolsa (percentual)
        $f2 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-04-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $f2->id, 'descricao' => 'Mensalidade 02 Bolsa', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 15.0, 'tipo_desconto' => 'percentual']);

        // 3. Paga com transação
        $f3 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-05-10', 'status' => StatusFatura::Pago]);
        ItemFatura::create(['fatura_id' => $f3->id, 'descricao' => 'Mensalidade 03 Paga', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);
        TransacaoBancaria::create(['fatura_id' => $f3->id, 'banco_id' => $banco->id, 'valor' => 1000.00, 'tipo' => 'entrada', 'data_transacao' => '2026-05-09']);

        // 4. Parcial com transação
        $f4 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-06-10', 'status' => StatusFatura::Parcial]);
        ItemFatura::create(['fatura_id' => $f4->id, 'descricao' => 'Mensalidade 04 Parcial', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);
        TransacaoBancaria::create(['fatura_id' => $f4->id, 'banco_id' => $banco->id, 'valor' => 500.00, 'tipo' => 'entrada', 'data_transacao' => '2026-06-08']);

        // 5. Atrasada
        $f5 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-01-10', 'status' => StatusFatura::Atrasado]);
        ItemFatura::create(['fatura_id' => $f5->id, 'descricao' => 'Mensalidade 05 Atrasada', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);

        // 6. Cancelada
        $f6 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-07-10', 'status' => StatusFatura::Cancelado]);
        ItemFatura::create(['fatura_id' => $f6->id, 'descricao' => 'Mensalidade 06 Cancelada', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);

        // 7. Com gateway_id
        $f7 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-08-10', 'status' => StatusFatura::Pendente, 'gateway_id' => 'gw_teste_999']);
        ItemFatura::create(['fatura_id' => $f7->id, 'descricao' => 'Mensalidade 07 Gateway', 'valor_unitario' => 1000.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);

        // 8. Com múltiplos itens
        $f8 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-09-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $f8->id, 'descricao' => 'Material Didático', 'valor_unitario' => 300.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);
        ItemFatura::create(['fatura_id' => $f8->id, 'descricao' => 'Mensalidade 08', 'valor_unitario' => 700.00, 'quantidade' => 1, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);

        // 9. Com item de quantidade diferente de 1
        $f9 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-10-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $f9->id, 'descricao' => 'Uniformes', 'valor_unitario' => 150.00, 'quantidade' => 2, 'desconto' => 0.0, 'tipo_desconto' => 'absoluto']);

        $totalFaturasOriginal = $contrato->faturas()->count();
        $this->assertEquals(9, $totalFaturasOriginal);

        // Contagem de atividades antes do teste
        $activityCountAntes = Activity::count();

        // 1. Exporta via Relation Manager
        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->mountTableAction('export')
            ->setTableActionData($this->getExportFormData())
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        /** @var Export $export */
        $export = Export::latest()->first();
        $this->assertNotNull($export);
        $csvContent = $this->getExportedCsvContent($export);
        $this->assertNotEmpty($csvContent);

        // 2. Reimporta o mesmo arquivo via ImportAction do Relation Manager
        $file = UploadedFile::fake()->createWithContent('reimportacao_pura.csv', $csvContent);

        $this->autenticarSuperAdmin();

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

        /** @var Import $import */
        $import = Import::latest()->first();
        $this->assertNotNull($import);
        $this->assertEquals(9, $import->successful_rows);
        $this->assertEquals(0, $import->getFailedRowsCount());

        // Verificações essenciais:
        // A. Nenhuma fatura nova foi criada
        $this->assertEquals(9, $contrato->fresh()->faturas()->count());

        // B. Itens permanecem idênticos
        $this->assertEquals('percentual', $f2->fresh()->itens->first()->tipo_desconto);
        $this->assertEquals(StatusFatura::Pago, $f3->fresh()->status);
        $this->assertEquals(StatusFatura::Parcial, $f4->fresh()->status);
        $this->assertEquals(StatusFatura::Atrasado, $f5->fresh()->status);
        $this->assertEquals(StatusFatura::Cancelado, $f6->fresh()->status);
        $this->assertEquals('gw_teste_999', $f7->fresh()->gateway_id);
        $this->assertCount(2, $f8->fresh()->itens);
        $this->assertEquals(2, $f9->fresh()->itens->first()->quantidade);

        // C. Assinafy permaneceu intacto (não foi resetado)
        $this->assertTrue($contrato->fresh()->jaEnviadoAssinafy());
        $this->assertEquals('assinafy_doc_roundtrip_1', $contrato->fresh()->assinafy_id);
        $this->assertEquals('pending', $contrato->fresh()->assinafy_status);

        // D. ActivityLog de Fatura/ItemFatura/Contrato inalterado (nenhuma mutação registrada)
        $activitiesMutacao = Activity::whereIn('subject_type', [Contrato::class, Fatura::class, ItemFatura::class])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();
        // Nenhuma alteração (updated/created/deleted) nesses modelos durante o round-trip
        $this->assertEquals(0, Activity::whereIn('subject_type', [Fatura::class, ItemFatura::class])->where('event', 'updated')->count());
        $this->assertEquals(0, Activity::where('subject_type', Contrato::class)->where('description', 'like', '%fatura%')->count());
    }

    /**
     * 2. Round-trip com uma edição: altera só o vencimento de uma fatura simples e reimporta.
     * Muda só ela, as demais intactas, assinatura é resetada.
     */
    public function test_round_trip_com_uma_edicao_altera_so_fatura_editada_e_reseta_assinafy(): void
    {
        Storage::fake('local');
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno([
            'assinafy_id' => 'assinafy_doc_com_edicao',
            'assinafy_status' => 'pending',
        ]);

        $f1 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $f1->id, 'descricao' => 'Parcela 1', 'valor_unitario' => 500.00, 'quantidade' => 1]);

        $f2 = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-04-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $f2->id, 'descricao' => 'Parcela 2', 'valor_unitario' => 500.00, 'quantidade' => 1]);

        // Exporta
        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
            ->mountTableAction('export')
            ->setTableActionData($this->getExportFormData())
            ->callMountedTableAction();

        $export = Export::latest()->first();
        $csvContent = $this->getExportedCsvContent($export);

        // Altera o vencimento de F1 de 10/03/2026 para 25/03/2026
        $csvModificado = str_replace('10/03/2026', '25/03/2026', $csvContent);

        $file = UploadedFile::fake()->createWithContent('edicao_vencimento.csv', $csvModificado);

        $this->autenticarSuperAdmin();

        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
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
            ->callMountedTableAction();

        // F1 mudou
        $this->assertEquals('2026-03-25', $f1->fresh()->vencimento->toDateString());
        // F2 intacta
        $this->assertEquals('2026-04-10', $f2->fresh()->vencimento->toDateString());
        // Assinafy foi resetado por mudança real
        $this->assertFalse($contrato->fresh()->jaEnviadoAssinafy());
    }

    /**
     * 3. Linha protegida alterada: muda o valor de fatura paga no CSV.
     * Linha falha com mensagem clara, aparece em failed_rows e a fatura não muda.
     */
    public function test_linha_protegida_alterada_falha_aparece_em_failed_rows_e_fatura_nao_muda(): void
    {
        Storage::fake('local');
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno();
        $banco = Banco::create(['codigo' => '001', 'nome' => 'Banco do Brasil']);

        $faturaPaga = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => StatusFatura::Pago]);
        ItemFatura::create(['fatura_id' => $faturaPaga->id, 'descricao' => 'Mensalidade Paga', 'valor_unitario' => 800.00, 'quantidade' => 1]);
        TransacaoBancaria::create(['fatura_id' => $faturaPaga->id, 'banco_id' => $banco->id, 'valor' => 800.00, 'tipo' => 'entrada', 'data_transacao' => '2026-03-09']);

        // Exporta
        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
            ->mountTableAction('export')
            ->setTableActionData($this->getExportFormData())
            ->callMountedTableAction();

        $export = Export::latest()->first();
        $csvContent = $this->getExportedCsvContent($export);

        // Altera o valor de 800.00 para 999.00
        $csvModificado = str_replace('800.00', '999.00', $csvContent);

        $file = UploadedFile::fake()->createWithContent('protegida_alterada.csv', $csvModificado);

        $this->autenticarSuperAdmin();

        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
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
            ->callMountedTableAction();

        /** @var Import $import */
        $import = Import::latest()->first();
        $this->assertEquals(1, $import->getFailedRowsCount());

        $failedRow = $import->failedRows()->first();
        $this->assertNotNull($failedRow);
        $this->assertStringContainsString('possui transações bancárias', $failedRow->validation_error);

        // A fatura não mudou
        $this->assertEquals(800.00, (float) $faturaPaga->fresh()->itens->first()->valor_unitario);
    }

    /**
     * 4. percentual (item 2): item percentual reimportado com percentual, % ou relativo é no-op
     * e não reseta a assinatura. O valor gravado continua percentual.
     */
    public function test_item_percentual_reimportado_com_variacoes_eh_no_op_e_preserva_grafia(): void
    {
        Storage::fake('local');
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno([
            'assinafy_id' => 'assinafy_doc_percentual',
            'assinafy_status' => 'pending',
        ]);

        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-04-10', 'status' => StatusFatura::Pendente]);
        $item = ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Parcela Bolsa',
            'valor_unitario' => 1000.00,
            'quantidade' => 1,
            'desconto' => 20.0,
            'tipo_desconto' => 'percentual',
        ]);

        $variacoes = ['percentual', '%', 'relativo'];

        foreach ($variacoes as $grafia) {
            $this->autenticarSuperAdmin();

            $csv = "Nº,Aluno,Vencimento,Descrição,Valor,Desconto,Tipo de desconto,Status,Valor a pagar,Valor pago,Saldo\n";
            $csv .= "{$fatura->id},Aluno,10/04/2026,Parcela Bolsa,1000.00,20.00,{$grafia},Pendente,800.00,0.00,800.00\n";

            $file = UploadedFile::fake()->createWithContent("teste_{$grafia}.csv", $csv);

            Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
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

            // Assinatura não é resetada
            $this->assertTrue($contrato->fresh()->jaEnviadoAssinafy(), "Grafia '{$grafia}' não deveria resetar a assinatura.");
            // O valor gravado no banco continua percentual
            $this->assertEquals('percentual', $item->fresh()->tipo_desconto);
        }
    }

    /**
     * 5. Status em branco (item 3): numa atualização, atrasado e cancelado são preservados.
     */
    public function test_status_em_branco_em_atualizacao_preserva_status_atrasado_e_cancelado(): void
    {
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno();

        $fAtrasada = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-02-10', 'status' => StatusFatura::Atrasado]);
        ItemFatura::create(['fatura_id' => $fAtrasada->id, 'descricao' => 'Parcela Atrasada', 'valor_unitario' => 600.00, 'quantidade' => 1]);

        $fCancelada = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => StatusFatura::Cancelado]);
        ItemFatura::create(['fatura_id' => $fCancelada->id, 'descricao' => 'Parcela Cancelada', 'valor_unitario' => 600.00, 'quantidade' => 1]);

        // CSV com Status vazio (,,)
        $csv = "Nº,Aluno,Vencimento,Descrição,Valor,Desconto,Tipo de desconto,Status,Valor a pagar,Valor pago,Saldo\n";
        $csv .= "{$fAtrasada->id},Aluno,10/02/2026,Parcela Atrasada,600.00,0.00,absoluto,,600.00,0.00,600.00\n";
        $csv .= "{$fCancelada->id},Aluno,10/03/2026,Parcela Cancelada,600.00,0.00,absoluto,,600.00,0.00,600.00\n";

        $file = UploadedFile::fake()->createWithContent('status_vazio.csv', $csv);

        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
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

        $this->assertEquals(StatusFatura::Atrasado, $fAtrasada->fresh()->status);
        $this->assertEquals(StatusFatura::Cancelado, $fCancelada->fresh()->status);
    }

    /**
     * 6. Escopo da exportação pela ação real: traz só faturas do contrato aberto.
     */
    public function test_escopo_da_exportacao_pela_acao_real_traz_apenas_faturas_do_contrato(): void
    {
        Storage::fake('local');
        $this->autenticarSuperAdmin();

        $contratoA = $this->createContratoComAluno();
        $contratoB = $this->createContratoComAluno();

        $fA = Fatura::create(['contrato_id' => $contratoA->id, 'vencimento' => '2026-05-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $fA->id, 'descricao' => 'Fatura A Exclusiva', 'valor_unitario' => 450.00, 'quantidade' => 1]);

        $fB = Fatura::create(['contrato_id' => $contratoB->id, 'vencimento' => '2026-05-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $fB->id, 'descricao' => 'Fatura B Exclusiva', 'valor_unitario' => 550.00, 'quantidade' => 1]);

        // Executa ação de exportar no relation manager do Contrato A
        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contratoA, 'pageClass' => EditContrato::class])
            ->mountTableAction('export')
            ->setTableActionData($this->getExportFormData())
            ->callMountedTableAction();

        $export = Export::latest()->first();
        $csv = $this->getExportedCsvContent($export);

        $this->assertStringContainsString("{$fA->id},", $csv);
        $this->assertStringContainsString('Fatura A Exclusiva', $csv);
        $this->assertStringNotContainsString("{$fB->id},", $csv);
        $this->assertStringNotContainsString('Fatura B Exclusiva', $csv);
    }

    /**
     * 7. Permissão negativa com usuário sem super_admin:
     * - sem permissões: ambas ocultas
     * - só com Import:Fatura: vê importar e não vê exportar
     * - só com Export:Fatura: vê exportar e não vê importar
     */
    public function test_permissao_negativa_action_hidden_para_import_e_export(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'Import:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Export:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:Fatura', 'guard_name' => 'web']);

        $roleNenhum = Role::firstOrCreate(['name' => 'sem_import_export', 'guard_name' => 'web']);
        $roleNenhum->givePermissionTo(['ViewAny:Fatura']);

        $roleSoImport = Role::firstOrCreate(['name' => 'so_import', 'guard_name' => 'web']);
        $roleSoImport->givePermissionTo(['ViewAny:Fatura', 'Import:Fatura']);

        $roleSoExport = Role::firstOrCreate(['name' => 'so_export', 'guard_name' => 'web']);
        $roleSoExport->givePermissionTo(['ViewAny:Fatura', 'Export:Fatura']);

        $user = User::factory()->create();
        $contrato = $this->createContratoComAluno();

        // 1. Sem permissões
        $user->syncRoles([$roleNenhum]);
        $this->actingAs($user);
        session(['active_role' => 'sem_import_export']);

        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
            ->assertTableActionHidden('import')
            ->assertTableActionHidden('export');

        // 2. Só com Import:Fatura
        $user->syncRoles([$roleSoImport]);
        session(['active_role' => 'so_import']);

        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
            ->assertTableActionVisible('import')
            ->assertTableActionHidden('export');

        // 3. Só com Export:Fatura
        $user->syncRoles([$roleSoExport]);
        session(['active_role' => 'so_export']);

        Livewire::test(FaturasRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => EditContrato::class])
            ->assertTableActionHidden('import')
            ->assertTableActionVisible('export');
    }

    /**
     * 8. Trava do "Regenerar Faturas" (item 1):
     * Faturas com transação, gateway_id e parcial impedem a regeneração.
     * Mostra erro, não apaga dados, não reseta Assinafy e não grava log geracao_faturas.
     * Contrato só com pendentes regenera. Teste direto no service garante exception.
     */
    public function test_trava_do_regenerar_faturas_com_transacao_gateway_e_parcial(): void
    {
        $this->autenticarSuperAdmin();
        $banco = Banco::create(['codigo' => '001', 'nome' => 'Banco do Brasil']);

        // Caso A: Contrato com fatura que tem transação
        $contratoTransacao = $this->createContratoComAluno(['assinafy_id' => 'doc_transacao', 'assinafy_status' => 'pending']);
        $fTrans = Fatura::create(['contrato_id' => $contratoTransacao->id, 'vencimento' => '2026-02-10', 'status' => StatusFatura::Pago]);
        ItemFatura::create(['fatura_id' => $fTrans->id, 'descricao' => 'Original', 'valor_unitario' => 500.00, 'quantidade' => 1]);
        TransacaoBancaria::create(['fatura_id' => $fTrans->id, 'banco_id' => $banco->id, 'valor' => 500.00, 'tipo' => 'entrada', 'data_transacao' => '2026-02-09']);

        Livewire::test(EditContrato::class, ['record' => $contratoTransacao->id])
            ->callAction('gerarFaturas', [
                'quantidade_parcelas' => 12,
                'valor_entrada' => 0,
            ])
            ->assertNotified();

        $this->assertEquals(1, $contratoTransacao->fresh()->faturas()->count());
        $this->assertTrue($contratoTransacao->fresh()->jaEnviadoAssinafy());
        $this->assertEquals(0, Activity::where('subject_id', $contratoTransacao->id)->where('properties->tipo', 'geracao_faturas')->count());

        // Caso B: Contrato com gateway_id
        $contratoGateway = $this->createContratoComAluno(['assinafy_id' => 'doc_gateway', 'assinafy_status' => 'pending']);
        $fGw = Fatura::create(['contrato_id' => $contratoGateway->id, 'vencimento' => '2026-03-10', 'status' => StatusFatura::Pendente, 'gateway_id' => 'gw_999']);
        ItemFatura::create(['fatura_id' => $fGw->id, 'descricao' => 'Original Gw', 'valor_unitario' => 500.00, 'quantidade' => 1]);

        Livewire::test(EditContrato::class, ['record' => $contratoGateway->id])
            ->callAction('gerarFaturas', [
                'quantidade_parcelas' => 12,
                'valor_entrada' => 0,
            ])
            ->assertNotified();

        $this->assertEquals(1, $contratoGateway->fresh()->faturas()->count());
        $this->assertTrue($contratoGateway->fresh()->jaEnviadoAssinafy());

        // Caso C: Contrato com fatura Parcial sem transação
        $contratoParcial = $this->createContratoComAluno(['assinafy_id' => 'doc_parcial', 'assinafy_status' => 'pending']);
        $fParcial = Fatura::create(['contrato_id' => $contratoParcial->id, 'vencimento' => '2026-04-10', 'status' => StatusFatura::Parcial]);
        ItemFatura::create(['fatura_id' => $fParcial->id, 'descricao' => 'Original Parcial', 'valor_unitario' => 500.00, 'quantidade' => 1]);

        Livewire::test(EditContrato::class, ['record' => $contratoParcial->id])
            ->callAction('gerarFaturas', [
                'quantidade_parcelas' => 12,
                'valor_entrada' => 0,
            ])
            ->assertNotified();

        $this->assertEquals(1, $contratoParcial->fresh()->faturas()->count());
        $this->assertTrue($contratoParcial->fresh()->jaEnviadoAssinafy());

        // Caso D: Contrato com faturas pendentes comuns regenera normalmente
        $contratoPendente = $this->createContratoComAluno(['assinafy_id' => 'doc_pendente', 'assinafy_status' => 'pending']);
        $fPend = Fatura::create(['contrato_id' => $contratoPendente->id, 'vencimento' => '2026-05-10', 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $fPend->id, 'descricao' => 'Parcela Pendente', 'valor_unitario' => 500.00, 'quantidade' => 1]);

        Livewire::test(EditContrato::class, ['record' => $contratoPendente->id])
            ->callAction('gerarFaturas', [
                'quantidade_parcelas' => 4,
                'valor_entrada' => 0,
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(4, $contratoPendente->fresh()->faturas()->count());

        // Teste direto no GeracaoFaturasContratoService: lança InvalidArgumentException e nada é apagado
        $service = app(GeracaoFaturasContratoService::class);
        try {
            $service->gerar($contratoTransacao, 10, 0);
            $this->fail('Deveria ter lançado InvalidArgumentException ao tentar regenerar contrato com fatura protegida.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('não pode ser regenerado', $e->getMessage());
        }

        $this->assertEquals(1, $contratoTransacao->fresh()->faturas()->count());
    }
}
