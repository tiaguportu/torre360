<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\DocumentoRegulatorios\Pages\CreateDocumentoRegulatorio;
use App\Filament\Resources\DocumentoRegulatorios\Pages\ListDocumentoRegulatorios;
use App\Models\DocumentoRegulatorio;
use App\Models\Unidade;
use App\Models\User;
use App\Services\DocumentoRegulatorioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentoRegulatorioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DocumentoRegulatorioService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleSuper = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole($roleSuper);

        $this->service = app(DocumentoRegulatorioService::class);
    }

    public function test_calcular_status_identifica_vigente_alerta_vencido_e_indeterminado(): void
    {
        $docIndeterminado = new DocumentoRegulatorio(['status' => 'vigente', 'data_validade' => null]);
        $this->assertEquals('indeterminado', $docIndeterminado->calcularStatus());

        $docVencido = new DocumentoRegulatorio([
            'status' => 'vigente',
            'data_validade' => Carbon::today()->subDays(5)->toDateString(),
        ]);
        $this->assertEquals('vencido', $docVencido->calcularStatus());

        $docAlerta = new DocumentoRegulatorio([
            'status' => 'vigente',
            'data_validade' => Carbon::today()->addDays(15)->toDateString(),
            'dias_antecedencia_alerta' => 30,
        ]);
        $this->assertEquals('alerta_vencimento', $docAlerta->calcularStatus());

        $docVigente = new DocumentoRegulatorio([
            'status' => 'vigente',
            'data_validade' => Carbon::today()->addDays(60)->toDateString(),
            'dias_antecedencia_alerta' => 30,
        ]);
        $this->assertEquals('vigente', $docVigente->calcularStatus());

        $docRevogado = new DocumentoRegulatorio([
            'status' => 'revogado',
            'data_validade' => Carbon::today()->addDays(60)->toDateString(),
        ]);
        $this->assertEquals('revogado', $docRevogado->calcularStatus());
    }

    public function test_service_sincronizar_status_geral_atualiza_registros(): void
    {
        $doc1 = DocumentoRegulatorio::create([
            'tipo_documento' => 'alvara_funcionamento',
            'nome' => 'Alvará Municipal',
            'data_validade' => Carbon::today()->subDays(10)->toDateString(),
            'status' => 'vigente', // Inconsistente, precisa virar vencido
        ]);

        $doc2 = DocumentoRegulatorio::create([
            'tipo_documento' => 'avcb_clcb',
            'nome' => 'AVCB Bombeiros',
            'data_validade' => Carbon::today()->addDays(10)->toDateString(),
            'dias_antecedencia_alerta' => 30,
            'status' => 'vigente', // Inconsistente, precisa virar alerta_vencimento
        ]);

        $atualizados = $this->service->sincronizarStatusGeral();

        $this->assertEquals(2, $atualizados);
        $this->assertEquals('vencido', $doc1->fresh()->status);
        $this->assertEquals('alerta_vencimento', $doc2->fresh()->status);
    }

    public function test_service_obter_resumo_por_status_e_alertas_vencimento(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Centro']);

        DocumentoRegulatorio::create([
            'unidade_id' => $unidade->id,
            'tipo_documento' => 'licenca_sanitaria',
            'nome' => 'Licença Sanitária 2026',
            'data_validade' => Carbon::today()->addDays(100)->toDateString(),
            'status' => 'vigente',
        ]);

        DocumentoRegulatorio::create([
            'unidade_id' => $unidade->id,
            'tipo_documento' => 'avcb_clcb',
            'nome' => 'AVCB',
            'data_validade' => Carbon::today()->addDays(5)->toDateString(),
            'status' => 'alerta_vencimento',
        ]);

        DocumentoRegulatorio::create([
            'unidade_id' => $unidade->id,
            'tipo_documento' => 'dedetizacao',
            'nome' => 'Dedetização',
            'data_validade' => Carbon::today()->subDays(2)->toDateString(),
            'status' => 'vencido',
        ]);

        $resumo = $this->service->obterResumoPorStatus($unidade->id);
        $this->assertEquals(3, $resumo['total']);
        $this->assertEquals(1, $resumo['vigentes']);
        $this->assertEquals(1, $resumo['alerta']);
        $this->assertEquals(1, $resumo['vencidos']);
        $this->assertEquals(33.3, $resumo['taxa_conformidade']);

        $alertas = $this->service->obterAlertasVencimento($unidade->id);
        $this->assertCount(2, $alertas);
    }

    public function test_admin_pode_criar_documento_regulatorio_no_filament(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateDocumentoRegulatorio::class)
            ->fillForm([
                'tipo_documento' => 'alvara_funcionamento',
                'nome' => 'Alvará Sanitário 2027',
                'numero_registro' => 'ALV-998877',
                'orgao_emissor' => 'Vigilância Sanitária Municipal',
                'data_emissao' => '2026-01-10',
                'data_validade' => '2027-01-10',
                'dias_antecedencia_alerta' => 45,
                'status' => 'vigente',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documentos_regulatorios', [
            'nome' => 'Alvará Sanitário 2027',
            'numero_registro' => 'ALV-998877',
            'tipo_documento' => 'alvara_funcionamento',
        ]);
    }

    public function test_admin_visualiza_listagem_de_documentos_regulatorios(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $doc = DocumentoRegulatorio::create([
            'tipo_documento' => 'credenciamento_mec_cee',
            'nome' => 'Parecer CEE Renovação de Reconhecimento',
            'numero_registro' => 'PAREC-CEE-12/2026',
            'data_validade' => Carbon::today()->addDays(200)->toDateString(),
            'status' => 'vigente',
        ]);

        Livewire::test(ListDocumentoRegulatorios::class)
            ->assertCanSeeTableRecords([$doc])
            ->assertSee('Parecer CEE Renovação de Reconhecimento')
            ->assertSee('Nº: PAREC-CEE-12/2026');
    }
}
