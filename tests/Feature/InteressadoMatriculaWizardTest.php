<?php

namespace Tests\Feature;

use App\Filament\Pages\EnrollmentWizard;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\InteressadoMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InteressadoMatriculaWizardTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $matriculado;

    private Serie $serie;

    private Curso $curso;

    private Unidade $unidade;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->novo = StatusInteressado::factory()->create(['nome' => 'Novo', 'ordem' => 1]);
        $this->matriculado = StatusInteressado::factory()->ganho()->create(['ordem' => 9]);

        $this->unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $this->curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => $this->unidade->id,
        ]);
        $this->serie = Serie::create([
            'nome' => '3º Ano',
            'curso_id' => $this->curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * @param  list<array{nome: string, nascimento?: string}>  $dependentes
     */
    private function lead(string $nomeContato, array $dependentes): Interessado
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nomeContato, 'email' => 'contato@example.com', 'telefone' => '11988887777']);

        $lead = Interessado::factory()->create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'status_interessado_id' => $this->novo->id,
        ]);

        foreach ($dependentes as $dependente) {
            InteressadoDependente::create([
                'interessado_id' => $lead->id,
                'nome_crianca' => $dependente['nome'],
                'data_nascimento' => $dependente['nascimento'] ?? null,
                'serie_id' => $this->serie->id,
            ]);
        }

        return $lead;
    }

    public function test_contato_diferente_do_aluno_vira_responsavel_financeiro_no_wizard(): void
    {
        $lead = $this->lead('Maria Responsável', [
            ['nome' => 'João Aluno', 'nascimento' => '2018-05-10'],
            ['nome' => 'Ana Aluna'],
        ]);

        $dados = InteressadoMatriculaService::dadosParaWizard($lead);

        $this->assertSame(['João Aluno', 'Ana Aluna'], array_column($dados['alunos'], 'nome'));
        $this->assertSame('2018-05-10', $dados['alunos'][0]['data_nascimento']);
        $this->assertNull($dados['alunos'][1]['data_nascimento']);
        $this->assertArrayNotHasKey('email', $dados['alunos'][0]);

        $this->assertCount(1, $dados['responsaveis']);
        $this->assertSame('Maria Responsável', $dados['responsaveis'][0]['nome']);
        $this->assertSame('contato@example.com', $dados['responsaveis'][0]['email']);
        $this->assertSame($lead->pessoa_id, $dados['responsaveis'][0]['pessoa_id_existente']);
        $this->assertTrue($dados['responsaveis'][0]['is_financeiro']);
        $this->assertSame(100, $dados['responsaveis'][0]['percentual']);
    }

    public function test_lead_que_e_o_proprio_aluno_leva_contato_para_o_aluno_sem_presumir_responsavel(): void
    {
        $lead = $this->lead('Pedro Estudante', [['nome' => 'Pedro Estudante']]);

        $dados = InteressadoMatriculaService::dadosParaWizard($lead);

        $this->assertArrayNotHasKey('responsaveis', $dados);
        $this->assertSame('contato@example.com', $dados['alunos'][0]['email']);
        $this->assertSame($lead->pessoa_id, $dados['alunos'][0]['pessoa_id_existente']);
    }

    public function test_curso_e_unidade_vem_da_serie_do_dependente(): void
    {
        $lead = $this->lead('Maria Responsável', [['nome' => 'João Aluno']]);

        $dados = InteressadoMatriculaService::dadosParaWizard($lead);

        $this->assertSame($this->curso->id, $dados['curso_id']);
        $this->assertSame($this->unidade->id, $dados['unidade_id']);
    }

    public function test_lead_sem_dependentes_gera_apenas_o_responsavel(): void
    {
        $lead = $this->lead('Maria Responsável', []);

        $dados = InteressadoMatriculaService::dadosParaWizard($lead);

        $this->assertArrayNotHasKey('alunos', $dados);
        $this->assertArrayNotHasKey('curso_id', $dados);
        $this->assertCount(1, $dados['responsaveis']);
    }

    public function test_registrar_conversao_marca_data_status_e_e_idempotente(): void
    {
        $lead = $this->lead('Maria Responsável', []);

        InteressadoMatriculaService::registrarConversao($lead);
        $lead->refresh();

        $primeiraData = $lead->data_conversao;
        $this->assertNotNull($primeiraData);
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
        $this->assertNotNull($lead->lead_score);

        $this->travel(2)->days();
        InteressadoMatriculaService::registrarConversao($lead);

        $this->assertTrue($lead->fresh()->data_conversao->eq($primeiraData), 'A data original da conversão é preservada.');
    }

    public function test_wizard_abre_pre_preenchido_com_o_lead(): void
    {
        $lead = $this->lead('Maria Responsável', [['nome' => 'João Aluno']]);

        $wizard = Livewire::withQueryParams(['interessado' => $lead->id])
            ->actingAs($this->admin())
            ->test(EnrollmentWizard::class)
            ->assertSet('interessadoId', $lead->id)
            ->assertNotified('Dados do lead carregados');

        $alunos = collect($wizard->get('data.alunos'))->pluck('nome')->all();
        $responsaveis = collect($wizard->get('data.responsaveis'))->pluck('nome')->all();

        $this->assertSame(['João Aluno'], $alunos);
        $this->assertSame(['Maria Responsável'], $responsaveis);
        $wizard->assertSet('data.curso_id', $this->curso->id);
    }

    public function test_wizard_preenche_dados_da_pessoa_ao_digitar_cpf_ja_cadastrado(): void
    {
        $existente = Pessoa::factory()->create([
            'nome' => 'Pedro Já Cadastrado',
            'cpf' => '01844778320',
            'data_nascimento' => '2015-03-20',
        ]);
        $lead = $this->lead('Maria Responsável', [['nome' => 'João Aluno']]);

        $wizard = Livewire::withQueryParams(['interessado' => $lead->id])
            ->actingAs($this->admin())
            ->test(EnrollmentWizard::class);

        $chave = array_key_first($wizard->get('data.alunos'));

        $wizard->set("data.alunos.{$chave}.cpf", '018.447.783-20')
            ->assertHasNoErrors()
            ->assertSet("data.alunos.{$chave}.nome", 'Pedro Já Cadastrado')
            // O DatePicker do Filament hidrata o estado como data+hora; interessa a data.
            ->assertSet("data.alunos.{$chave}.data_nascimento", fn ($valor) => str_starts_with((string) $valor, '2015-03-20'))
            ->assertSet("data.alunos.{$chave}.pessoa_id_existente", $existente->id);
    }

    public function test_turmas_do_periodo_e_sem_periodo_aparecem_e_de_outro_periodo_nao(): void
    {
        $atual = PeriodoLetivo::factory()->create();
        $outro = PeriodoLetivo::factory()->create();
        $doPeriodo = Turma::factory()->create(['serie_id' => $this->serie->id, 'periodo_letivo_id' => $atual->id]);
        $semPeriodo = Turma::factory()->create(['serie_id' => $this->serie->id, 'periodo_letivo_id' => null]);
        $deOutroPeriodo = Turma::factory()->create(['serie_id' => $this->serie->id, 'periodo_letivo_id' => $outro->id]);

        $wizard = Livewire::actingAs($this->admin())
            ->test(EnrollmentWizard::class)
            ->set('data.unidade_id', $this->unidade->id)
            ->set('data.curso_id', $this->curso->id)
            ->set('data.periodo_letivo_id', $atual->id);

        $opcoes = $wizard->instance()->getSchema('form')->getFlatFields(withHidden: true)['turma_id']->getOptions();

        $this->assertArrayHasKey($doPeriodo->id, $opcoes);
        $this->assertArrayHasKey($semPeriodo->id, $opcoes);
        $this->assertArrayNotHasKey($deOutroPeriodo->id, $opcoes);
    }

    public function test_wizard_sem_parametro_abre_vazio(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EnrollmentWizard::class)
            ->assertSet('interessadoId', null)
            ->assertNotNotified('Dados do lead carregados');
    }

    public function test_wizard_ignora_lead_inexistente(): void
    {
        Livewire::withQueryParams(['interessado' => 999999])
            ->actingAs($this->admin())
            ->test(EnrollmentWizard::class)
            ->assertSet('interessadoId', null);
    }

    public function test_finalizar_matricula_pelo_wizard_converte_o_lead_de_origem(): void
    {
        $lead = $this->lead('Maria Responsável', [['nome' => 'João Aluno', 'nascimento' => '2018-05-10']]);
        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['serie_id' => $this->serie->id, 'periodo_letivo_id' => $periodo->id]);
        $vinculo = TipoVinculo::create(['nome' => 'Mãe']);

        $wizard = Livewire::withQueryParams(['interessado' => $lead->id])
            ->actingAs($this->admin())
            ->test(EnrollmentWizard::class);

        $responsaveis = $wizard->get('data.responsaveis');
        $chave = array_key_first($responsaveis);

        $wizard
            ->set("data.responsaveis.{$chave}.tipo_vinculo_id", $vinculo->id)
            ->set('data.unidade_id', $this->unidade->id)
            ->set('data.periodo_letivo_id', $periodo->id)
            ->set('data.curso_id', $this->curso->id)
            ->set('data.turma_id', $turma->id)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertSame(1, Matricula::count());

        $lead->refresh();
        $this->assertNotNull($lead->data_conversao);
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
    }

    public function test_acao_matricular_da_tabela_aponta_para_o_wizard_com_o_lead(): void
    {
        $lead = $this->lead('Maria Responsável', [['nome' => 'João Aluno']]);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionVisible('matricular', $lead)
            ->assertTableActionHidden('finalizarMatricula', $lead)
            ->assertTableActionHasUrl('matricular', EnrollmentWizard::getUrl(['interessado' => $lead->id]), $lead);
    }

    public function test_acao_matricular_fica_oculta_para_lead_ja_convertido(): void
    {
        $lead = $this->lead('Maria Responsável', []);
        $lead->update(['status_interessado_id' => $this->matriculado->id]);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionHidden('matricular', $lead);
    }
}
