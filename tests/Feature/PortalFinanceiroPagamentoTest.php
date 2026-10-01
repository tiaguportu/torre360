<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Portal\Pages\Financeiro;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalFinanceiroPagamentoTest extends TestCase
{
    use RefreshDatabase;

    private function criarResponsavelComFatura(float $valor = 500.0): array
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $turma = Turma::factory()->create();

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsavel Financeiro Portal']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $dependente = Pessoa::factory()->create(['nome' => 'Dependente Financeiro Portal']);
        $responsavelPessoa->alunos()->attach($dependente->id);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $dependente->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => $valor,
            'data_aceite' => now(),
        ]);

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => now()->addDays(10),
            'status' => StatusFatura::Pendente,
        ]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade',
            'valor_unitario' => $valor,
            'quantidade' => 1,
        ]);

        return compact('user', 'fatura');
    }

    public function test_acao_pagar_gera_cobranca_automaticamente_na_primeira_vez(): void
    {
        ['user' => $user, 'fatura' => $fatura] = $this->criarResponsavelComFatura();

        $this->assertNull($fatura->gateway_id);

        Livewire::actingAs($user)
            ->test(Financeiro::class)
            ->callTableAction('pagar', $fatura)
            ->assertSuccessful();

        $fatura = $fatura->fresh();
        $this->assertNotNull($fatura->gateway_id);
        $this->assertNotNull($fatura->pix_copia_e_cola);
    }

    public function test_acao_pagar_nao_gera_cobranca_de_novo_se_ja_existir(): void
    {
        ['user' => $user, 'fatura' => $fatura] = $this->criarResponsavelComFatura();

        Livewire::actingAs($user)->test(Financeiro::class)->callTableAction('pagar', $fatura);
        $gatewayIdPrimeiraVez = $fatura->fresh()->gateway_id;

        Livewire::actingAs($user)->test(Financeiro::class)->callTableAction('pagar', $fatura);

        $this->assertSame($gatewayIdPrimeiraVez, $fatura->fresh()->gateway_id);
    }

    public function test_acao_pagar_nao_aparece_para_fatura_paga(): void
    {
        ['user' => $user, 'fatura' => $fatura] = $this->criarResponsavelComFatura();
        $fatura->update(['status' => StatusFatura::Pago]);

        Livewire::actingAs($user)
            ->test(Financeiro::class)
            ->assertTableActionHidden('pagar', $fatura);
    }
}
