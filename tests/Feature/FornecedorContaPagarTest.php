<?php

namespace Tests\Feature;

use App\Enums\StatusContaPagar;
use App\Filament\Resources\Fornecedores\Pages\CreateFornecedor;
use App\Filament\Resources\Fornecedores\Pages\ListFornecedores;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Models\TransacaoBancaria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FornecedorContaPagarTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $usuarioSemPermissao;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole('aluno');
    }

    public function test_usuario_sem_permissao_nao_acessa_fornecedores(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'aluno']);

        $this->get('/admin/fornecedores')->assertForbidden();

        Livewire::test(ListFornecedores::class)->assertForbidden();
    }

    public function test_super_admin_cria_e_lista_fornecedores(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $this->get('/admin/fornecedores')->assertSuccessful();

        Livewire::test(CreateFornecedor::class)
            ->fillForm([
                'razao_social' => 'Papelaria & Suprimentos Escolares Ltda',
                'nome_fantasia' => 'Papelaria Central',
                'cnpj' => '12.345.678/0001-90',
                'email' => 'contato@papelariacentral.com.br',
                'telefone' => '11988887777',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fornecedors', [
            'razao_social' => 'Papelaria & Suprimentos Escolares Ltda',
            'cnpj' => '12.345.678/0001-90',
        ]);

        Livewire::test(ListFornecedores::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Fornecedor::all());
    }

    public function test_validacao_de_cnpj_duplicado_bloqueia_cadastro(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        Fornecedor::create([
            'razao_social' => 'Fornecedor Original Ltda',
            'cnpj' => '99.888.777/0001-11',
        ]);

        Livewire::test(CreateFornecedor::class)
            ->fillForm([
                'razao_social' => 'Outra Empresa Clonada',
                'cnpj' => '99.888.777/0001-11',
            ])
            ->call('create')
            ->assertHasFormErrors(['cnpj' => 'unique']);
    }

    public function test_associacao_de_fornecedor_com_contas_a_pagar_e_transacoes(): void
    {
        $fornecedor = Fornecedor::create([
            'razao_social' => 'Distribuidora de Alimentos Infantis S.A.',
            'cnpj' => '44.333.222/0001-55',
            'email' => 'vendas@alimentosescolares.com',
        ]);

        $banco = Banco::create(['nome' => 'Banco Bradesco', 'is_active' => true]);

        $contaPagar = ContaPagar::create([
            'descricao' => 'Merenda e Lanches - Lote 1',
            'valor' => 3200.00,
            'vencimento' => now()->addDays(10)->toDateString(),
            'status' => StatusContaPagar::Pendente,
            'fornecedor_id' => $fornecedor->id,
        ]);

        $transacao = TransacaoBancaria::create([
            'banco_id' => $banco->id,
            'fornecedor_id' => $fornecedor->id,
            'tipo' => 'saida',
            'valor' => 3200.00,
            'data_transacao' => now()->toDateString(),
            'conciliado' => true,
        ]);

        $this->assertEquals($fornecedor->id, $contaPagar->fornecedor->id);
        $this->assertCount(1, $fornecedor->transacoes);
        $this->assertEquals($transacao->id, $fornecedor->transacoes->first()->id);
        $this->assertEquals('Distribuidora de Alimentos Infantis S.A. - 44.333.222/0001-55', $fornecedor->nome_cnpj);
    }
}
