<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Portal\Pages\Preceptoria;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria as PreceptoriaModel;
use App\Models\ResponsavelFinanceiro;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalResponsavelTest extends TestCase
{
    use RefreshDatabase;

    private function criarResponsavelComDependente(): array
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $professor = Pessoa::factory()->create(['nome' => 'Professor Teste']);
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $professor->id]);

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsavel Portal Teste']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $dependente = Pessoa::factory()->create(['nome' => 'Dependente Portal Teste']);
        $responsavelPessoa->alunos()->attach($dependente->id);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $dependente->id,
            'turma_id' => $turma->id,
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

        return compact('user', 'responsavelPessoa', 'dependente', 'matricula', 'contrato', 'fatura', 'professor', 'turma');
    }

    public function test_responsavel_ve_dashboard_do_portal_com_nome_do_dependente(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $this->actingAs($dados['user'])
            ->get('/portal')
            ->assertOk()
            ->assertSee('Dependente Portal Teste');
    }

    public function test_responsavel_ve_boletim_do_dependente(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $this->actingAs($dados['user'])
            ->get('/portal/academico')
            ->assertOk()
            ->assertSee('Dependente Portal Teste');
    }

    public function test_responsavel_ve_fatura_do_dependente_no_financeiro(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $this->actingAs($dados['user'])
            ->get('/portal/financeiro')
            ->assertOk()
            ->assertSee('Dependente Portal Teste');
    }

    public function test_responsavel_ve_contrato_do_dependente_em_documentos(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $this->actingAs($dados['user'])
            ->get('/portal/documentos')
            ->assertOk()
            ->assertSee('Dependente Portal Teste');
    }

    public function test_responsavel_nao_ve_dados_de_aluno_que_nao_e_seu_dependente(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Aluno Alheio']);
        Matricula::factory()->create(['pessoa_id' => $outraPessoa->id, 'situacao' => 'ativa']);

        $this->actingAs($dados['user'])
            ->get('/portal/academico')
            ->assertOk()
            ->assertDontSee('Aluno Alheio');
    }

    public function test_responsavel_ve_fatura_vinculada_por_responsabilidade_financeira_mesmo_sem_ser_dependente(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Aluno Apadrinhado']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id, 'situacao' => 'ativa']);
        $outroContrato = Contrato::create([
            'matricula_id' => $outraMatricula->id,
            'valor_total' => 800,
            'assinafy_status' => 'pending',
        ]);

        ResponsavelFinanceiro::create([
            'contrato_id' => $outroContrato->id,
            'pessoa_id' => $dados['responsavelPessoa']->id,
            'percentual' => 100,
        ]);

        $this->actingAs($dados['user'])
            ->get('/portal/documentos')
            ->assertOk()
            ->assertSee('Aluno Apadrinhado');
    }

    public function test_responsavel_agenda_preceptoria_para_dependente(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $slot = PreceptoriaModel::factory()->create([
            'professor_id' => $dados['professor']->id,
            'data' => now()->addDays(3)->toDateString(),
            'matricula_id' => null,
        ]);

        Livewire::actingAs($dados['user'])
            ->test(Preceptoria::class)
            ->set('data.matricula_id', $dados['matricula']->id)
            ->set('data.preceptoria_id', $slot->id)
            ->call('agendar')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('preceptoria', [
            'id' => $slot->id,
            'matricula_id' => $dados['matricula']->id,
        ]);
    }

    public function test_responsavel_nao_agenda_preceptoria_para_aluno_que_nao_e_seu_dependente(): void
    {
        $dados = $this->criarResponsavelComDependente();

        $outraPessoa = Pessoa::factory()->create(['nome' => 'Aluno Alheio']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outraPessoa->id]);

        $slot = PreceptoriaModel::factory()->create([
            'professor_id' => $dados['professor']->id,
            'data' => now()->addDays(3)->toDateString(),
            'matricula_id' => null,
        ]);

        Livewire::actingAs($dados['user'])
            ->test(Preceptoria::class)
            ->set('data.matricula_id', $outraMatricula->id)
            ->set('data.preceptoria_id', $slot->id)
            ->call('agendar');

        $this->assertDatabaseHas('preceptoria', [
            'id' => $slot->id,
            'matricula_id' => null,
        ]);
    }
}
