<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NivelAssinatura;
use App\Enums\StatusDocumentoAssinatura;
use App\Enums\StatusSolicitacaoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Resources\DocumentosAssinatura\Pages\ViewDocumentoAssinatura;
use App\Filament\Resources\HistoricoEscolars\Pages\ListHistoricoEscolars;
use App\Filament\Resources\SolicitacaoDocumentos\Pages\ListSolicitacaoDocumentos;
use App\Models\Curso;
use App\Models\DocumentoAssinatura;
use App\Models\HistoricoEscolar;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\Documentos\AssinaturaPdfInspector;
use App\Services\Documentos\DocumentosOficiaisService;
use App\Services\DocumentoService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Declarações e histórico escolar com assinatura digital (Onda 74): a leitura do PDF assinado, os dois caminhos (Assinafy e
 * PDF assinado fora do sistema), a cópia assinada no documento oficial, o acesso, a página pública e as telas.
 *
 * "Hoje" é quinta-feira, 22/10/2026. O Assinafy é sempre simulado e nenhum certificado é validado: os PDFs dos testes só
 * trazem as marcas que o inspetor procura.
 */
class AssinaturaOficialTest extends TestCase
{
    use RefreshDatabase;

    private const ASSINAFY_ID = 'DOC-OFICIAL-1';

    private const ORIGINAL = "%PDF-1.4\n1 0 obj<<>>endobj\n%declaracao emitida pela escola\n%%EOF\n";

    private const CODIGO = 'TR36-AAAA-BBBB-CCCC';

    private User $secretaria;

    private User $coordenador;

    private Matricula $matricula;

    private Pessoa $aluno;

    private Unidade $unidade;

    private SolicitacaoDocumento $declaracao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
        Storage::fake('local');

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

        $permissoes = [
            'secretaria' => ['ViewAny:DocumentoAssinatura', 'View:DocumentoAssinatura', 'Create:DocumentoAssinatura', 'Enviar:DocumentoAssinatura', 'Cancelar:DocumentoAssinatura', 'AnexarAssinado:DocumentoAssinatura',
                'ViewAny:SolicitacaoDocumento', 'View:SolicitacaoDocumento', 'Update:SolicitacaoDocumento', 'Delete:SolicitacaoDocumento',
                'ViewAny:HistoricoEscolar', 'View:HistoricoEscolar', 'Update:HistoricoEscolar', 'Delete:HistoricoEscolar'],
            // A coordenação consulta, mas quem afirma a autenticidade do documento é a secretaria.
            'coordenador' => ['ViewAny:DocumentoAssinatura', 'View:DocumentoAssinatura', 'ViewAny:SolicitacaoDocumento', 'View:SolicitacaoDocumento', 'ViewAny:HistoricoEscolar', 'View:HistoricoEscolar'],
        ];

        foreach ($permissoes as $papel => $nomes) {
            foreach ($nomes as $nome) {
                Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
            }
        }

        $this->secretaria = $this->usuario('secretaria');
        $this->coordenador = $this->usuario('coordenador');

        $this->unidade = Unidade::create(['nome' => 'Unidade Sede', 'cnpj' => '12.345.678/0001-90']);
        $this->unidade->representantesLegais()->attach(Pessoa::create(['nome' => 'Diretora Clara', 'email' => 'clara@escola.test'])->id, ['cargo' => 'Diretora']);
        $curso = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->aluno = Pessoa::factory()->create(['nome' => 'Ana Lima', 'data_nascimento' => '2014-03-10']);
        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => $this->aluno->id,
            'turma_id' => Turma::factory()->create(['serie_id' => $serie->id])->id,
            'situacao' => 'ativa',
        ]);

        $this->declaracao = $this->declaracaoEmitida();
    }

    private function usuario(string $papel, array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos + ['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): DocumentosOficiaisService
    {
        return app(DocumentosOficiaisService::class);
    }

    private function declaracaoEmitida(string $protocolo = 'DOC-2026-000001', string $codigo = self::CODIGO): SolicitacaoDocumento
    {
        $modelo = TemplateDocumento::query()->first() ?? TemplateDocumento::create([
            'nome' => 'Declaração de Matrícula',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => '<p>{{ALUNO_NOME}} está matriculado(a).</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        $caminho = "documentos_emitidos/{$protocolo}.pdf";
        Storage::disk('local')->put($caminho, self::ORIGINAL);

        return SolicitacaoDocumento::create([
            'protocolo' => $protocolo,
            'codigo_verificacao' => $codigo,
            'matricula_id' => $this->matricula->id,
            'template_documento_id' => $modelo->id,
            'solicitado_por_user_id' => $this->secretaria->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
            'data_emissao' => now(),
            'data_validade' => now()->addDays(30),
            'arquivo_path' => $caminho,
        ]);
    }

    private function historico(): HistoricoEscolar
    {
        return HistoricoEscolar::create([
            'pessoa_id' => $this->aluno->id,
            'unidade_id' => $this->unidade->id,
            'codigo_autenticidade' => HistoricoEscolar::gerarCodigoAutenticidade(),
            'situacao' => 'concluido',
            'data_emissao' => '2026-10-22',
        ]);
    }

    /**
     * Um PDF com o rastro que uma assinatura digital deixa (atualização incremental com `/ByteRange` e `/Contents`). Não é
     * uma assinatura válida: o inspetor só procura as marcas.
     */
    private function pdfAssinado(string $base = self::ORIGINAL, bool $icp = true, int $assinaturas = 1): string
    {
        $certificado = bin2hex($icp ? 'CN=AC Raiz Brasileira, O=ICP-Brasil' : 'CN=Certificado Autoassinado, O=Teste');
        $pdf = $base;

        for ($i = 0; $i < $assinaturas; $i++) {
            $pdf .= "\n".(10 + $i)." 0 obj<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /ByteRange [0 100 200 300] /Contents <{$certificado}0000> >>endobj\n%%EOF\n";
        }

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $assinantesDaApi
     */
    private function fakeAssinafy(string $statusDoDocumento = 'pending_signature', array $assinantesDaApi = [], ?string $certificado = null): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();

        Http::fake(function (Request $requisicao) use ($statusDoDocumento, $assinantesDaApi, $certificado) {
            $metodo = $requisicao->method();
            $caminho = (string) parse_url($requisicao->url(), PHP_URL_PATH);

            return match (true) {
                $metodo === 'POST' && str_ends_with($caminho, '/documents') => Http::response(['id' => self::ASSINAFY_ID], 200),
                $metodo === 'GET' && str_ends_with($caminho, '/signers') => Http::response(['data' => []], 200),
                $metodo === 'POST' && str_ends_with($caminho, '/signers') => Http::response(['id' => 'SIGNER-'.substr(md5((string) $requisicao['email']), 0, 8)], 200),
                $metodo === 'POST' && str_ends_with($caminho, '/assignments') => Http::response(['signing_urls' => array_map(
                    fn (array $assinante): array => ['signer_id' => $assinante['id'], 'url' => 'https://assinafy.teste/sign/'.$assinante['id'].'?token=T'.$assinante['id']],
                    $requisicao['signers'],
                )], 200),
                $metodo === 'GET' && str_ends_with($caminho, '/download/certificated') => $certificado === null ? Http::response('', 404) : Http::response($certificado, 200),
                $metodo === 'GET' && (bool) preg_match('#/documents/[^/]+$#', $caminho) => Http::response(['data' => ['id' => self::ASSINAFY_ID, 'status' => $statusDoDocumento, 'assignment' => ['signers' => $assinantesDaApi]]], 200),
                $metodo === 'DELETE' => Http::response(null, 204),
                default => Http::response(['message' => 'rota não prevista no teste'], 500),
            };
        });
    }

    // --- Leitura do PDF ----------------------------------------------------------------------------------------------

    public function test_inspetor_nao_acha_assinatura_em_pdf_comum_nem_em_arquivo_que_nao_e_pdf(): void
    {
        $inspetor = new AssinaturaPdfInspector;

        $this->assertSame(0, $inspetor->inspecionar(self::ORIGINAL)['assinaturas']);
        $this->assertNull($inspetor->nivelDe(self::ORIGINAL));
        $this->assertNull($inspetor->nivelDe('<html>não sou um PDF /ByteRange [0 1 2 3]</html>'));
    }

    public function test_inspetor_reconhece_assinatura_digital_e_a_marca_da_icp_brasil(): void
    {
        $inspetor = new AssinaturaPdfInspector;

        $icp = $inspetor->inspecionar($this->pdfAssinado());
        $this->assertSame(1, $icp['assinaturas']);
        $this->assertTrue($icp['icp_brasil']);
        $this->assertSame(NivelAssinatura::QualificadaIcp, $icp['nivel']);
        $this->assertSame(['ETSI.CAdES.detached'], $icp['formatos']);

        $outro = $inspetor->inspecionar($this->pdfAssinado(icp: false, assinaturas: 2));
        $this->assertSame(2, $outro['assinaturas']);
        $this->assertFalse($outro['icp_brasil']);
        $this->assertSame(NivelAssinatura::Digital, $outro['nivel']);
    }

    public function test_inspetor_diz_se_o_assinado_comeca_pelo_pdf_emitido(): void
    {
        $inspetor = new AssinaturaPdfInspector;

        $this->assertTrue($inspetor->contemOriginal($this->pdfAssinado(), self::ORIGINAL));
        $this->assertFalse($inspetor->contemOriginal($this->pdfAssinado("%PDF-1.4\n%outro documento\n%%EOF\n"), self::ORIGINAL));
        $this->assertFalse($inspetor->contemOriginal('qualquer coisa', ''));
    }

    // --- PDF assinado fora do sistema --------------------------------------------------------------------------------

    public function test_anexar_pdf_assinado_guarda_a_copia_no_documento_e_registra_o_que_foi_lido(): void
    {
        $documento = $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(), $this->secretaria);

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $documento->status);
        $this->assertSame(NivelAssinatura::QualificadaIcp, $documento->nivel_assinatura);
        $this->assertTrue($documento->foiAssinadoFora());
        $this->assertTrue($documento->ehDocumentoOficial());
        $this->assertNull($documento->template_documento_id);
        $this->assertSame(1, $documento->assinatura_detalhes['assinaturas']);
        $this->assertTrue($documento->assinatura_detalhes['icp_brasil']);
        $this->assertTrue($documento->assinatura_detalhes['contem_original']);
        $this->assertFalse($documento->assinatura_detalhes['validado'], 'o sistema não valida o certificado');
        $this->assertSame(hash('sha256', $this->pdfAssinado()), $documento->assinatura_detalhes['hash_sha256']);
        Storage::disk('local')->assertExists("documentos_assinatura/{$documento->id}/assinado.pdf");
        Storage::disk('local')->assertExists("documentos_assinatura/{$documento->id}/original.pdf");

        $declaracao = $this->declaracao->refresh();
        $this->assertTrue($declaracao->temCopiaAssinada());
        $this->assertSame($documento->arquivo_assinado_path, $declaracao->arquivo_assinado_path);
        $this->assertSame(NivelAssinatura::QualificadaIcp, $declaracao->assinatura_nivel);
        $this->assertNotNull($declaracao->assinado_em);
    }

    public function test_pdf_sem_assinatura_digital_e_recusado(): void
    {
        try {
            $this->servico()->anexarAssinado($this->declaracao, self::ORIGINAL, $this->secretaria);
            $this->fail('um PDF sem assinatura não pode virar cópia assinada');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não traz assinatura digital', $e->getMessage());
        }

        $this->assertFalse($this->declaracao->refresh()->temCopiaAssinada());
        $this->assertSame(0, DocumentoAssinatura::count());
    }

    public function test_arquivo_que_nao_e_pdf_e_recusado_mesmo_com_marcas_de_assinatura(): void
    {
        $this->expectException(\DomainException::class);

        $this->servico()->anexarAssinado($this->declaracao, '<html>/ByteRange [0 1 2 3] /Contents <ABCD></html>', $this->secretaria);
    }

    public function test_pdf_assinado_que_nao_comeca_pelo_emitido_e_aceito_com_o_aviso_registrado(): void
    {
        $outro = $this->pdfAssinado("%PDF-1.4\n%arquivo regravado pelo assinador\n%%EOF\n");

        $documento = $this->servico()->anexarAssinado($this->declaracao, $outro, $this->secretaria);

        $this->assertFalse($documento->assinatura_detalhes['contem_original']);
    }

    public function test_certificado_que_nao_e_icp_brasil_vale_como_assinatura_digital_e_nao_como_qualificada(): void
    {
        $documento = $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(icp: false), $this->secretaria);

        $this->assertSame(NivelAssinatura::Digital, $documento->nivel_assinatura);
        $this->assertSame(NivelAssinatura::Digital, $this->declaracao->refresh()->assinatura_nivel);
    }

    public function test_documento_com_copia_assinada_nao_aceita_outra_nem_nova_emissao_do_pdf(): void
    {
        $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(), $this->secretaria);
        $declaracao = $this->declaracao->refresh();

        $this->assertNotNull($this->servico()->impedimento($declaracao));

        try {
            $this->servico()->anexarAssinado($declaracao, $this->pdfAssinado(), $this->secretaria);
            $this->fail('o documento já está assinado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já tem uma cópia assinada', $e->getMessage());
        }

        $caminho = $declaracao->arquivo_path;
        $bytes = Storage::disk('local')->get($caminho);

        try {
            app(DocumentoService::class)->gerarPdf($declaracao);
            $this->fail('refazer o PDF deixaria a cópia assinada sem par');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não pode ser emitida de novo', $e->getMessage());
        }

        $this->assertSame($bytes, Storage::disk('local')->get($caminho), 'o PDF emitido continua intacto');
    }

    public function test_declaracao_sem_pdf_emitido_ou_nao_disponivel_nao_pode_ser_assinada(): void
    {
        $semArquivo = $this->declaracaoEmitida('DOC-2026-000002', 'TR36-DDDD-EEEE-FFFF');
        Storage::disk('local')->delete($semArquivo->arquivo_path);
        $this->assertStringContainsString('não foi encontrado', (string) $this->servico()->impedimento($semArquivo));

        $solicitada = $this->declaracaoEmitida('DOC-2026-000003', 'TR36-GGGG-HHHH-IIII');
        $solicitada->update(['status' => StatusSolicitacaoDocumento::Solicitado]);
        $this->assertStringContainsString('disponível', (string) $this->servico()->impedimento($solicitada));

        $this->expectException(\DomainException::class);
        $this->servico()->anexarAssinado($semArquivo, $this->pdfAssinado(), $this->secretaria);
    }

    public function test_pdf_assinado_acima_do_limite_e_recusado(): void
    {
        $grande = $this->pdfAssinado().str_repeat('0', DocumentosOficiaisService::TAMANHO_MAXIMO_BYTES);

        try {
            $this->servico()->anexarAssinado($this->declaracao, $grande, $this->secretaria);
            $this->fail('o arquivo passa do limite');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('até 15 MB', $e->getMessage());
        }
    }

    // --- Caminho do Assinafy -----------------------------------------------------------------------------------------

    public function test_pdf_emitido_vira_documento_para_assinatura_com_a_direcao_sugerida(): void
    {
        $sugestao = $this->servico()->signatariosSugeridos($this->declaracao);

        $this->assertSame([['nome' => 'Diretora Clara', 'email' => 'clara@escola.test', 'papel' => 'Diretora']], $sugestao['assinantes']);

        $documento = $this->servico()->enviarParaAssinatura($this->declaracao, $sugestao['assinantes'], $this->secretaria);

        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->status);
        $this->assertTrue($documento->ehDocumentoOficial());
        $this->assertSame(SolicitacaoDocumento::class, $documento->documentavel_type);
        $this->assertSame(self::ORIGINAL, Storage::disk('local')->get($documento->arquivo_original_path), 'vai para o Assinafy o mesmo PDF que a família baixa');
        $this->assertSame(hash('sha256', self::ORIGINAL), $documento->arquivo_original_hash);
        $this->assertSame('Diretora Clara', $documento->assinantes()->first()->nome);
        $this->assertStringContainsString($this->declaracao->protocolo, $documento->titulo);
        $this->assertFalse($this->declaracao->refresh()->temCopiaAssinada(), 'só depois da assinatura');
    }

    public function test_so_um_documento_em_andamento_por_declaracao(): void
    {
        $assinantes = $this->servico()->signatariosSugeridos($this->declaracao)['assinantes'];
        $this->servico()->enviarParaAssinatura($this->declaracao, $assinantes, $this->secretaria);

        try {
            $this->servico()->enviarParaAssinatura($this->declaracao, $assinantes, $this->secretaria);
            $this->fail('já há um documento em andamento');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Já existe um documento em andamento', $e->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(), $this->secretaria);
    }

    public function test_certificado_do_assinafy_com_assinatura_embutida_vira_a_copia_assinada(): void
    {
        $assinantes = $this->servico()->signatariosSugeridos($this->declaracao)['assinantes'];
        $documento = $this->servico()->enviarParaAssinatura($this->declaracao, $assinantes, $this->secretaria);

        $this->fakeAssinafy();
        app(AssinaturaDocumentoService::class)->enviar($documento, $this->secretaria);

        $this->fakeAssinafy('certificated', [['email' => 'clara@escola.test', 'status' => 'signed', 'signed_at' => '2026-10-22T10:00:00Z']], $this->pdfAssinado());
        $documento = app(AssinaturaDocumentoService::class)->atualizar($documento->refresh());

        $this->assertSame(StatusDocumentoAssinatura::Assinado, $documento->status);
        $this->assertSame(NivelAssinatura::QualificadaIcp, $documento->nivel_assinatura);
        $this->assertSame('assinafy', $documento->assinatura_detalhes['origem']);
        $this->assertFalse($documento->foiAssinadoFora());

        $declaracao = $this->declaracao->refresh();
        $this->assertSame($documento->arquivo_assinado_path, $declaracao->arquivo_assinado_path);
        $this->assertSame(NivelAssinatura::QualificadaIcp, $declaracao->assinatura_nivel);
        $this->assertNotNull($declaracao->assinado_em);
    }

    public function test_certificado_do_assinafy_sem_assinatura_embutida_fica_como_assinatura_eletronica(): void
    {
        $assinantes = $this->servico()->signatariosSugeridos($this->declaracao)['assinantes'];
        $documento = $this->servico()->enviarParaAssinatura($this->declaracao, $assinantes, $this->secretaria);

        $this->fakeAssinafy();
        app(AssinaturaDocumentoService::class)->enviar($documento, $this->secretaria);

        $this->fakeAssinafy('certificated', [['email' => 'clara@escola.test', 'status' => 'signed']], "%PDF-1.4\n%certificado de assinaturas do Assinafy\n%%EOF");
        $documento = app(AssinaturaDocumentoService::class)->atualizar($documento->refresh());

        $this->assertSame(NivelAssinatura::Eletronica, $documento->nivel_assinatura);
        $this->assertSame(0, $documento->assinatura_detalhes['assinaturas']);
        $this->assertSame(NivelAssinatura::Eletronica, $this->declaracao->refresh()->assinatura_nivel);
    }

    // --- Histórico escolar -------------------------------------------------------------------------------------------

    public function test_historico_escolar_segue_o_mesmo_caminho_do_pdf_assinado_fora(): void
    {
        $historico = $this->historico();
        $pdfEmitido = $this->servico()->pdfEmitido($historico);
        $this->assertStringStartsWith('%PDF', $pdfEmitido);

        $documento = $this->servico()->anexarAssinado($historico, $this->pdfAssinado($pdfEmitido), $this->secretaria);

        $this->assertSame(HistoricoEscolar::class, $documento->documentavel_type);
        $this->assertSame('Histórico escolar', $documento->tipoDoRegistro());
        $this->assertArrayNotHasKey('contem_original', $documento->assinatura_detalhes, 'o histórico é gerado na hora: não há PDF guardado para comparar');

        $historico->refresh();
        $this->assertTrue($historico->temCopiaAssinada());
        $this->assertSame(NivelAssinatura::QualificadaIcp, $historico->assinatura_nivel);
    }

    public function test_historico_vai_para_a_assinatura_da_direcao_da_unidade(): void
    {
        $historico = $this->historico();

        $sugestao = $this->servico()->signatariosSugeridos($historico);
        $this->assertSame('clara@escola.test', $sugestao['assinantes'][0]['email']);

        $documento = $this->servico()->enviarParaAssinatura($historico, $sugestao['assinantes'], $this->secretaria);

        $this->assertStringContainsString('Histórico escolar', $documento->titulo);
        $this->assertStringContainsString($historico->codigo_autenticidade, $documento->titulo);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($documento->arquivo_original_path));
    }

    // --- Acesso e página pública -------------------------------------------------------------------------------------

    public function test_familia_do_aluno_baixa_a_copia_assinada_e_estranho_nao(): void
    {
        $documento = $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(), $this->secretaria);

        $familia = $this->usuario('responsavel');
        $familia->pessoas()->attach($this->aluno->id);
        $estranho = $this->usuario('responsavel');
        $estranho->pessoas()->attach(Pessoa::factory()->create()->id);

        $this->como($familia);
        $this->actingAs($familia)->get(route('documentos.visualizar', ['path' => $documento->arquivo_assinado_path]))->assertOk();

        $this->como($estranho);
        $this->actingAs($estranho)->get(route('documentos.visualizar', ['path' => $documento->arquivo_assinado_path]))->assertForbidden();

        $this->como($this->secretaria);
        $this->actingAs($this->secretaria)->get(route('documentos.visualizar', ['path' => $documento->arquivo_assinado_path]))->assertOk();
    }

    public function test_familia_do_aluno_baixa_o_historico_assinado_e_estranho_nao(): void
    {
        $historico = $this->historico();
        $documento = $this->servico()->anexarAssinado($historico, $this->pdfAssinado($this->servico()->pdfEmitido($historico)), $this->secretaria);

        $familia = $this->usuario('aluno');
        $familia->pessoas()->attach($this->aluno->id);
        $estranho = $this->usuario('responsavel');
        $estranho->pessoas()->attach(Pessoa::factory()->create()->id);

        $this->como($familia);
        $this->actingAs($familia)->get(route('documentos.visualizar', ['path' => $documento->arquivo_assinado_path]))->assertOk();

        $this->como($estranho);
        $this->actingAs($estranho)->get(route('documentos.visualizar', ['path' => $documento->arquivo_assinado_path]))->assertForbidden();
    }

    public function test_pagina_publica_mostra_o_nivel_da_assinatura_sem_afirmar_validacao(): void
    {
        $this->get(route('documentos.validar-autenticidade', self::CODIGO))
            ->assertOk()
            ->assertDontSee('Cópia com assinatura digital');

        $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(), $this->secretaria);

        $this->get(route('documentos.validar-autenticidade', self::CODIGO))
            ->assertOk()
            ->assertSee('Cópia com assinatura digital')
            ->assertSee('Assinatura qualificada (certificado ICP-Brasil)')
            ->assertSee('ela não valida o certificado')
            ->assertSee('validar.iti.gov.br');
    }

    public function test_pagina_publica_do_historico_mostra_a_copia_assinada(): void
    {
        $historico = $this->historico();
        $this->servico()->anexarAssinado($historico, $this->pdfAssinado($this->servico()->pdfEmitido($historico)), $this->secretaria);

        $this->get(route('documentos.validar-autenticidade', $historico->codigo_autenticidade))
            ->assertOk()
            ->assertSee('Cópia com assinatura digital');
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_acoes_de_assinatura_aparecem_conforme_a_permissao_e_o_estado_da_declaracao(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListSolicitacaoDocumentos::class)
            ->assertTableActionVisible('assinar_pelo_assinafy', $this->declaracao)
            ->assertTableActionVisible('anexar_assinado', $this->declaracao)
            ->assertTableActionHidden('baixar_assinada', $this->declaracao);

        // A coordenação consulta, mas não afirma a autenticidade nem monta o documento para assinatura.
        Livewire::actingAs($this->como($this->coordenador))
            ->test(ListSolicitacaoDocumentos::class)
            ->assertTableActionHidden('anexar_assinado', $this->declaracao)
            ->assertTableActionHidden('assinar_pelo_assinafy', $this->declaracao);
    }

    public function test_anexar_pelo_botao_guarda_a_copia_e_esconde_o_que_nao_cabe_mais(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('declaracao-assinada.pdf', $this->pdfAssinado());

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListSolicitacaoDocumentos::class)
            ->callAction(TestAction::make('anexar_assinado')->table($this->declaracao), ['arquivo' => $arquivo, 'conferi' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Cópia assinada guardada')
            ->assertTableActionVisible('baixar_assinada', $this->declaracao->refresh())
            ->assertTableActionHidden('anexar_assinado', $this->declaracao)
            ->assertTableActionHidden('assinar_pelo_assinafy', $this->declaracao)
            ->assertTableActionHidden('gerar_pdf', $this->declaracao);

        $this->assertTrue($this->declaracao->refresh()->temCopiaAssinada());
    }

    public function test_anexar_pelo_botao_exige_a_confirmacao_de_conferencia(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('declaracao-assinada.pdf', $this->pdfAssinado());

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListSolicitacaoDocumentos::class)
            ->callAction(TestAction::make('anexar_assinado')->table($this->declaracao), ['arquivo' => $arquivo, 'conferi' => false])
            ->assertHasActionErrors(['conferi']);

        $this->assertFalse($this->declaracao->refresh()->temCopiaAssinada());
    }

    public function test_anexar_pelo_botao_recusa_pdf_sem_assinatura_com_aviso(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('declaracao.pdf', self::ORIGINAL);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListSolicitacaoDocumentos::class)
            ->callAction(TestAction::make('anexar_assinado')->table($this->declaracao), ['arquivo' => $arquivo, 'conferi' => true])
            ->assertNotified('PDF não aceito');

        $this->assertFalse($this->declaracao->refresh()->temCopiaAssinada());
    }

    public function test_botao_de_enviar_para_assinatura_leva_ao_documento_em_rascunho(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListSolicitacaoDocumentos::class)
            ->callAction(TestAction::make('assinar_pelo_assinafy')->table($this->declaracao), ['assinantes' => [['nome' => 'Diretora Clara', 'email' => 'clara@escola.test', 'papel' => 'Diretora']]])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $documento = DocumentoAssinatura::query()->firstOrFail();
        $this->assertSame(StatusDocumentoAssinatura::Rascunho, $documento->status);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListSolicitacaoDocumentos::class)
            ->assertTableActionHidden('assinar_pelo_assinafy', $this->declaracao)
            ->assertTableActionHidden('anexar_assinado', $this->declaracao)
            ->assertTableActionVisible('ver_assinatura', $this->declaracao);
    }

    public function test_historico_tem_as_mesmas_acoes_e_trava_a_sincronizacao_depois_de_assinado(): void
    {
        $historico = $this->historico();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListHistoricoEscolars::class)
            ->assertTableActionVisible('anexar_assinado', $historico)
            ->assertTableActionVisible('assinar_pelo_assinafy', $historico)
            ->assertTableActionVisible('sincronizar_dados', $historico);

        $this->servico()->anexarAssinado($historico, $this->pdfAssinado($this->servico()->pdfEmitido($historico)), $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListHistoricoEscolars::class)
            ->assertTableActionVisible('baixar_assinada', $historico->refresh())
            ->assertTableActionHidden('anexar_assinado', $historico)
            ->assertTableActionHidden('sincronizar_dados', $historico);
    }

    public function test_pagina_do_documento_mostra_o_nivel_e_o_aviso_de_que_o_certificado_nao_foi_validado(): void
    {
        $documento = $this->servico()->anexarAssinado($this->declaracao, $this->pdfAssinado(), $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewDocumentoAssinatura::class, ['record' => $documento->getKey()])
            ->assertSee('Assinatura digital')
            ->assertSee('Assinatura qualificada (certificado ICP-Brasil)')
            ->assertSee('Assinado fora do sistema e anexado')
            ->assertSee('não valida a cadeia de certificação')
            ->assertSee('validar.iti.gov.br');
    }

    public function test_permissao_de_anexar_e_da_secretaria_e_da_administracao(): void
    {
        $this->assertTrue($this->como($this->secretaria)->can('AnexarAssinado:DocumentoAssinatura'));
        $this->assertFalse($this->como($this->coordenador)->can('AnexarAssinado:DocumentoAssinatura'));
    }
}
