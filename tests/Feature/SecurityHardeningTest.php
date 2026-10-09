<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Portal\Pages\CentralAtendimento;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
    }

    public function test_rota_de_auto_registro_no_admin_esta_desativada(): void
    {
        $response = $this->get('/admin/register');

        $response->assertNotFound();
    }

    public function test_central_atendimento_bloqueia_upload_de_arquivo_executavel_ou_invalido(): void
    {
        Storage::fake('local');

        $aluno = Pessoa::create(['nome' => 'Filho Teste', 'cpf' => '11122233344']);
        $responsavel = Pessoa::create(['nome' => 'Pai Teste', 'cpf' => '55566677788']);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('responsavel');
        $user->pessoas()->attach([$aluno->id, $responsavel->id]);

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma Teste', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $setor = AtendimentoSetor::create(['nome' => 'Secretaria', 'ativo' => true, 'ordem' => 1]);

        $arquivoMalicioso = UploadedFile::fake()->create('malware.exe', 50, 'application/x-msdownload');

        $this->actingAs($user);

        Livewire::test(CentralAtendimento::class)
            ->set('novoSetorId', $setor->id)
            ->set('novoMatriculaId', $matricula->id)
            ->set('novoAssunto', 'Dúvida com documento')
            ->set('novaMensagemInicial', 'Segue o arquivo em anexo')
            ->set('novoAnexo', $arquivoMalicioso)
            ->call('criarChamado')
            ->assertHasErrors(['novoAnexo']);
    }

    public function test_central_atendimento_salva_anexo_valido_no_disco_local(): void
    {
        Storage::fake('local');

        $aluno = Pessoa::create(['nome' => 'Filha Teste', 'cpf' => '22233344455']);
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('responsavel');
        $user->pessoas()->attach($aluno->id);

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma B', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $setor = AtendimentoSetor::create(['nome' => 'Financeiro', 'ativo' => true, 'ordem' => 1]);
        $arquivoPdf = UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf');

        $this->actingAs($user);

        Livewire::test(CentralAtendimento::class)
            ->set('novoSetorId', $setor->id)
            ->set('novoMatriculaId', $matricula->id)
            ->set('novoAssunto', 'Envio de comprovante de pagamento')
            ->set('novaMensagemInicial', 'Segue o comprovante em anexo para conferência.')
            ->set('novoAnexo', $arquivoPdf)
            ->call('criarChamado')
            ->assertHasNoErrors();

        $mensagem = AtendimentoMensagem::latest('id')->first();
        $this->assertNotNull($mensagem->anexo_path);
        Storage::disk('local')->assertExists($mensagem->anexo_path);
    }

    public function test_central_atendimento_impede_idor_em_chamados_de_outros_usuarios(): void
    {
        // Usuário A e seu Chamado
        $pessoaA = Pessoa::create(['nome' => 'Pessoa A', 'cpf' => '33344455566']);
        $userA = User::factory()->create(['activated_at' => now()]);
        $userA->assignRole('responsavel');
        $userA->pessoas()->attach($pessoaA->id);

        $setor = AtendimentoSetor::create(['nome' => 'Coordenação', 'ativo' => true, 'ordem' => 1]);
        $chamadoA = AtendimentoChamado::create([
            'setor_id' => $setor->id,
            'solicitante_id' => $pessoaA->id,
            'assunto' => 'Chamado Sigiloso do Aluno A',
            'status' => 'aberto',
        ]);

        // Usuário B tenta manipular o chamado A
        $pessoaB = Pessoa::create(['nome' => 'Pessoa B', 'cpf' => '77788899900']);
        $userB = User::factory()->create(['activated_at' => now()]);
        $userB->assignRole('responsavel');
        $userB->pessoas()->attach($pessoaB->id);

        $this->actingAs($userB);

        $component = Livewire::test(CentralAtendimento::class)
            ->set('chamadoSelecionadoId', $chamadoA->id)
            ->set('respostaTexto', 'Tentativa de injeção de mensagem indevida')
            ->call('enviarResposta');

        // A mensagem NÃO deve ser adicionada ao chamado A
        $this->assertDatabaseMissing('atendimento_mensagens', [
            'chamado_id' => $chamadoA->id,
            'mensagem' => 'Tentativa de injeção de mensagem indevida',
        ]);
    }

    public function test_cabecalho_permissions_policy_permite_camera_no_mesmo_dominio(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_login_com_credenciais_incorretas_nao_enumera_usuario_desativado(): void
    {
        // Usuário existe mas está desativado
        User::factory()->create([
            'email' => 'desativado@torre360.com.br',
            'password' => Hash::make('Senha@Forte1234'),
            'activated_at' => null,
            'deactivated_at' => now()->subDay(),
        ]);

        $component = Livewire::test(CustomLogin::class)
            ->fillForm([
                'email' => 'desativado@torre360.com.br',
                'password' => 'SenhaErrada123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        // A mensagem de erro NÃO deve revelar que a conta está desativada
        $mensagens = collect($component->errors()->all())->implode(' ');
        $this->assertStringNotContainsString('Esta conta está desativada', $mensagens);
        $this->assertFalse(auth()->check());
    }

    public function test_login_com_credenciais_corretas_em_conta_desativada_bloqueia_e_desloga(): void
    {
        User::factory()->create([
            'email' => 'desativado.correto@torre360.com.br',
            'password' => Hash::make('Senha@Forte1234'),
            'activated_at' => null,
            'deactivated_at' => now()->subDay(),
        ]);

        $component = Livewire::test(CustomLogin::class)
            ->fillForm([
                'email' => 'desativado.correto@torre360.com.br',
                'password' => 'Senha@Forte1234',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        // Quando as credenciais são legítimas, a mensagem específica de conta desativada é exibida e o login é desfeito
        $mensagens = collect($component->errors()->all())->implode(' ');
        $this->assertStringContainsString('Esta conta está desativada', $mensagens);
        $this->assertFalse(auth()->check(), 'Usuário não deve permanecer autenticado.');
    }

    public function test_cadastro_de_usuario_exige_politica_de_senha_forte(): void
    {
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        // Senha fraca com apenas 8 caracteres numéricos
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Novo Usuario Teste',
                'email' => 'novo.usuario@torre360.com.br',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_tentativa_de_login_com_email_inexistente_gera_log_de_auditoria_sem_vazar_senha(): void
    {
        $emailInexistente = 'hacker.invasor@tentativa-externa.com';
        $senhaTentada = 'SenhaSuperSecreta123!';

        Livewire::test(CustomLogin::class)
            ->fillForm([
                'email' => $emailInexistente,
                'password' => $senhaTentada,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        // Deve existir log de auditoria no canal auth
        $log = Activity::where('log_name', 'auth')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'O evento de login falho deve gerar um registro de auditoria.');
        $this->assertStringContainsString('não localizado', $log->description);
        $this->assertStringContainsString($emailInexistente, $log->description);
        $this->assertEquals($emailInexistente, $log->properties['email_tentado'] ?? null);

        // Segurança e LGPD estrita: a senha digitada NUNCA pode ser armazenada nos logs!
        $todasPropriedades = json_encode($log->properties);
        $this->assertStringNotContainsString($senhaTentada, $todasPropriedades, 'A senha tentada jamais deve ser gravada no log de auditoria.');
        $this->assertArrayNotHasKey('password', $log->properties->toArray(), 'A chave password não pode existir no log.');
    }

    public function test_tentativa_de_login_com_senha_errada_em_usuario_existente_gera_log_de_auditoria_vinculado(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario.cadastrado@torre360.com.br',
            'password' => Hash::make('Senha@Correta1234'),
            'activated_at' => now(),
        ]);

        $senhaIncorreta = 'SenhaTotalmenteErrada999!';

        Livewire::test(CustomLogin::class)
            ->fillForm([
                'email' => $user->email,
                'password' => $senhaIncorreta,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $log = Activity::where('log_name', 'auth')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Tentativa de login falho para usuário existente deve gerar log vinculado ao modelo User.');
        $this->assertStringContainsString('usuário cadastrado', $log->description);
        $this->assertStringContainsString($user->name, $log->description);
        $this->assertEquals($user->email, $log->properties['email_tentado'] ?? null);

        // Verifica que a senha não foi gravada
        $todasPropriedades = json_encode($log->properties);
        $this->assertStringNotContainsString($senhaIncorreta, $todasPropriedades);
    }
}
