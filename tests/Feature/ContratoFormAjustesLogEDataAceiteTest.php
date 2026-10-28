<?php

namespace Tests\Feature;

use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Models\TipoVinculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContratoFormAjustesLogEDataAceiteTest extends TestCase
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

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permissoes = [
            'ViewAny:Contrato', 'View:Contrato', 'Create:Contrato', 'Update:Contrato', 'Delete:Contrato',
            'DeleteAny:Contrato', 'Import:Contrato', 'Export:Contrato',
        ];
        foreach ($permissoes as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $this->adminUser = User::factory()->create(['email' => 'admin.test@example.com']);
        $this->adminUser->assignRole($role);
        $this->actingAs($this->adminUser);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $this->matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $this->template = TemplateContrato::create([
            'nome' => 'Template Padrão',
            'conteudo' => '<p>Termos</p>',
            'is_padrao' => true,
        ]);
    }

    /**
     * Teste de reprodução do Ajuste 2:
     * Contrato com data_aceite contendo hora/minuto/segundo não deve perder
     * o horário ao salvar alterações em outros campos do formulário.
     */
    public function test_salvar_contrato_nao_perde_hora_de_data_aceite(): void
    {
        $contrato = Contrato::create([
            'valor_total' => 2000.00,
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'data_aceite' => '2026-10-27 14:32:10',
            'assinafy_id' => null, // Sem assinafy_id para salvar direto sem modal
        ]);

        Livewire::test(EditContrato::class, ['record' => $contrato->id])
            ->fillForm(['valor_total' => 2500.00])
            ->call('save')
            ->assertHasNoFormErrors();

        $contratoAtualizado = $contrato->fresh();
        $this->assertEquals(2500.00, (float) $contratoAtualizado->valor_total);

        // Afirmação que deve falhar se o DatePicker descartar o horário
        $this->assertEquals(
            '2026-10-27 14:32:10',
            $contratoAtualizado->data_aceite?->format('Y-m-d H:i:s'),
            'A data de aceite não deve perder a hora/minuto/segundo ao salvar o formulário.'
        );
    }

    public function test_registro_tecnico_oculto_quando_contrato_nao_tem_log(): void
    {
        $contratoSemLog = Contrato::create([
            'valor_total' => 1500.00,
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'log_assinatura' => null,
        ]);

        Livewire::test(EditContrato::class, ['record' => $contratoSemLog->id])
            ->assertFormFieldIsHidden('log_assinatura')
            ->assertDontSee('Registro técnico');
    }

    public function test_registro_tecnico_visivel_somente_leitura_e_imutavel_quando_tem_log(): void
    {
        $logOriginal = "IP: 192.168.1.1\nAssinado digitalmente por João da Silva via Assinafy hash: abc123def456";

        $contratoComLog = Contrato::create([
            'valor_total' => 1500.00,
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'log_assinatura' => $logOriginal,
        ]);

        Livewire::test(EditContrato::class, ['record' => $contratoComLog->id])
            ->assertFormFieldIsVisible('log_assinatura')
            ->assertFormFieldIsDisabled('log_assinatura')
            ->assertSee('Registro técnico')
            ->fillForm([
                'valor_total' => 1999.00,
                'log_assinatura' => 'Tentativa de adulteracao maliciosa do log',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $contratoAtualizado = $contratoComLog->fresh();
        $this->assertEquals(1999.00, (float) $contratoAtualizado->valor_total);
        $this->assertEquals($logOriginal, $contratoAtualizado->log_assinatura, 'O log técnico de assinatura não pode ser alterado via formulário.');
    }
}
