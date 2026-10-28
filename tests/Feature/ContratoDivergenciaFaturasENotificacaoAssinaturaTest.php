<?php

namespace Tests\Feature;

use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Models\TipoVinculo;
use App\Models\User;
use App\Services\AssinafyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContratoDivergenciaFaturasENotificacaoAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Matricula $matricula;

    private TemplateContrato $template;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        TipoVinculo::firstOrCreate(['nome' => 'Pai']);
        TipoVinculo::firstOrCreate(['nome' => 'Mãe']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleSecretaria = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);

        $permissoes = [
            'ViewAny:Contrato', 'View:Contrato', 'Create:Contrato', 'Update:Contrato', 'Delete:Contrato',
        ];
        foreach ($permissoes as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $roleAdmin->givePermissionTo($perm);
            $roleSecretaria->givePermissionTo($perm);
        }

        $this->adminUser = User::factory()->create([
            'email' => 'admin.contratos@example.com',
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
        ]);
        $this->adminUser->assignRole($roleAdmin);
        $this->actingAs($this->adminUser);

        $aluno = Pessoa::factory()->create(['nome' => 'Joãozinho Silva']);
        $this->matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $this->template = TemplateContrato::create([
            'nome' => 'Template Contrato Anual',
            'conteudo' => '<p>Minuta de Contrato</p>',
            'is_padrao' => true,
        ]);
    }

    public function test_registra_log_no_activitylog_quando_valor_total_do_contrato_diverge_das_faturas_emitidas(): void
    {
        $contrato = Contrato::create([
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'valor_total' => 12000.00,
            'data_aceite' => now()->subDays(5),
            'assinafy_status' => 'pending',
        ]);

        // Cria faturas emitidas totalizando R$ 12.000,00
        $fatura1 = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => now()->addMonth()->toDateString(),
            'status' => 'pendente',
        ]);
        ItemFatura::create([
            'fatura_id' => $fatura1->id,
            'descricao' => 'Parcela 1/2',
            'valor_unitario' => 6000.00,
            'quantidade' => 1,
            'desconto' => 0,
            'tipo_desconto' => 'absoluto',
        ]);

        $fatura2 = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => now()->addMonths(2)->toDateString(),
            'status' => 'pendente',
        ]);
        ItemFatura::create([
            'fatura_id' => $fatura2->id,
            'descricao' => 'Parcela 2/2',
            'valor_unitario' => 6000.00,
            'quantidade' => 1,
            'desconto' => 0,
            'tipo_desconto' => 'absoluto',
        ]);

        $this->assertEquals(12000.00, $contrato->totalFaturasEmitidas());

        // Altera o valor total do contrato para R$ 15.000,00 (divergência de R$ 3.000,00)
        $contrato->update(['valor_total' => 15000.00]);

        $logDivergencia = Activity::query()
            ->where('log_name', 'contrato')
            ->where('subject_type', Contrato::class)
            ->where('subject_id', $contrato->id)
            ->where('properties->tipo', 'divergencia_faturamento')
            ->latest('id')
            ->first();

        $this->assertNotNull($logDivergencia, 'Deve registrar um log explícito no Spatie ActivityLog alertando a divergência.');
        $this->assertStringContainsString('Regenerar Faturas', $logDivergencia->description);
        $this->assertStringContainsString('divergindo do saldo das faturas já emitidas', $logDivergencia->description);
        $this->assertEquals(12000.00, (float) $logDivergencia->properties['valor_contrato_anterior']);
        $this->assertEquals(15000.00, (float) $logDivergencia->properties['valor_contrato_novo']);
        $this->assertEquals(12000.00, (float) $logDivergencia->properties['saldo_faturas_emitidas']);
        $this->assertEquals(3000.00, (float) $logDivergencia->properties['diferenca']);
        $this->assertEquals('Regenerar Faturas', $logDivergencia->properties['sugestao']);
    }

    public function test_nao_registra_log_de_divergencia_quando_contrato_nao_possui_faturas_emitidas(): void
    {
        $contrato = Contrato::create([
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'valor_total' => 10000.00,
            'assinafy_status' => 'pending',
        ]);

        $contrato->update(['valor_total' => 14000.00]);

        $logDivergencia = Activity::query()
            ->where('subject_type', Contrato::class)
            ->where('subject_id', $contrato->id)
            ->where('properties->tipo', 'divergencia_faturamento')
            ->first();

        $this->assertNull($logDivergencia, 'Não deve registrar log de divergência se o contrato ainda não tem faturas emitidas.');
    }

    public function test_dispara_notificacao_no_painel_ao_concluir_assinaturas_do_contrato(): void
    {
        $secretaria = User::factory()->create([
            'name' => 'Maria Secretaria',
            'email' => 'maria.secretaria@example.com',
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
        ]);
        $roleSecretaria = Role::where('name', 'secretaria')->first();
        $secretaria->assignRole($roleSecretaria);

        $contrato = Contrato::create([
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'valor_total' => 10000.00,
            'assinafy_status' => 'enviado',
            'assinafy_id' => 'doc_assinafy_test_123',
        ]);

        $this->assertEquals(0, $secretaria->notifications()->count());
        $this->assertEquals(0, $this->adminUser->notifications()->count());

        // Atualiza para status de conclusão (ex: ready)
        $contrato->update(['assinafy_status' => 'ready']);

        $this->assertGreaterThan(0, $secretaria->notifications()->count(), 'A secretária ativa deve receber a notificação no painel.');
        $this->assertGreaterThan(0, $this->adminUser->notifications()->count(), 'O admin ativo deve receber a notificação no painel.');

        $notificacao = $secretaria->notifications()->first();
        $this->assertEquals('Contrato Assinado', $notificacao->data['title']);
        $this->assertStringContainsString("Contrato #{$contrato->id}", $notificacao->data['body']);
        $this->assertStringContainsString('Joãozinho Silva', $notificacao->data['body']);
    }

    public function test_assinafy_service_dispara_notificacao_ao_processar_webhook_de_documento_pronto(): void
    {
        $secretaria = User::factory()->create([
            'name' => 'Clara Secretaria',
            'email' => 'clara.secretaria@example.com',
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
        ]);
        $roleSecretaria = Role::where('name', 'secretaria')->first();
        $secretaria->assignRole($roleSecretaria);

        $contrato = Contrato::create([
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'valor_total' => 8000.00,
            'assinafy_status' => 'enviado',
            'assinafy_id' => 'doc_webhook_ready_999',
        ]);

        Http::fake([
            '*/documents/doc_webhook_ready_999' => Http::response([
                'status' => 'ready',
                'data' => [
                    'id' => 'doc_webhook_ready_999',
                    'status' => 'ready',
                    'assignment' => [
                        'signers' => [
                            ['email' => 'pai@example.com', 'status' => 'signed'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(AssinafyService::class);
        $payload = [
            'event' => 'document_ready',
            'object' => [
                'id' => 'doc_webhook_ready_999',
                'name' => "Contrato - Escola Torre de Marfim - Joãozinho Silva - {$contrato->id}.pdf",
            ],
        ];

        $processou = $service->handleWebhook($payload);
        $this->assertTrue($processou);

        $contrato->refresh();
        $this->assertTrue($contrato->estaAssinado());
        $this->assertGreaterThan(0, $secretaria->notifications()->count());
    }

    public function test_formulario_de_edicao_exibe_label_regenerar_faturas_quando_faturas_existem(): void
    {
        $contrato = Contrato::create([
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'valor_total' => 6000.00,
            'data_aceite' => now()->subDay(),
            'assinafy_status' => 'pending',
        ]);

        // Sem faturas: o botão é 'Gerar Faturas Automaticamente'
        Livewire::test(EditContrato::class, ['record' => $contrato->id])
            ->assertActionExists('gerarFaturas')
            ->assertSee('Gerar Faturas Automaticamente');

        // Cria uma fatura
        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => now()->addMonth()->toDateString(),
            'status' => 'pendente',
        ]);
        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Parcela Única',
            'valor_unitario' => 6000.00,
            'quantidade' => 1,
            'desconto' => 0,
            'tipo_desconto' => 'absoluto',
        ]);

        // Com faturas: o botão se adapta para 'Regenerar Faturas'
        Livewire::test(EditContrato::class, ['record' => $contrato->id])
            ->assertActionExists('gerarFaturas')
            ->assertSee('Regenerar Faturas');
    }
}
