<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalAlunoTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComDados(): array
    {
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $pessoa = Pessoa::factory()->create(['nome' => 'Aluno Portal Teste']);
        $user = User::factory()->create(['activated_at' => now()]);
        $pessoa->users()->save($user);
        $user->assignRole('aluno');

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 1200,
            'assinafy_status' => 'pending',
        ]);

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => now()->addDays(10),
            'status' => StatusFatura::Pendente,
        ]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade',
            'valor_unitario' => 600,
            'quantidade' => 1,
        ]);

        return compact('user', 'pessoa', 'matricula', 'contrato', 'fatura');
    }

    public function test_aluno_ve_dashboard_do_portal_com_seu_nome(): void
    {
        $dados = $this->criarAlunoComDados();

        $this->actingAs($dados['user'])
            ->get('/portal')
            ->assertOk()
            ->assertSee('Aluno Portal Teste');
    }

    public function test_aluno_ve_boletim_no_portal(): void
    {
        $dados = $this->criarAlunoComDados();

        $this->actingAs($dados['user'])
            ->get('/portal/academico')
            ->assertOk()
            ->assertSee('Aluno Portal Teste');
    }

    public function test_aluno_ve_fatura_propria_no_financeiro_do_portal(): void
    {
        $dados = $this->criarAlunoComDados();

        $this->actingAs($dados['user'])
            ->get('/portal/financeiro')
            ->assertOk()
            ->assertSee('Aluno Portal Teste');
    }

    public function test_aluno_ve_contrato_proprio_em_documentos_do_portal(): void
    {
        $dados = $this->criarAlunoComDados();

        $this->actingAs($dados['user'])
            ->get('/portal/documentos')
            ->assertOk()
            ->assertSee('Aluno Portal Teste');
    }

    public function test_aluno_pode_baixar_boletim_da_propria_matricula(): void
    {
        $dados = $this->criarAlunoComDados();

        $response = $this->actingAs($dados['user'])
            ->get(route('matriculas.boletim.download', $dados['matricula']));

        $response->assertStatus(302)->assertSessionHas('error');
    }

    public function test_aluno_pode_visualizar_o_proprio_contrato(): void
    {
        $dados = $this->criarAlunoComDados();

        $response = $this->actingAs($dados['user'])
            ->get(route('contratos.visualizar', $dados['contrato']));

        $response->assertOk();
    }

    public function test_aluno_nao_acessa_contrato_de_outro_aluno(): void
    {
        $dados = $this->criarAlunoComDados();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id]);
        $outroContrato = Contrato::create([
            'matricula_id' => $outraMatricula->id,
            'valor_total' => 500,
            'assinafy_status' => 'pending',
        ]);

        $response = $this->actingAs($dados['user'])
            ->get(route('contratos.visualizar', $outroContrato));

        $response->assertForbidden();
    }

    public function test_aluno_nao_ve_fatura_de_outro_aluno_no_financeiro_do_portal(): void
    {
        $dados = $this->criarAlunoComDados();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id]);
        $outroContrato = Contrato::create([
            'matricula_id' => $outraMatricula->id,
            'valor_total' => 900,
            'assinafy_status' => 'pending',
        ]);
        Fatura::create([
            'contrato_id' => $outroContrato->id,
            'vencimento' => now()->addDays(5),
            'status' => StatusFatura::Pendente,
        ]);

        $this->actingAs($dados['user'])
            ->get('/portal/financeiro')
            ->assertOk()
            ->assertDontSee('Outro Aluno');
    }

    public function test_aluno_nao_ve_documento_de_outro_aluno_na_lista_do_portal(): void
    {
        $dados = $this->criarAlunoComDados();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id]);
        Contrato::create([
            'matricula_id' => $outraMatricula->id,
            'valor_total' => 500,
            'assinafy_status' => 'pending',
        ]);

        $this->actingAs($dados['user'])
            ->get('/portal/documentos')
            ->assertOk()
            ->assertDontSee('Outro Aluno');
    }

    public function test_aluno_nao_ve_boletim_de_outro_aluno_no_academico_do_portal(): void
    {
        $dados = $this->criarAlunoComDados();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        Matricula::factory()->create(['pessoa_id' => $outraPessoa->id, 'situacao' => 'ativa']);

        $this->actingAs($dados['user'])
            ->get('/portal/academico')
            ->assertOk()
            ->assertDontSee('Outro Aluno');
    }
}
