<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\StatusAssinante;
use App\Enums\StatusDocumentoAssinatura;
use App\Enums\TipoProcessoFuncionario;
use App\Enums\TipoTemplateDocumento;
use App\Models\CancelamentoMatricula;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\DocumentoAssinatura;
use App\Models\Funcionario;
use App\Models\ItemProcessoFuncionario;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CancelamentoMatriculaService;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\Documentos\SignatariosSugeridosService;
use App\Services\ProcessoFuncionarioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Assinatura eletrônica de documentos avulsos (Onda 69): criação a partir de um modelo, envio ao Assinafy, retorno por
 * consulta e webhook, certificado, reação do registro ligado ao documento, acesso aos PDFs e permissões.
 *
 * "Hoje" é quinta-feira, 22/10/2026. Nenhum teste chama o Assinafy de verdade: tudo passa por Http::fake.
 */
class DocumentosAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private const ASSINAFY_ID = 'DOC-AVULSO-1';

    private const CERTIFICADO = "%PDF-1.4\n1 0 obj<<>>endobj\n%certificado de assinaturas\n%%EOF";

    private User $secretaria;

    private User $coordenador;

    private User $admin;

    private Matricula $matricula;

    private Contrato $contrato;

    private Pessoa $mae;

    private Unidade $unidade;

    private TemplateDocumento $modelo;

    private CancelamentoMatricula $cancelamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
        Storage::fake('local');

        // O .env local pode ter credenciais reais do Assinafy: o teste define as suas e proíbe qualquer chamada solta.
        config([
            'services.assinafy.key' => 'CHAVE-DE-TESTE',
            'services.assinafy.account_id' => 'CONTA-TESTE',
            'services.assinafy.url' => 'https://assinafy.teste/v1',
            'services.assinafy.webhook_secret' => null,
        ]);
        Http::preventStrayRequests();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel', 'aluno'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny', 'View', 'Create', 'Enviar', 'Cancelar'] as $acao) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:DocumentoAssinatura", 'guard_name' => 'web']));
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:DocumentoAssinatura", 'guard_name' => 'web']));
        }

        foreach (['ViewAny', 'View'] as $acao) {
            Role::findByName('coordenador')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:DocumentoAssinatura", 'guard_name' => 'web']));
        }

        // RH é da administração: a secretaria não vê Admissão e Desligamento.
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Concluir'] as $acao) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:ProcessoFuncionario", 'guard_name' => 'web']));
        }

        $this->secretaria = $this->usuario('secretaria');
        $this->coordenador = $this->usuario('coordenador');
        $this->admin = $this->usuario('admin');

        // Unidade → curso → série → turma, com a diretora como representante legal da escola.
        $this->unidade = Unidade::create(['nome' => 'Unidade Sede', 'cnpj' => '12.345.678/0001-90']);
        $this->unidade->representantesLegais()->attach(Pessoa::create(['nome' => 'Diretora Clara', 'email' => 'clara@escola.test'])->id, ['cargo' => 'Diretora']);
        $curso = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $turma = Turma::factory()->create(['serie_id' => $serie->id]);
        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Ana Lima', 'data_nascimento' => '2014-03-10'])->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
        $this->contrato = Contrato::create(['matricula_id' => $this->matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
        $this->mae = Pessoa::create(['nome' => 'Marina Lima', 'email' => 'marina@teste.com']);
        ResponsavelFinanceiro::create(['contrato_id' => $this->contrato->id, 'pessoa_id' => $this->mae->id, 'percentual' => 100]);

        $this->modelo = $this->modelo(TipoTemplateDocumento::DistratoMatricula, 'Distrato de Matrícula');
        $this->cancelamento = $this->cancelamentoAprovado();
    }

    private function servico(): AssinaturaDocumentoService
    {
        return app(AssinaturaDocumentoService::class);
    }

    private function usuario(string $papel, array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos + ['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /**
     * As permissões valem pelo papel ativo da sessão, que é global no teste: quem age é declarado antes de cada chamada.
     */
    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function modelo(TipoTemplateDocumento $tipo, string $nome, bool $ativo = true, ?string $conteudo = null): TemplateDocumento
    {
        return TemplateDocumento::create([
            'nome' => $nome,
            'tipo' => $tipo,
            'conteudo' => $conteudo ?? '<p>{{ALUNO_NOME}} — saída em {{DATA_SAIDA}}.</p>{{BLOCO_ASSINATURAS}}',
            'validade_dias' => 0,
            'is_ativo' => $ativo,
        ]);
    }

    private function cancelamentoAprovado(): CancelamentoMatricula
    {
        $servico = app(CancelamentoMatriculaService::class);
        $pedido = $servico->solicitar($this->matricula, [
            'motivo' => MotivoCancelamentoMatricula::MudancaCidade,
            'data_efetiva' => '2026-10-31',
        ], null, OrigemCancelamentoMatricula::Escola);

        return $servico->aprovar($pedido, $this->secretaria, []);
    }

    /**
     * @return list<array{nome: string, email: string, papel: string}>
     */
    private function assinantes(): array
    {
        return [
            ['nome' => 'Marina Lima', 'email' => 'marina@teste.com', 'papel' => 'Responsável financeiro'],
            ['nome' => 'Diretora Clara', 'email' => 'clara@escola.test', 'papel' => 'Escola'],
        ];
    }

    private function documento(?TemplateDocumento $modelo = null, ?object $registro = null, ?array $assinantes = null): DocumentoAssinatura
    {
        return $this->servico()->criar($modelo ?? $this->modelo, $registro ?? $this->cancelamento, $assinantes ?? $this->assinantes(), $this->secretaria);
    }

    private function documentoEnviado(?TemplateDocumento $modelo = null, ?object $registro = null): DocumentoAssinatura
    {
        $documento = $this->documento($modelo, $registro);
        $this->fakeAssinafy();

        return $this->servico()->enviar($documento, $this->secretaria);
    }

    /**
     * Um único fake para todas as rotas do Assinafy que o fluxo usa. Cada chamada recomeça do zero: Http::fake acumula
     * respostas e vale a primeira que casa.
     *
     * @param  list<array<string, mixed>>  $assinantesDaApi  o que a consulta devolve em assignment.signers
     */
    private function fakeAssinafy(string $statusDoDocumento = 'pending_signature', array $assinantesDaApi = [], ?string $certificado = null, ?string $falhaEm = null): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();

        Http::fake(function (Request $requisicao) use ($statusDoDocumento, $assinantesDaApi, $certificado, $falhaEm) {
            $metodo = $requisicao->method();
            $caminho = (string) parse_url($requisicao->url(), PHP_URL_PATH);

            if ($metodo === 'POST' && str_ends_with($caminho, '/documents')) {
                return $falhaEm === 'upload' ? Http::response(['message' => 'arquivo inválido'], 422) : Http::response(['id' => self::ASSINAFY_ID], 200);
            }

            if ($metodo === 'GET' && str_ends_with($caminho, '/signers')) {
                return Http::response(['data' => []], 200);
            }

            if ($metodo === 'POST' && str_ends_with($caminho, '/signers')) {
                return Http::response(['id' => 'SIGNER-'.substr(md5((string) $requisicao['email']), 0, 8)], 200);
            }

            if ($metodo === 'POST' && str_ends_with($caminho, '/assignments')) {
                if ($falhaEm === 'assignments') {
                    return Http::response(['message' => 'limite do plano atingido'], 422);
                }

                $enderecos = array_map(
                    fn (array $assinante): array => ['signer_id' => $assinante['id'], 'url' => 'https://assinafy.teste/sign/'.$assinante['id'].'?token=T'.$assinante['id']],
                    $requisicao['signers'],
                );

                return Http::response(['signing_urls' => $enderecos], 200);
            }

            if ($metodo === 'GET' && str_ends_with($caminho, '/download/certificated')) {
                return $certificado === null ? Http::response('', 404) : Http::response($certificado, 200);
            }

            if ($metodo === 'GET' && preg_match('#/documents/[^/]+$#', $caminho)) {
                return Http::response(['data' => ['id' => self::ASSINAFY_ID, 'status' => $statusDoDocumento, 'assignment' => ['signers' => $assinantesDaApi]]], 200);
            }

            if ($metodo === 'DELETE') {
                return Http::response(null, 204);
            }

            return Http::response(['message' => 'rota não prevista no teste: '.$metodo.' '.$caminho], 500);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function todosAssinaram(): array
    {
        return [
            ['email' => 'marina@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z'],
            ['email' => 'clara@escola.test', 'completed' => true, 'signed_at' => '2026-10-22T14:30:00Z'],
        ];
    }

    private function pastasDoAssinafy(string $metodo, string $final): int
    {
        return Http::recorded()->filter(fn (array $par): bool => $par[0]->method() === $metodo && str_ends_with((string) parse_url($par[0]->url(), PHP_URL_PATH), $final))->count();
    }

    // --- Criar -------------------------------------------------------------------------------------------------------

    public function test_criar_monta_o_texto_do_modelo_o_pdf_e_a_lista_de_assinantes(): void
    {
        $documento = $this->documento();

        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->status);
        $this->assertSame('Distrato de Matrícula — Ana Lima', $documento->titulo);
        $this->assertStringContainsString('Ana Lima', $documento->conteudo);
        $this->assertStringContainsString('31/10/2026', $documento->conteudo);
        $this->assertStringNotContainsString('{{', $documento->conteudo, 'nenhuma variável fica sem troca');
        $this->assertStringContainsString('Marina Lima', $documento->conteudo, 'o bloco de assinaturas lista os assinantes');

        $this->assertSame(CancelamentoMatricula::class, $documento->documentavel_type);
        $this->assertSame($this->cancelamento->id, $documento->documentavel_id);
        $this->assertSame($this->secretaria->id, $documento->criado_por_user_id);

        $this->assertSame(['Marina Lima', 'Diretora Clara'], $documento->assinantes()->pluck('nome')->all());
        $this->assertSame([StatusAssinante::Pendente, StatusAssinante::Pendente], $documento->assinantes()->get()->pluck('status')->all());

        $this->assertSame("documentos_assinatura/{$documento->id}/original.pdf", $documento->arquivo_original_path);
        $pdf = Storage::disk('local')->get($documento->arquivo_original_path);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(hash('sha256', $pdf), $documento->arquivo_original_hash);
    }

    public function test_criar_nao_manda_nada_ao_assinafy_e_registra_o_historico_sem_dados_pessoais(): void
    {
        $this->documento();

        Http::assertNothingSent();

        $log = Activity::query()->where('log_name', 'assinaturas')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->secretaria->id, $log->causer_id);
        $this->assertStringNotContainsString('marina@teste.com', json_encode($log->properties, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Ana Lima', json_encode($log->properties, JSON_UNESCAPED_UNICODE));
    }

    public function test_criar_recusa_modelo_inativo_e_modelo_de_outro_tipo_de_registro(): void
    {
        $inativo = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, 'Contrato de Trabalho', ativo: false);

        try {
            $this->documento($inativo);
            $this->fail('um modelo inativo não vai para assinatura');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('inativo', $e->getMessage());
        }

        $termo = $this->modelo(TipoTemplateDocumento::TermoConfidencialidade, 'Termo');

        try {
            $this->documento($termo);
            $this->fail('um modelo de funcionário não usa dados de cancelamento');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Funcionário', $e->getMessage());
        }

        $this->assertSame(0, DocumentoAssinatura::query()->count());
    }

    public function test_criar_confere_a_lista_de_assinantes(): void
    {
        $casos = [
            'sem ninguém' => [[], 'ao menos um assinante'],
            'e-mail inválido' => [[['nome' => 'Fulano', 'email' => 'isso-nao-e-email']], 'e-mail válido'],
            'sem nome' => [[['nome' => '', 'email' => 'a@b.com']], 'nome e de um e-mail válido'],
            'e-mail repetido' => [[['nome' => 'A', 'email' => 'x@y.com'], ['nome' => 'B', 'email' => 'X@Y.com']], 'mais de uma vez'],
            'gente demais' => [array_map(fn (int $i): array => ['nome' => "Pessoa {$i}", 'email' => "p{$i}@teste.com"], range(1, 11)), 'limite é de 10'],
        ];

        foreach ($casos as $nome => [$lista, $trecho]) {
            try {
                $this->documento(null, null, $lista);
                $this->fail("deveria recusar: {$nome}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }

        $this->assertSame(0, DocumentoAssinatura::query()->count());
    }

    public function test_criar_nao_repete_documento_em_andamento_do_mesmo_modelo_e_registro(): void
    {
        $primeiro = $this->documento();

        try {
            $this->documento();
            $this->fail('já existe um em andamento');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Já existe um documento', $e->getMessage());
        }

        // Cancelado o primeiro, pode criar outro.
        $this->servico()->cancelar($primeiro, $this->secretaria);

        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $this->documento()->status);
    }

    public function test_documento_guarda_a_copia_do_texto_mesmo_se_o_modelo_mudar_depois(): void
    {
        $documento = $this->documento();
        $original = $documento->conteudo;

        $this->modelo->update(['conteudo' => '<p>Texto novo, sem nada a ver.</p>']);

        $this->assertSame($original, $documento->refresh()->conteudo);
    }

    public function test_variaveis_sem_troca_do_modelo_nao_vao_para_o_documento_como_chaves_soltas(): void
    {
        $modelo = $this->modelo(TipoTemplateDocumento::Personalizado, 'Declaração livre', true, '<p>{{ALUNO_NOME}}</p>');

        $documento = $this->documento($modelo, $this->matricula);

        $this->assertStringContainsString('Ana Lima', $documento->conteudo);
        $this->assertSame(Matricula::class, $documento->documentavel_type);
    }

    // --- Sugestão de assinantes -------------------------------------------------------------------------------------

    public function test_sugestao_traz_o_responsavel_financeiro_e_o_representante_da_escola_e_avisa_quem_nao_tem_email(): void
    {
        $semEmail = Pessoa::create(['nome' => 'Pai Sem Email']);
        ResponsavelFinanceiro::create(['contrato_id' => $this->contrato->id, 'pessoa_id' => $semEmail->id, 'percentual' => 0]);

        $sugestao = app(SignatariosSugeridosService::class)->para($this->cancelamento);

        $this->assertSame([
            ['nome' => 'Marina Lima', 'email' => 'marina@teste.com', 'papel' => 'Responsável financeiro'],
            ['nome' => 'Diretora Clara', 'email' => 'clara@escola.test', 'papel' => 'Escola'],
        ], $sugestao['assinantes']);
        $this->assertSame(['Pai Sem Email'], $sugestao['sem_email']);
    }

    public function test_sugestao_do_funcionario_traz_ele_e_o_representante_da_escola(): void
    {
        $funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id,
            'cargo' => 'Professora',
        ]);

        $sugestao = app(SignatariosSugeridosService::class)->para($funcionario);

        $this->assertSame('Marta Souza', $sugestao['assinantes'][0]['nome']);
        $this->assertSame('Funcionário(a)', $sugestao['assinantes'][0]['papel']);
    }

    // --- Enviar ------------------------------------------------------------------------------------------------------

    public function test_enviar_manda_o_pdf_cadastra_os_assinantes_e_guarda_o_link_de_cada_um(): void
    {
        $documento = $this->documento();
        $this->fakeAssinafy();

        $enviado = $this->servico()->enviar($documento, $this->secretaria);

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $enviado->status);
        $this->assertSame(self::ASSINAFY_ID, $enviado->assinafy_id);
        $this->assertSame('pending_signature', $enviado->assinafy_status);
        $this->assertNotNull($enviado->enviado_em);

        $assinantes = $enviado->assinantes()->get();

        foreach ($assinantes as $assinante) {
            $this->assertNotEmpty($assinante->assinafy_signer_id);
            $this->assertStringContainsString($assinante->assinafy_signer_id, (string) $assinante->link_assinatura, 'cada um com o seu link');
        }

        $this->assertNotSame($assinantes[0]->link_assinatura, $assinantes[1]->link_assinatura);

        $this->assertSame(1, $this->pastasDoAssinafy('POST', '/documents'), 'o PDF sobe uma vez');
        $this->assertSame(1, $this->pastasDoAssinafy('POST', '/assignments'));
        Http::assertSent(fn (Request $r): bool => str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/assignments')
            && count($r['signers']) === 2
            && $r['method'] === 'virtual');
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('X-Api-Key', 'CHAVE-DE-TESTE'));
    }

    public function test_link_de_assinatura_fica_criptografado_no_banco(): void
    {
        $enviado = $this->documentoEnviado();
        $assinante = $enviado->assinantes()->first();

        $bruto = \DB::table('assinantes_documento')->where('id', $assinante->id)->value('link_assinatura');

        $this->assertNotEmpty($bruto);
        $this->assertStringNotContainsString('assinafy.teste', $bruto);
    }

    public function test_so_envia_documento_em_rascunho_ou_com_erro(): void
    {
        $enviado = $this->documentoEnviado();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('rascunho');

        $this->servico()->enviar($enviado, $this->secretaria);
    }

    public function test_envio_sem_configuracao_do_assinafy_explica_o_que_falta(): void
    {
        $documento = $this->documento();
        config(['services.assinafy.key' => '', 'services.assinafy.account_id' => '']);

        try {
            $this->servico()->enviar($documento, $this->secretaria);
            $this->fail('sem credenciais não envia');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('ASSINAFY_API_KEY', $e->getMessage());
        }

        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_falha_no_envio_marca_erro_e_a_nova_tentativa_continua_sem_subir_o_pdf_de_novo(): void
    {
        $documento = $this->documento();
        $this->fakeAssinafy(falhaEm: 'assignments');

        try {
            $this->servico()->enviar($documento, $this->secretaria);
            $this->fail('o Assinafy recusou');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('limite do plano atingido', $e->getMessage());
        }

        $documento->refresh();
        $this->assertSame(StatusDocumentoAssinatura::Erro, $documento->status);
        $this->assertSame(self::ASSINAFY_ID, $documento->assinafy_id, 'o documento já estava no Assinafy');
        $this->assertStringContainsString('limite do plano atingido', $documento->assinafy_log['erro']);

        // Nova tentativa: o PDF e os assinantes já cadastrados não se repetem.
        $this->fakeAssinafy();
        $reenviado = $this->servico()->enviar($documento, $this->secretaria);

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $reenviado->status);
        $this->assertArrayNotHasKey('erro', $reenviado->assinafy_log ?? []);
        $this->assertSame(0, $this->pastasDoAssinafy('POST', '/documents'), 'o PDF não sobe de novo');
    }

    public function test_falha_no_upload_deixa_o_documento_com_erro_sem_id_do_assinafy(): void
    {
        $documento = $this->documento();
        $this->fakeAssinafy(falhaEm: 'upload');

        try {
            $this->servico()->enviar($documento, $this->secretaria);
            $this->fail('o upload falhou');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Não foi possível enviar ao Assinafy', $e->getMessage());
        }

        $documento->refresh();
        $this->assertSame(StatusDocumentoAssinatura::Erro, $documento->status);
        $this->assertNull($documento->assinafy_id);
    }

    // --- Acompanhar --------------------------------------------------------------------------------------------------

    public function test_assinatura_de_uma_pessoa_atualiza_so_ela_e_o_documento_continua_aguardando(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('pending_signature', [
            ['email' => 'marina@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z'],
            ['email' => 'clara@escola.test', 'completed' => false],
        ]);

        $atualizado = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $atualizado->status);
        $this->assertSame(['total' => 2, 'assinaram' => 1], $atualizado->andamentoDasAssinaturas());
        $this->assertSame(StatusAssinante::Assinou, $atualizado->assinantes()->where('email', 'marina@teste.com')->first()->status);
        $this->assertNull($atualizado->arquivo_assinado_path);
    }

    public function test_assinado_por_todos_guarda_o_certificado_avisa_o_criador_e_o_cancelamento_reage(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);

        $assinado = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $assinado->status);
        $this->assertTrue($assinado->concluido_em->equalTo(Carbon::parse('2026-10-22 11:30:00', 'America/Sao_Paulo')->setTimezone(config('app.timezone'))), 'a conclusão é a data da última assinatura');
        $this->assertSame("documentos_assinatura/{$assinado->id}/assinado.pdf", $assinado->arquivo_assinado_path);
        $this->assertSame(self::CERTIFICADO, Storage::disk('local')->get($assinado->arquivo_assinado_path));
        $this->assertSame(['total' => 2, 'assinaram' => 2], $assinado->andamentoDasAssinaturas());

        // O cancelamento passa a ter a cópia assinada e a conclusão deixa de esperar o anexo.
        $this->assertSame($assinado->arquivo_assinado_path, $this->cancelamento->refresh()->distrato_assinado_path);
        $this->assertTrue($this->cancelamento->temDistratoAssinado());

        $aviso = $this->secretaria->notifications()->latest()->first();
        $this->assertNotNull($aviso);
        $this->assertSame('Documento assinado por todos', $aviso->data['title']);
    }

    public function test_a_reacao_ao_documento_assinado_acontece_uma_vez_so(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);

        $this->servico()->atualizar($enviado);
        $this->servico()->atualizar($enviado);
        $this->servico()->atualizar($enviado);

        $this->assertSame(1, Activity::query()->where('description', 'Distrato assinado eletronicamente.')->count());
        $this->assertSame(1, $this->secretaria->notifications()->count(), 'um aviso só');
    }

    public function test_certificado_atrasado_e_baixado_pela_rotina_e_so_entao_o_cancelamento_reage(): void
    {
        $enviado = $this->documentoEnviado();

        // O Assinafy já marca como pronto, mas o certificado ainda não está disponível.
        $this->fakeAssinafy('certificating', $this->todosAssinaram(), null);
        $assinado = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $assinado->status);
        $this->assertNull($assinado->arquivo_assinado_path);
        $this->assertFalse($this->cancelamento->refresh()->temDistratoAssinado(), 'sem o certificado, nada reage');

        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $resumo = $this->servico()->reconciliar();

        $this->assertSame(1, $resumo['certificados']);
        $this->assertNotNull($assinado->refresh()->arquivo_assinado_path);
        $this->assertTrue($this->cancelamento->refresh()->temDistratoAssinado());
    }

    public function test_recusa_de_um_assinante_encerra_como_recusado_e_avisa_o_criador(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('rejected_by_signer', [
            ['email' => 'marina@teste.com', 'status' => 'refused'],
            ['email' => 'clara@escola.test', 'completed' => false],
        ]);

        $recusado = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Recusado, $recusado->status);
        $this->assertSame(StatusAssinante::Recusou, $recusado->assinantes()->where('email', 'marina@teste.com')->first()->status);
        $this->assertSame('Assinatura recusada', $this->secretaria->notifications()->latest()->first()->data['title']);
        $this->assertFalse($this->cancelamento->refresh()->temDistratoAssinado());
    }

    public function test_atualizar_exige_documento_enviado_e_assinafy_configurado(): void
    {
        $rascunho = $this->documento();

        try {
            $this->servico()->atualizar($rascunho);
            $this->fail('ainda não foi enviado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('ainda não foi enviado', $e->getMessage());
        }

        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($rascunho, $this->secretaria);
        config(['services.assinafy.key' => '']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Configuração do Assinafy pendente');
        $this->servico()->atualizar($enviado);
    }

    public function test_erro_ao_consultar_o_assinafy_vira_mensagem_e_nao_muda_o_documento(): void
    {
        $enviado = $this->documentoEnviado();
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['message' => 'indisponível'], 503)]);

        try {
            $this->servico()->atualizar($enviado);
            $this->fail('o Assinafy estava fora do ar');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Erro ao consultar o Assinafy', $e->getMessage());
        }

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $enviado->refresh()->status);
    }

    public function test_documento_completo_sem_o_detalhe_dos_assinantes_marca_todos_como_assinou(): void
    {
        $enviado = $this->documentoEnviado();
        // A resposta da API diz que o documento está pronto, mas não traz a lista de assinantes.
        $this->fakeAssinafy('certificated', [], self::CERTIFICADO);

        $assinado = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $assinado->status);
        $this->assertSame(['total' => 2, 'assinaram' => 2], $assinado->andamentoDasAssinaturas());
        $this->assertNotNull($assinado->assinantes()->first()->assinado_em);
    }

    private function fakeSemConexao(): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
    }

    public function test_sem_conexao_no_envio_o_documento_fica_com_erro_e_a_nova_tentativa_funciona(): void
    {
        $documento = $this->documento();
        $this->fakeSemConexao();

        try {
            $this->servico()->enviar($documento, $this->secretaria);
            $this->fail('o Assinafy estava inacessível');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sem conexão com o Assinafy', $e->getMessage());
        }

        $this->assertSame(StatusDocumentoAssinatura::Erro, $documento->refresh()->status);
        $this->assertNull($documento->assinafy_id);

        $this->fakeAssinafy();

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $this->servico()->enviar($documento, $this->secretaria)->status);
    }

    public function test_sem_conexao_na_consulta_vira_mensagem_e_a_rotina_segue_sem_cair(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeSemConexao();

        try {
            $this->servico()->atualizar($enviado);
            $this->fail('o Assinafy estava inacessível');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Erro ao consultar o Assinafy', $e->getMessage());
        }

        $resumo = $this->servico()->reconciliar();

        $this->assertSame(1, $resumo['falhas']);
        $this->assertSame(StatusDocumentoAssinatura::Enviado, $enviado->refresh()->status);
    }

    public function test_sem_conexao_no_download_do_certificado_o_documento_fica_para_a_proxima_rodada(): void
    {
        $enviado = $this->documentoEnviado();

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake(function (Request $requisicao) {
            if (str_ends_with((string) parse_url($requisicao->url(), PHP_URL_PATH), '/download/certificated')) {
                throw new ConnectionException('timeout');
            }

            return Http::response(['data' => ['id' => self::ASSINAFY_ID, 'status' => 'certificated', 'assignment' => ['signers' => $this->todosAssinaram()]]], 200);
        });

        $assinado = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $assinado->status);
        $this->assertNull($assinado->arquivo_assinado_path);
        $this->assertFalse($this->cancelamento->refresh()->temDistratoAssinado(), 'sem o certificado, nada reage');

        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $this->assertSame(1, $this->servico()->reconciliar()['certificados']);
        $this->assertTrue($this->cancelamento->refresh()->temDistratoAssinado());
    }

    // --- Webhook -----------------------------------------------------------------------------------------------------

    public function test_webhook_de_assinatura_consulta_a_api_e_atualiza_o_documento_avulso(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);

        $this->postJson('/api/webhooks/assinafy', [
            'event' => 'document_ready',
            'object' => ['id' => self::ASSINAFY_ID],
        ])->assertOk();

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $enviado->refresh()->status);
        $this->assertNotNull($enviado->arquivo_assinado_path);
        $this->assertSame('document_ready', $enviado->assinafy_log['webhook_last']['event']);
    }

    public function test_webhook_nao_confia_no_envelope_e_so_muda_a_situacao_depois_de_consultar_a_api(): void
    {
        $enviado = $this->documentoEnviado();
        // A API ainda diz que está aguardando: um webhook "pronto" falso não pode marcar como assinado.
        $this->fakeAssinafy('pending_signature', [['email' => 'marina@teste.com', 'completed' => false]]);

        $this->postJson('/api/webhooks/assinafy', ['event' => 'document_ready', 'object' => ['id' => self::ASSINAFY_ID]])->assertOk();

        $this->assertSame(StatusDocumentoAssinatura::Enviado, $enviado->refresh()->status);
    }

    public function test_evento_informativo_do_webhook_so_entra_no_historico(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy();

        $this->postJson('/api/webhooks/assinafy', ['event' => 'signer_viewed_document', 'object' => ['id' => self::ASSINAFY_ID]])->assertOk();

        Http::assertNothingSent();
        $this->assertSame('signer_viewed_document', $enviado->refresh()->assinafy_log['webhook_last']['event']);
        $this->assertSame(StatusDocumentoAssinatura::Enviado, $enviado->status);
    }

    public function test_webhook_de_documento_desconhecido_responde_200_sem_efeito(): void
    {
        $this->documentoEnviado();
        $this->fakeAssinafy();

        $this->postJson('/api/webhooks/assinafy', ['event' => 'document_ready', 'object' => ['id' => 'NAO-E-NOSSO']])
            ->assertOk()
            ->assertJson(['message' => 'Webhook recebido']);

        Http::assertNothingSent();
    }

    public function test_webhook_do_contrato_continua_no_fluxo_antigo(): void
    {
        $contrato = Contrato::create([
            'matricula_id' => Matricula::factory()->create()->id,
            'valor_total' => 0,
            'assinafy_id' => 'DOC-DO-CONTRATO',
            'assinafy_status' => 'enviado',
            'data_aceite' => '2026-01-10 10:00:00',
        ]);

        // No fluxo do contrato o sistema confirma na API antes de marcar como assinado.
        $this->fakeAssinafy('ready');

        $this->postJson('/api/webhooks/assinafy', ['event' => 'document_ready', 'object' => ['id' => 'DOC-DO-CONTRATO']])
            ->assertOk()
            ->assertJson(['message' => 'Webhook processado com sucesso']);

        $this->assertSame('ready', $contrato->refresh()->assinafy_status);
    }

    // --- Rotina periódica --------------------------------------------------------------------------------------------

    public function test_rotina_expira_o_que_passou_do_prazo_e_tira_o_documento_do_assinafy(): void
    {
        $enviado = $this->documentoEnviado();
        $enviado->update(['expira_em' => '2026-10-21']);
        $this->fakeAssinafy();

        $resumo = $this->servico()->reconciliar();

        $this->assertSame(1, $resumo['expirados']);
        $this->assertSame(0, $resumo['consultados']);
        $this->assertSame(StatusDocumentoAssinatura::Expirado, $enviado->refresh()->status);
        $this->assertSame(1, $this->pastasDoAssinafy('DELETE', '/'.self::ASSINAFY_ID));
        $this->assertSame('Documento expirou sem todas as assinaturas', $this->secretaria->notifications()->latest()->first()->data['title']);
    }

    public function test_prazo_que_vence_hoje_ainda_vale(): void
    {
        $enviado = $this->documentoEnviado();
        $enviado->update(['expira_em' => '2026-10-22']);
        $this->fakeAssinafy('pending_signature', [['email' => 'marina@teste.com', 'completed' => false]]);

        $resumo = $this->servico()->reconciliar();

        $this->assertSame(0, $resumo['expirados']);
        $this->assertSame(1, $resumo['consultados']);
        $this->assertSame(StatusDocumentoAssinatura::Enviado, $enviado->refresh()->status);
    }

    public function test_rotina_conta_a_falha_de_consulta_sem_parar_nos_outros_documentos(): void
    {
        $this->documentoEnviado();
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('erro', 500)]);

        $resumo = $this->servico()->reconciliar();

        $this->assertSame(1, $resumo['falhas']);
    }

    public function test_comando_da_rotina_mostra_o_resumo(): void
    {
        $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);

        $this->artisan('documentos:reconciliar-assinaturas')
            ->expectsOutputToContain('1 documento(s) consultado(s), 1 certificado(s) baixado(s), 0 expirado(s), 0 falha(s).')
            ->assertSuccessful();
    }

    // --- Cancelar ----------------------------------------------------------------------------------------------------

    public function test_cancelar_tira_o_documento_do_assinafy(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy();

        $cancelado = $this->servico()->cancelar($enviado, $this->secretaria);

        $this->assertSame(StatusDocumentoAssinatura::Cancelado, $cancelado->status);
        $this->assertTrue($cancelado->assinafy_log['removido_do_assinafy']);
        $this->assertSame(1, $this->pastasDoAssinafy('DELETE', '/'.self::ASSINAFY_ID));
    }

    public function test_cancelar_rascunho_nao_chama_o_assinafy(): void
    {
        $documento = $this->documento();

        $this->assertSame(StatusDocumentoAssinatura::Cancelado, $this->servico()->cancelar($documento, $this->secretaria)->status);
        Http::assertNothingSent();
    }

    public function test_documento_assinado_ou_encerrado_nao_pode_ser_cancelado(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $assinado = $this->servico()->atualizar($enviado);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('em andamento');

        $this->servico()->cancelar($assinado, $this->secretaria);
    }

    public function test_assinatura_depois_do_cancelamento_nao_reabre_o_documento_mas_deixa_o_alerta(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy();
        $this->servico()->cancelar($enviado, $this->secretaria);

        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $depois = $this->servico()->atualizar($enviado);

        $this->assertSame(StatusDocumentoAssinatura::Cancelado, $depois->status);
        $this->assertTrue($depois->assinafy_log['assinado_apos_cancelamento']);
        $this->assertNull($depois->arquivo_assinado_path);
        $this->assertFalse($this->cancelamento->refresh()->temDistratoAssinado());
    }

    // --- Registros ligados ------------------------------------------------------------------------------------------

    public function test_documento_assinado_conclui_a_providencia_do_rh_com_o_certificado_como_comprovante(): void
    {
        $funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id,
            'cargo' => 'Professora',
            'data_admissao' => '2026-11-02',
        ]);
        $processo = app(ProcessoFuncionarioService::class)->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-11-02', $this->como($this->admin));
        $item = $processo->itens()->where('titulo', 'Contrato de trabalho assinado')->firstOrFail();
        $modelo = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, 'Contrato de Trabalho', true, '<p>Contrato de {{FUNCIONARIO_NOME}}.</p>{{BLOCO_ASSINATURAS}}');

        $documento = $this->servico()->criar($modelo, $item, [
            ['nome' => 'Marta Souza', 'email' => 'marta@teste.com', 'papel' => 'Funcionário(a)'],
            ['nome' => 'Diretora Clara', 'email' => 'clara@escola.test', 'papel' => 'Escola'],
        ], $this->admin);

        $this->assertStringContainsString('Marta Souza', $documento->conteudo, 'os dados vêm do funcionário do processo');
        $this->assertTrue($documento->ehDeRh());

        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($documento, $this->admin);
        $this->fakeAssinafy('certificated', [
            ['email' => 'marta@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z'],
            ['email' => 'clara@escola.test', 'completed' => true, 'signed_at' => '2026-10-22T14:00:00Z'],
        ], self::CERTIFICADO);
        $assinado = $this->servico()->atualizar($enviado);

        $item->refresh();
        $this->assertTrue($item->estaResolvido());
        $this->assertSame($assinado->arquivo_assinado_path, $item->arquivo_path);
        $this->assertSame('Assinado eletronicamente.', $item->observacao);
        $this->assertSame($this->admin->id, $item->concluido_por_user_id);
    }

    public function test_processo_ja_encerrado_nao_muda_quando_o_documento_e_assinado(): void
    {
        $funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id,
            'data_admissao' => '2026-11-02',
        ]);
        $servicoRh = app(ProcessoFuncionarioService::class);
        $processo = $servicoRh->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-11-02', $this->como($this->admin));
        $item = $processo->itens()->where('titulo', 'Contrato de trabalho assinado')->firstOrFail();
        $modelo = $this->modelo(TipoTemplateDocumento::TermoConfidencialidade, 'Termo', true, '<p>Termo de {{FUNCIONARIO_NOME}}.</p>{{BLOCO_ASSINATURAS}}');
        $documento = $this->servico()->criar($modelo, $item, [['nome' => 'Marta Souza', 'email' => 'marta@teste.com']], $this->admin);
        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($documento, $this->admin);

        $servicoRh->cancelar($processo, $this->como($this->admin));

        $this->fakeAssinafy('certificated', [['email' => 'marta@teste.com', 'completed' => true, 'signed_at' => '2026-10-22T13:00:00Z']], self::CERTIFICADO);
        $this->servico()->atualizar($enviado);

        $this->assertNull($item->refresh()->concluido_em, 'processo cancelado não é alterado');
    }

    // --- Permissões --------------------------------------------------------------------------------------------------

    public function test_secretaria_cria_envia_e_cancela_mas_a_coordenacao_so_consulta(): void
    {
        $documento = $this->documento();

        $this->como($this->secretaria);
        $this->assertTrue($this->secretaria->can('viewAny', DocumentoAssinatura::class));
        $this->assertTrue($this->secretaria->can('create', DocumentoAssinatura::class));
        $this->assertTrue($this->secretaria->can('view', $documento));
        $this->assertTrue($this->secretaria->can('enviar', $documento));
        $this->assertTrue($this->secretaria->can('cancelar', $documento));
        $this->assertFalse($this->secretaria->can('update', $documento), 'sem edição: o texto enviado é a prova');
        $this->assertFalse($this->secretaria->can('delete', $documento));
        $this->assertFalse($this->secretaria->can('atualizar', $documento), 'sem id do Assinafy, não há o que consultar');

        $this->como($this->coordenador);
        $this->assertTrue($this->coordenador->can('viewAny', DocumentoAssinatura::class));
        $this->assertTrue($this->coordenador->can('view', $documento));
        $this->assertFalse($this->coordenador->can('create', DocumentoAssinatura::class));
        $this->assertFalse($this->coordenador->can('enviar', $documento));
        $this->assertFalse($this->coordenador->can('cancelar', $documento));
    }

    public function test_as_habilidades_seguem_o_estado_do_documento(): void
    {
        $documento = $this->documento();

        $this->como($this->secretaria);
        $this->assertTrue($this->secretaria->can('enviar', $documento));
        $this->assertFalse($this->secretaria->can('verLinks', $documento), 'rascunho não tem link');

        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($documento, $this->secretaria);

        $this->como($this->secretaria);
        $this->assertFalse($this->secretaria->can('enviar', $enviado), 'já enviado');
        $this->assertTrue($this->secretaria->can('cancelar', $enviado));
        $this->assertTrue($this->secretaria->can('atualizar', $enviado));
        $this->assertTrue($this->secretaria->can('verLinks', $enviado));

        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $assinado = $this->servico()->atualizar($enviado);

        $this->como($this->secretaria);
        $this->assertFalse($this->secretaria->can('cancelar', $assinado), 'assinado não se cancela');
        $this->assertFalse($this->secretaria->can('verLinks', $assinado), 'sem link depois de assinado');
        $this->assertTrue($this->secretaria->can('view', $assinado), 'continua consultável');
    }

    public function test_o_estado_do_documento_nas_funcoes_do_modelo(): void
    {
        $documento = $this->documento();

        $this->assertTrue($documento->podeEnviar());
        $this->assertFalse($documento->aguardaAssinaturas());
        $this->assertFalse($documento->podeSerConsultado(), 'sem id no Assinafy');

        $this->fakeAssinafy();
        $enviado = $this->servico()->enviar($documento, $this->secretaria);

        $this->assertFalse($enviado->podeEnviar());
        $this->assertTrue($enviado->aguardaAssinaturas());
        $this->assertTrue($enviado->podeSerConsultado());

        $cancelado = $this->servico()->cancelar($enviado, $this->secretaria);

        $this->assertFalse($cancelado->podeSerConsultado(), 'cancelado não é consultado pela tela');
        $this->assertFalse($cancelado->emAndamento());
    }

    public function test_documento_de_rh_so_para_quem_tambem_ve_admissao_e_desligamento(): void
    {
        $funcionario = Funcionario::factory()->create(['pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id]);
        $modelo = $this->modelo(TipoTemplateDocumento::TermoConfidencialidade, 'Termo', true, '<p>{{FUNCIONARIO_NOME}}</p>');
        $documento = $this->servico()->criar($modelo, $funcionario, [['nome' => 'Marta Souza', 'email' => 'marta@teste.com']], $this->admin);

        $this->como($this->secretaria);
        $this->assertFalse($this->secretaria->can('view', $documento));
        $this->assertFalse($this->secretaria->can('enviar', $documento));
        $this->assertFalse($this->secretaria->can('cancelar', $documento));

        $this->como($this->admin);
        $this->assertTrue($this->admin->can('view', $documento));
        $this->assertTrue($this->admin->can('enviar', $documento));

        $this->assertSame(1, DocumentoAssinatura::query()->deRh()->count());
        $this->assertSame(0, DocumentoAssinatura::query()->semRh()->where('id', $documento->id)->count());
    }

    // --- Acesso aos PDFs --------------------------------------------------------------------------------------------

    public function test_pdf_do_documento_abre_para_a_equipe_para_quem_assina_e_para_a_familia_do_aluno(): void
    {
        $documento = $this->documentoEnviado();
        $caminho = $documento->arquivo_original_path;

        $this->actingAs($this->como($this->secretaria))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();
        $this->actingAs($this->como($this->coordenador))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();

        $assina = $this->usuario('responsavel', ['email' => 'Clara@Escola.test']);
        $this->actingAs($this->como($assina))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();

        $mae = $this->usuario('responsavel');
        $this->mae->users()->save($mae);
        $this->mae->alunos()->attach($this->matricula->pessoa_id);
        $this->actingAs($this->como($mae))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();
    }

    public function test_pdf_do_documento_nao_abre_para_estranhos_nem_para_o_professor(): void
    {
        $documento = $this->documentoEnviado();
        $caminho = $documento->arquivo_original_path;

        $outraFamilia = $this->usuario('responsavel');
        $this->actingAs($this->como($outraFamilia))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();

        $professor = $this->usuario('professor');
        $this->actingAs($this->como($professor))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
    }

    public function test_pdf_de_documento_de_rh_so_abre_para_quem_ve_admissao_e_desligamento(): void
    {
        $funcionario = Funcionario::factory()->create(['pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id]);
        $modelo = $this->modelo(TipoTemplateDocumento::TermoConfidencialidade, 'Termo', true, '<p>{{FUNCIONARIO_NOME}}</p>');
        $documento = $this->servico()->criar($modelo, $funcionario, [['nome' => 'Marta Souza', 'email' => 'marta@teste.com']], $this->admin);
        $caminho = $documento->arquivo_original_path;

        $this->actingAs($this->como($this->secretaria))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
        $this->actingAs($this->como($this->admin))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();

        // Quem vai assinar vê o próprio documento.
        $marta = $this->usuario('professor', ['email' => 'marta@teste.com']);
        $this->actingAs($this->como($marta))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();
    }

    public function test_arquivo_da_pasta_sem_documento_correspondente_nao_abre_para_ninguem(): void
    {
        Storage::disk('local')->put('documentos_assinatura/999/original.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs($this->como($this->admin))->get(route('documentos.visualizar', ['path' => 'documentos_assinatura/999/original.pdf']))->assertForbidden();
    }

    public function test_documento_de_assinatura_nao_e_apagado_pelo_anexo_do_distrato_nem_pela_providencia(): void
    {
        $enviado = $this->documentoEnviado();
        $this->fakeAssinafy('certificated', $this->todosAssinaram(), self::CERTIFICADO);
        $assinado = $this->servico()->atualizar($enviado);

        // O cancelamento aponta para o PDF certificado; anexar outra cópia em papel não pode apagar o certificado.
        $copia = UploadedFile::fake()->createWithContent('distrato.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        app(CancelamentoMatriculaService::class)->anexarDistratoAssinado($this->cancelamento->refresh(), $copia, $this->secretaria);

        Storage::disk('local')->assertExists($assinado->arquivo_assinado_path);
        $this->assertNotSame($assinado->arquivo_assinado_path, $this->cancelamento->refresh()->distrato_assinado_path);
    }

    public function test_itens_do_rh_com_documento_de_assinatura_tem_a_relacao_para_a_tela(): void
    {
        $funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id,
            'data_admissao' => '2026-11-02',
        ]);
        $processo = app(ProcessoFuncionarioService::class)->iniciar($funcionario, TipoProcessoFuncionario::Admissao, '2026-11-02', $this->como($this->admin));
        $item = $processo->itens()->firstOrFail();
        $modelo = $this->modelo(TipoTemplateDocumento::TermoConfidencialidade, 'Termo', true, '<p>{{FUNCIONARIO_NOME}}</p>');
        $documento = $this->servico()->criar($modelo, $item, [['nome' => 'Marta Souza', 'email' => 'marta@teste.com']], $this->admin);

        $this->assertInstanceOf(ItemProcessoFuncionario::class, $documento->documentavel);
        $this->assertSame($documento->id, $item->documentosAssinatura()->first()->id);
    }
}
