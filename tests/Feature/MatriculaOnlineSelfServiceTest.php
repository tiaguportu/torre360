<?php

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Livewire\MatriculaOnline\MatriculaOnlineWizard;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\TipoDocumento;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\MatriculaOnlineService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class MatriculaOnlineSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Unidade $unidade;

    protected Curso $curso;

    protected Serie $serie;

    protected Turma $turma;

    protected PeriodoLetivo $periodoLetivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);

        TipoVinculo::firstOrCreate(['nome' => 'Responsável Legal']);
        TipoVinculo::firstOrCreate(['nome' => 'Mãe']);
        TipoVinculo::firstOrCreate(['nome' => 'Pai']);

        TipoDocumento::firstOrCreate(['nome' => 'Certidão de Nascimento / RG do Aluno']);
        TipoDocumento::firstOrCreate(['nome' => 'Documento de Identidade do Responsável']);

        $this->unidade = Unidade::create([
            'nome' => 'Unidade Central Torre360',
            'flag_ativo' => true,
        ]);

        $this->curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental II',
            'nome_interno' => 'EF II',
            'unidade_id' => $this->unidade->id,
        ]);

        $this->serie = Serie::create([
            'nome' => '7º Ano',
            'curso_id' => $this->curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);

        $this->periodoLetivo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
        ]);

        $turno = Turno::firstOrCreate(
            ['nome' => 'Matutino'],
            ['hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']
        );

        $this->turma = Turma::create([
            'nome' => '7º Ano A - Matutino',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);
    }

    public function test_rota_publica_de_matricula_online_carrega_com_sucesso(): void
    {
        $response = $this->get(route('matricular.online'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(MatriculaOnlineWizard::class);
        $response->assertSee('Matrícula Online para Novos Alunos');
    }

    public function test_wizard_avanca_etapas_com_validacao_reativa(): void
    {
        Livewire::test(MatriculaOnlineWizard::class)
            // Passo 1 sem turma
            ->set('turma_id', null)
            ->call('avancarPasso')
            ->assertHasErrors(['turma_id'])
            // Seleciona unidade, curso, série, turma e avança para o passo 2
            ->set('unidade_id', $this->unidade->id)
            ->set('curso_id', $this->curso->id)
            ->set('serie_id', $this->serie->id)
            ->set('turma_id', $this->turma->id)
            ->call('avancarPasso')
            ->assertSet('passoAtual', 2)
            // Passo 2 sem nome do aluno
            ->set('aluno_nome', '')
            ->call('avancarPasso')
            ->assertHasErrors(['aluno_nome'])
            // Preenche dados do aluno e avança para o passo 3
            ->set('aluno_nome', 'Lucas Gabriel Silva')
            ->set('aluno_cpf', '111.222.333-44')
            ->set('aluno_data_nascimento', '2013-05-10')
            ->call('avancarPasso')
            ->assertSet('passoAtual', 3)
            // Passo 3 sem responsável
            ->set('responsavel_nome', '')
            ->call('avancarPasso')
            ->assertHasErrors(['responsavel_nome'])
            // Preenche dados do responsável e avança para o passo 4
            ->set('responsavel_nome', 'Mariana Silva')
            ->set('responsavel_cpf', '555.666.777-88')
            ->set('responsavel_email', 'mariana.silva@teste.com')
            ->set('responsavel_telefone', '(11) 98765-4321')
            ->set('cep', '01001-000')
            ->set('logradouro', 'Praça da Sé')
            ->set('numero', '100')
            ->set('bairro', 'Sé')
            ->set('cidade_nome', 'São Paulo')
            ->set('estado_sigla', 'SP')
            ->call('avancarPasso')
            ->assertSet('passoAtual', 4)
            // Avança do passo 4 de documentos para o passo 5
            ->call('avancarPasso')
            ->assertSet('passoAtual', 5)
            // Passo 5 sem aceitar os termos
            ->set('aceite_contrato', false)
            ->call('finalizarMatricula')
            ->assertHasErrors(['aceite_contrato']);
    }

    public function test_service_processa_matricula_criando_todas_as_entidades_e_vinculos(): void
    {
        Storage::fake('local');
        Queue::fake([ValidarDocumentoComIaJob::class]);
        $service = app(MatriculaOnlineService::class);

        $dados = [
            'turma_id' => $this->turma->id,
            'aluno' => [
                'nome' => 'Beatriz Souza',
                'cpf' => '999.888.777-66',
                'data_nascimento' => '2012-08-20',
                'sexo' => 'feminino',
                'cor_raca' => 'Branca',
                'necessidades_especiais' => false,
            ],
            'responsavel' => [
                'nome' => 'Carlos Souza',
                'cpf' => '333.444.555-66',
                'email' => 'carlos.souza@teste.com',
                'telefone' => '(11) 97777-8888',
                'tipo_vinculo_id' => TipoVinculo::where('nome', 'Pai')->first()->id,
                'cep' => '01310-100',
                'logradouro' => 'Avenida Paulista',
                'numero' => '1578',
                'bairro' => 'Bela Vista',
                'cidade_nome' => 'São Paulo',
                'estado_sigla' => 'SP',
            ],
        ];

        $arquivos = [
            'documento_aluno' => UploadedFile::fake()->create('rg_aluno.pdf', 200, 'application/pdf'),
            'documento_responsavel' => UploadedFile::fake()->create('cnh_responsavel.jpg', 300, 'image/jpeg'),
        ];

        $matricula = $service->processarMatricula($dados, $arquivos);

        // 1. Matrícula criada com situação PENDENTE
        $this->assertInstanceOf(Matricula::class, $matricula);
        $this->assertEquals(SituacaoMatricula::PENDENTE, $matricula->situacao);
        $this->assertEquals($this->turma->id, $matricula->turma_id);

        // 2. Aluno criado
        $aluno = $matricula->pessoa;
        $this->assertEquals('Beatriz Souza', $aluno->nome);
        $this->assertEquals('99988877766', $aluno->cpf);

        // 3. Responsável criado e vinculado
        $responsavel = Pessoa::where('cpf', '33344455566')->first();
        $this->assertNotNull($responsavel);
        $this->assertEquals('Carlos Souza', $responsavel->nome);
        $this->assertEquals('carlos.souza@teste.com', $responsavel->email);

        $this->assertDatabaseHas('aluno_responsavel', [
            'aluno_id' => $aluno->id,
            'responsavel_id' => $responsavel->id,
        ]);

        // 4. Endereço criado e vinculado aos dois
        $this->assertNotEmpty($responsavel->enderecos);
        $this->assertEquals('Avenida Paulista', $responsavel->enderecos->first()->logradouro);

        // 5. Contrato criado com log de assinatura eletrônica
        $contrato = $matricula->contrato;
        $this->assertNotNull($contrato);
        $this->assertStringContainsString('Aceite dos Termos Contratuais', $contrato->log_assinatura);
        $this->assertStringContainsString('Carlos Souza', $contrato->log_assinatura);

        // 6. Responsável Financeiro vinculado a 100%
        $this->assertDatabaseHas('responsavel_financeiro', [
            'contrato_id' => $contrato->id,
            'pessoa_id' => $responsavel->id,
            'percentual' => 100,
        ]);

        // 7. Documentos anexados
        $this->assertDatabaseHas('documento_inserido', [
            'matricula_id' => $matricula->id,
            'status' => SituacaoDocumento::EM_ANALISE,
            'nome_arquivo_original' => 'rg_aluno.pdf',
        ]);

        // 8. Conta de usuário criada para o responsável com papel responsavel
        $usuario = User::where('email', 'carlos.souza@teste.com')->first();
        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->hasRole('responsavel'));
        $this->assertTrue($usuario->pessoas()->where('pessoa.id', $responsavel->id)->exists());

        // 9. Job de IA enfileirado para os documentos anexados
        Queue::assertPushed(ValidarDocumentoComIaJob::class);
    }

    public function test_impede_matricula_quando_turma_atinge_capacidade_maxima(): void
    {
        $this->turma->update(['vagas_maximas' => 1]);

        $alunoExistente = Pessoa::create([
            'nome' => 'Aluno Antigo',
            'cpf' => '11111111111',
        ]);

        Matricula::create([
            'pessoa_id' => $alunoExistente->id,
            'turma_id' => $this->turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $service = app(MatriculaOnlineService::class);

        $dados = [
            'turma_id' => $this->turma->id,
            'aluno' => [
                'nome' => 'Novo Aluno Sem Vaga',
                'cpf' => '222.222.222-22',
            ],
            'responsavel' => [
                'nome' => 'Pai Sem Vaga',
                'cpf' => '333.333.333-33',
                'email' => 'pai.semvaga@teste.com',
            ],
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("A turma '{$this->turma->nome}' atingiu a lotação máxima de 1 vagas.");

        $service->processarMatricula($dados);
    }

    public function test_reaproveita_pessoa_existente_sem_duplicar_cpf(): void
    {
        $pessoaExistente = Pessoa::create([
            'nome' => 'Fernanda Lima Existente',
            'cpf' => '77788899900',
            'email' => 'fernanda@antigo.com',
        ]);

        $service = app(MatriculaOnlineService::class);

        $dados = [
            'turma_id' => $this->turma->id,
            'aluno' => [
                'nome' => 'Filho de Fernanda',
                'cpf' => '444.555.666-77',
            ],
            'responsavel' => [
                'nome' => 'Fernanda Lima Atualizada',
                'cpf' => '777.888.999-00', // Mesmo CPF
                'email' => 'fernanda@antigo.com',
            ],
        ];

        $matricula = $service->processarMatricula($dados);

        // Não deve criar nova pessoa para o responsável
        $this->assertEquals(1, Pessoa::where('cpf', '77788899900')->count());
        $responsavel = $matricula->contrato->responsaveisFinanceiros->first()->pessoa;
        $this->assertEquals($pessoaExistente->id, $responsavel->id);
    }

    public function test_tela_de_sucesso_exibe_resumo_e_protocolo_da_matricula(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Gabriel Medeiros',
            'cpf' => '88877766655',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $this->turma->id,
            'situacao' => SituacaoMatricula::PENDENTE,
        ]);

        $response = $this->withSession(['matricula_online_id' => $matricula->id])
            ->get(route('matricular.online.sucesso', $matricula));

        $response->assertStatus(200);
        $response->assertSee('Matrícula Efetuada com Sucesso');
        $response->assertSee('Gabriel Medeiros');
        $response->assertSee($this->turma->nome);
        $response->assertSee(str_pad($matricula->id, 6, '0', STR_PAD_LEFT));
    }
}
