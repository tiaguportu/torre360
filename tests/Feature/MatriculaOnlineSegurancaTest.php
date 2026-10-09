<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Livewire\MatriculaOnline\MatriculaOnlineWizard;
use App\Models\AlunoResponsavel;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Notifications\WelcomeUserMail;
use App\Services\MatriculaOnlineService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O fluxo /matricular-online é público e cria pessoas, matrícula, contrato e conta de acesso: o CPF/e-mail digitado
 * não pode servir de chave para se passar por uma família que já existe.
 */
class MatriculaOnlineSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Http::preventStrayRequests();

        // O .env local tem chaves reais do reCAPTCHA: cada teste decide se o captcha está ligado.
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        TipoVinculo::firstOrCreate(['nome' => 'Responsável Legal']);

        $unidade = Unidade::create(['nome' => 'Unidade Central', 'flag_ativo' => true]);
        $curso = Curso::create(['nome_externo' => 'Fundamental II', 'nome_interno' => 'EF II', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '7º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-20']);
        $turno = Turno::firstOrCreate(['nome' => 'Matutino'], ['hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);

        $this->turma = Turma::create([
            'nome' => '7º Ano A',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);
    }

    /**
     * @param  array<string, mixed>  $aluno
     * @param  array<string, mixed>  $responsavel
     * @return array<string, mixed>
     */
    private function dados(array $aluno = [], array $responsavel = []): array
    {
        return [
            'turma_id' => $this->turma->id,
            'aluno' => $aluno + ['nome' => 'Aluno Novo Teste', 'cpf' => '', 'data_nascimento' => '2013-05-10'],
            'responsavel' => $responsavel + [
                'nome' => 'Quem Preencheu',
                'cpf' => '111.222.333-44',
                'email' => 'quem.preencheu@teste.com',
                'telefone' => '(11) 98888-7777',
                'tipo_vinculo_id' => TipoVinculo::first()->id,
            ],
        ];
    }

    public function test_email_do_formulario_nao_e_gravado_em_responsavel_existente_sem_email(): void
    {
        Notification::fake();

        $vitima = Pessoa::create(['nome' => 'Responsável Cadastrado', 'cpf' => '99988877766']);

        app(MatriculaOnlineService::class)->processarMatricula($this->dados(
            responsavel: ['cpf' => '999.888.777-66', 'email' => 'atacante@teste.com'],
        ));

        $this->assertNull($vitima->fresh()->email, 'o e-mail digitado não pode ser gravado num cadastro que já existia');
        $this->assertDatabaseMissing('users', ['email' => 'atacante@teste.com']);
        Notification::assertNothingSent();
    }

    public function test_conta_do_portal_usa_o_email_do_cadastro_existente_e_nao_o_do_formulario(): void
    {
        Notification::fake();

        $dono = Pessoa::create(['nome' => 'Dona do Cadastro', 'cpf' => '55544433322', 'email' => 'dona@teste.com']);

        app(MatriculaOnlineService::class)->processarMatricula($this->dados(
            responsavel: ['cpf' => '555.444.333-22', 'email' => 'outro.email@teste.com'],
        ));

        $this->assertDatabaseMissing('users', ['email' => 'outro.email@teste.com']);

        $usuario = User::where('email', 'dona@teste.com')->firstOrFail();
        $this->assertTrue($usuario->pessoas()->where('pessoa.id', $dono->id)->exists());
        Notification::assertSentTo($usuario, WelcomeUserMail::class);
    }

    public function test_cpf_de_aluno_existente_nao_vincula_um_responsavel_desconhecido(): void
    {
        Notification::fake();

        $aluno = Pessoa::create(['nome' => 'Aluno de Outra Família', 'cpf' => '12345678909']);
        $paiVerdadeiro = Pessoa::create(['nome' => 'Pai Verdadeiro', 'cpf' => '98765432100', 'email' => 'pai@teste.com']);
        AlunoResponsavel::create([
            'aluno_id' => $aluno->id,
            'responsavel_id' => $paiVerdadeiro->id,
            'tipo_vinculo_id' => TipoVinculo::first()->id,
        ]);

        try {
            app(MatriculaOnlineService::class)->processarMatricula($this->dados(
                aluno: ['cpf' => '123.456.789-09'],
                responsavel: ['nome' => 'Estranho', 'cpf' => '321.654.987-00', 'email' => 'estranho@teste.com'],
            ));
            $this->fail('deveria recusar vincular um estranho a um aluno que já existe');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('procure a secretaria', $e->getMessage());
        }

        // Nada ficou para trás: a transação desfez o cadastro do "responsável" e não houve conta nem matrícula.
        $this->assertDatabaseMissing('pessoa', ['cpf' => '32165498700']);
        $this->assertDatabaseMissing('users', ['email' => 'estranho@teste.com']);
        $this->assertSame(0, Matricula::count());
        Notification::assertNothingSent();
    }

    public function test_familia_ja_vinculada_pode_matricular_o_proprio_aluno_existente(): void
    {
        Notification::fake();

        $aluno = Pessoa::create(['nome' => 'Filho da Casa', 'cpf' => '12345678909']);
        $mae = Pessoa::create(['nome' => 'Mãe da Casa', 'cpf' => '98765432100', 'email' => 'mae@teste.com']);
        AlunoResponsavel::create([
            'aluno_id' => $aluno->id,
            'responsavel_id' => $mae->id,
            'tipo_vinculo_id' => TipoVinculo::first()->id,
        ]);

        $matricula = app(MatriculaOnlineService::class)->processarMatricula($this->dados(
            aluno: ['cpf' => '123.456.789-09'],
            responsavel: ['cpf' => '987.654.321-00', 'email' => 'mae@teste.com'],
        ));

        $this->assertSame($aluno->id, $matricula->pessoa_id);
        $this->assertEquals(SituacaoMatricula::PENDENTE, $matricula->situacao);
        // Responsável já cadastrado: a identidade não foi verificada e isso fica registrado para a secretaria.
        $this->assertStringContainsString('identidade', $matricula->contrato->log_assinatura);
    }

    public function test_cadastro_novo_continua_gerando_conta_e_boas_vindas(): void
    {
        Notification::fake();

        app(MatriculaOnlineService::class)->processarMatricula($this->dados());

        $usuario = User::where('email', 'quem.preencheu@teste.com')->firstOrFail();
        $this->assertTrue($usuario->hasRole('responsavel'));
        Notification::assertSentTo($usuario, WelcomeUserMail::class);
    }

    public function test_conta_existente_de_outra_pessoa_nao_e_amarrada_a_um_cadastro_novo(): void
    {
        Notification::fake();

        $equipe = User::create(['name' => 'Funcionária', 'email' => 'quem.preencheu@teste.com', 'password' => bcrypt('x')]);

        app(MatriculaOnlineService::class)->processarMatricula($this->dados());

        $this->assertSame(0, $equipe->pessoas()->count());
        $this->assertFalse($equipe->fresh()->hasRole('responsavel'));
    }

    private function preencherWizard(Testable $teste): Testable
    {
        return $teste
            ->set('turma_id', $this->turma->id)
            ->set('aluno_nome', 'Aluno do Wizard')
            ->set('aluno_data_nascimento', '2013-05-10')
            ->set('responsavel_nome', 'Responsável do Wizard')
            ->set('responsavel_cpf', '444.555.666-77')
            ->set('responsavel_email', 'wizard@teste.com')
            ->set('responsavel_telefone', '(11) 97777-6666')
            ->set('responsavel_tipo_vinculo_id', TipoVinculo::first()->id)
            ->set('cep', '01001-000')
            ->set('logradouro', 'Praça da Sé')
            ->set('numero', '1')
            ->set('bairro', 'Sé')
            ->set('cidade_nome', 'São Paulo')
            ->set('estado_sigla', 'SP')
            ->set('aceite_contrato', true)
            ->set('aceite_lgpd', true)
            ->set('aceite_regimento', true);
    }

    private function configurarRecaptcha(): void
    {
        config(['services.recaptcha.site_key' => 'site-key-teste', 'services.recaptcha.secret' => 'secret-teste']);
    }

    public function test_finalizar_sem_token_do_recaptcha_e_recusado(): void
    {
        $this->configurarRecaptcha();

        $this->preencherWizard(Livewire::test(MatriculaOnlineWizard::class))
            ->call('finalizarMatricula')
            ->assertHasErrors(['recaptcha_token']);

        $this->assertSame(0, Matricula::count());
        Http::assertNothingSent();
    }

    public function test_finalizar_com_recaptcha_valido_conclui_e_redireciona_para_link_assinado(): void
    {
        $this->configurarRecaptcha();
        Notification::fake();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9], 200)]);

        $teste = $this->preencherWizard(Livewire::test(MatriculaOnlineWizard::class))
            ->set('recaptcha_token', 'token-de-teste')
            ->call('finalizarMatricula')
            ->assertHasNoErrors();

        $matricula = Matricula::firstOrFail();
        $teste->assertRedirect();
        $destino = (string) data_get($teste->effects, 'redirect');

        $this->assertStringContainsString("/matricular-online/sucesso/{$matricula->id}", $destino);
        $this->assertStringContainsString('signature=', $destino);
        $this->get($destino)->assertOk();
    }

    public function test_recaptcha_com_score_baixo_e_recusado(): void
    {
        $this->configurarRecaptcha();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.1], 200)]);

        $this->preencherWizard(Livewire::test(MatriculaOnlineWizard::class))
            ->set('recaptcha_token', 'token-de-robo')
            ->call('finalizarMatricula')
            ->assertHasErrors(['recaptcha_token']);

        $this->assertSame(0, Matricula::count());
    }

    public function test_excesso_de_tentativas_por_ip_bloqueia_o_envio(): void
    {
        $this->configurarRecaptcha();
        config(['seguranca.matricula_online.max_tentativas' => 2]);
        RateLimiter::clear('matricula-online:127.0.0.1');

        $teste = $this->preencherWizard(Livewire::test(MatriculaOnlineWizard::class));

        // As duas primeiras (sem token, portanto recusadas) já contam como tentativa.
        $teste->call('finalizarMatricula')->assertHasErrors(['recaptcha_token']);
        $teste->call('finalizarMatricula')->assertHasErrors(['recaptcha_token']);

        $teste->call('finalizarMatricula')
            ->assertHasNoErrors()
            ->assertSet('mensagemErro', 'Recebemos muitas tentativas deste dispositivo. Aguarde alguns minutos e tente novamente, ou fale com a secretaria da escola.');

        $this->assertSame(0, Matricula::count());
    }

    public function test_erro_inesperado_nao_expoe_detalhes_internos_ao_publico(): void
    {
        $this->mock(MatriculaOnlineService::class, function ($mock): void {
            $mock->shouldReceive('processarMatricula')->andThrow(new \RuntimeException('SQLSTATE[HY000]: tabela secreta falhou'));
        });

        $this->preencherWizard(Livewire::test(MatriculaOnlineWizard::class))
            ->call('finalizarMatricula')
            ->assertSet('processando', false)
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('tabela secreta');
    }

    public function test_tela_de_sucesso_exige_link_assinado(): void
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Sigiloso', 'cpf' => '88877766655']);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $this->turma->id,
            'situacao' => SituacaoMatricula::PENDENTE,
        ]);

        // Sem assinatura (alguém tentando números de matrícula em sequência): negado e sem dados.
        $this->get(route('matricular.online.sucesso', $matricula))
            ->assertForbidden()
            ->assertDontSee('Aluno Sigiloso');

        $assinada = URL::temporarySignedRoute('matricular.online.sucesso', now()->addHour(), ['matricula' => $matricula->id]);
        $this->get($assinada)->assertOk()->assertSee('Aluno Sigiloso');

        // Link expirado deixa de abrir.
        $expirada = URL::temporarySignedRoute('matricular.online.sucesso', now()->subMinute(), ['matricula' => $matricula->id]);
        $this->get($expirada)->assertForbidden();
    }
}
