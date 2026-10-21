<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Models\Curso;
use App\Models\DocumentoInserido;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TipoDocumento;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ImportacaoDocumentosMatriculaService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Importação de documentos entre matrículas do mesmo aluno (tela "Gerenciar Documentos").
 *
 * Só os papéis/permissões necessários são criados: o RolesSeeder completo deixaria cada teste ~1 minuto mais lento.
 */
class ImportarDocumentosMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

    private const ACAO = 'importarDeOutraMatricula';

    private Pessoa $aluno;

    private Matricula $origem;

    private Matricula $destino;

    private TipoDocumento $rg;

    private TipoDocumento $cpf;

    private TipoDocumento $certidao;

    private TipoDocumento $comprovante;

    private User $secretaria;

    private User $semPermissao;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Permission::firstOrCreate(['name' => 'ViewAny:Matricula', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ImportarDocumentos:Matricula', 'guard_name' => 'web']);

        $this->secretaria = User::factory()->create(['activated_at' => now()]);
        $this->secretaria->givePermissionTo('ViewAny:Matricula', 'ImportarDocumentos:Matricula');

        $this->semPermissao = User::factory()->create(['activated_at' => now()]);
        $this->semPermissao->givePermissionTo('ViewAny:Matricula');

        $this->aluno = Pessoa::factory()->create();
        $this->origem = Matricula::factory()->create(['pessoa_id' => $this->aluno->id]);
        $this->destino = Matricula::factory()->create(['pessoa_id' => $this->aluno->id]);

        $this->rg = TipoDocumento::create(['nome' => 'RG do Aluno']);
        $this->cpf = TipoDocumento::create(['nome' => 'CPF do Aluno']);
        $this->certidao = TipoDocumento::create(['nome' => 'Certidão de Nascimento']);
        $this->comprovante = TipoDocumento::create(['nome' => 'Comprovante de Residência']);
    }

    /**
     * Cria um documento enviado na matrícula, com o arquivo físico (de conteúdo único) no disco.
     *
     * @param  array<string, mixed>  $extra
     */
    private function enviar(Matricula $matricula, TipoDocumento $tipo, array $extra = [], bool $comArquivo = true): DocumentoInserido
    {
        $caminho = "documentos_alunos/original-{$matricula->id}-{$tipo->id}.pdf";

        if ($comArquivo) {
            Storage::disk('local')->put($caminho, self::PDF."\n% {$matricula->id}-{$tipo->nome}");
        }

        return DocumentoInserido::create(array_merge([
            'tipo_documento_id' => $tipo->id,
            'matricula_id' => $matricula->id,
            'status' => SituacaoDocumento::VERIFICADO,
            'arquivo_path' => $caminho,
            'nome_arquivo_original' => "{$tipo->id}.pdf",
            'hash_arquivo' => md5($caminho),
        ], $extra));
    }

    private function servico(): ImportacaoDocumentosMatriculaService
    {
        return app(ImportacaoDocumentosMatriculaService::class);
    }

    private function pagina(User $usuario, ?Matricula $matricula = null)
    {
        return Livewire::actingAs($usuario)
            ->test(DocumentosMatricula::class, ['record' => ($matricula ?? $this->destino)->getKey()]);
    }

    private function documentoNoDestino(TipoDocumento $tipo): ?DocumentoInserido
    {
        return DocumentoInserido::query()
            ->where('matricula_id', $this->destino->id)
            ->where('tipo_documento_id', $tipo->id)
            ->first();
    }

    public function test_importa_o_documento_copiando_o_arquivo_e_entrando_em_analise(): void
    {
        $original = $this->enviar($this->origem, $this->rg, [
            'status' => SituacaoDocumento::VERIFICADO,
            'observacoes' => 'Frente e verso',
            'dados_ia' => ['legivel' => true],
            'analisado_ia_em' => now(),
            'status_sugerido' => 'verificado',
        ]);

        $resultado = $this->servico()->importar($this->origem, $this->destino, [$original->id]);

        $this->assertSame(['RG do Aluno'], $resultado['importados']);
        $this->assertSame([], $resultado['ignorados']);

        $copia = $this->documentoNoDestino($this->rg);
        $this->assertNotNull($copia);
        $this->assertNotSame($original->arquivo_path, $copia->arquivo_path);
        $this->assertStringStartsWith('documentos_alunos/Doc-rg-do-aluno-', $copia->arquivo_path);
        $this->assertStringEndsWith('.pdf', $copia->arquivo_path);
        $this->assertSame(
            Storage::disk('local')->get($original->arquivo_path),
            Storage::disk('local')->get($copia->arquivo_path),
        );
        Storage::disk('local')->assertExists($original->arquivo_path);

        $this->assertSame(SituacaoDocumento::EM_ANALISE, $copia->status);
        $this->assertSame($original->nome_arquivo_original, $copia->nome_arquivo_original);
        $this->assertSame($original->hash_arquivo, $copia->hash_arquivo);
        $this->assertNull($copia->dados_ia);
        $this->assertNull($copia->analisado_ia_em);
        $this->assertNull($copia->status_sugerido);
        $this->assertNull($copia->interessado_id);
        $this->assertStringContainsString('Importado da matrícula', (string) $copia->observacoes);
        $this->assertStringContainsString('Frente e verso', (string) $copia->observacoes);

        // A origem não é alterada.
        $original->refresh();
        $this->assertSame(SituacaoDocumento::VERIFICADO, $original->status);
        $this->assertSame($this->origem->id, $original->matricula_id);
    }

    public function test_importa_varios_documentos_de_uma_vez_apenas_os_selecionados_pela_tela(): void
    {
        $docRg = $this->enviar($this->origem, $this->rg);
        $docCpf = $this->enviar($this->origem, $this->cpf);
        $this->enviar($this->origem, $this->certidao);
        $docComprovante = $this->enviar($this->origem, $this->comprovante, ['status' => SituacaoDocumento::EM_ANALISE]);

        $this->pagina($this->secretaria)
            ->callAction(self::ACAO, [
                'matricula_origem_id' => $this->origem->id,
                'documentos' => [$docRg->id, $docCpf->id, $docComprovante->id],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Documentos importados');

        $this->assertNotNull($this->documentoNoDestino($this->rg));
        $this->assertNotNull($this->documentoNoDestino($this->cpf));
        $this->assertNotNull($this->documentoNoDestino($this->comprovante));
        $this->assertNull($this->documentoNoDestino($this->certidao), 'Documento não marcado não pode ser importado.');
        $this->assertSame(3, DocumentoInserido::where('matricula_id', $this->destino->id)->count());
        $this->assertSame(4, DocumentoInserido::where('matricula_id', $this->origem->id)->count());
    }

    public function test_tipo_que_ja_existe_no_destino_e_ignorado_sem_alterar_nada(): void
    {
        $original = $this->enviar($this->origem, $this->rg);
        $outro = $this->enviar($this->origem, $this->cpf);
        $jaNoDestino = $this->enviar($this->destino, $this->rg, ['status' => SituacaoDocumento::VERIFICADO]);
        $conteudoAntes = Storage::disk('local')->get($jaNoDestino->arquivo_path);

        $resultado = $this->servico()->importar($this->origem, $this->destino, [$original->id, $outro->id]);

        $this->assertSame(['CPF do Aluno'], $resultado['importados']);
        $this->assertCount(1, $resultado['ignorados']);
        $this->assertSame('RG do Aluno', $resultado['ignorados'][0]['tipo']);
        $this->assertStringContainsString('Já existe', $resultado['ignorados'][0]['motivo']);

        $this->assertSame(1, DocumentoInserido::where('matricula_id', $this->destino->id)->where('tipo_documento_id', $this->rg->id)->count());
        $jaNoDestino->refresh();
        $this->assertSame(SituacaoDocumento::VERIFICADO, $jaNoDestino->status);
        $this->assertSame($conteudoAntes, Storage::disk('local')->get($jaNoDestino->arquivo_path));
    }

    public function test_tela_nao_aceita_selecionar_documento_de_tipo_que_o_destino_ja_tem(): void
    {
        $original = $this->enviar($this->origem, $this->rg);
        $this->enviar($this->destino, $this->rg);

        $this->pagina($this->secretaria)
            ->callAction(self::ACAO, [
                'matricula_origem_id' => $this->origem->id,
                'documentos' => [$original->id],
            ])
            ->assertHasActionErrors(['documentos.0']);

        $this->assertSame(1, DocumentoInserido::where('matricula_id', $this->destino->id)->count());
    }

    public function test_recusa_matricula_de_origem_de_outra_pessoa(): void
    {
        $outraMatricula = Matricula::factory()->create();
        $documento = $this->enviar($outraMatricula, $this->rg);

        try {
            $this->servico()->importar($outraMatricula, $this->destino, [$documento->id]);
            $this->fail('Deveria recusar a origem de outra pessoa.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('mesmo aluno', $e->getMessage());
        }

        $this->pagina($this->secretaria)
            ->callAction(self::ACAO, [
                'matricula_origem_id' => $outraMatricula->id,
                'documentos' => [$documento->id],
            ])
            ->assertHasActionErrors(['matricula_origem_id']);

        $this->assertSame(0, DocumentoInserido::where('matricula_id', $this->destino->id)->count());
    }

    public function test_recusa_origem_igual_ao_destino(): void
    {
        $documento = $this->enviar($this->destino, $this->rg);

        $this->expectException(DomainException::class);

        $this->servico()->importar($this->destino, $this->destino, [$documento->id]);
    }

    public function test_ignora_ids_de_documentos_que_nao_sao_da_matricula_de_origem(): void
    {
        $doutraMatricula = $this->enviar(Matricula::factory()->create(), $this->cpf);

        $resultado = $this->servico()->importar($this->origem, $this->destino, [$doutraMatricula->id]);

        $this->assertSame([], $resultado['importados']);
        $this->assertSame(0, DocumentoInserido::where('matricula_id', $this->destino->id)->count());
    }

    public function test_documento_sem_arquivo_fisico_nao_e_importavel_e_documento_rejeitado_e(): void
    {
        $semArquivo = $this->enviar($this->origem, $this->rg, [], comArquivo: false);
        $rejeitado = $this->enviar($this->origem, $this->cpf, ['status' => SituacaoDocumento::REJEITADO]);

        $lista = $this->servico()->documentosImportaveis($this->origem, $this->destino);

        $this->assertNotNull($lista[$semArquivo->id]['motivo_bloqueio']);
        $this->assertNull($lista[$rejeitado->id]['motivo_bloqueio'], 'Rejeitado na origem continua selecionável.');

        $this->pagina($this->secretaria)
            ->callAction(self::ACAO, [
                'matricula_origem_id' => $this->origem->id,
                'documentos' => [$semArquivo->id],
            ])
            ->assertHasActionErrors(['documentos.0']);

        $this->pagina($this->secretaria)
            ->callAction(self::ACAO, [
                'matricula_origem_id' => $this->origem->id,
                'documentos' => [$rejeitado->id],
            ])
            ->assertHasNoActionErrors();

        $copia = $this->documentoNoDestino($this->cpf);
        $this->assertNotNull($copia);
        $this->assertSame(SituacaoDocumento::EM_ANALISE, $copia->status);
        $this->assertNull($this->documentoNoDestino($this->rg));
    }

    public function test_excluir_o_documento_importado_nao_apaga_o_arquivo_da_origem(): void
    {
        $original = $this->enviar($this->origem, $this->rg);

        $this->servico()->importar($this->origem, $this->destino, [$original->id]);
        $copia = $this->documentoNoDestino($this->rg);
        $caminhoDaCopia = $copia->arquivo_path;

        $this->pagina($this->secretaria)->callTableAction('excluir', $this->rg);

        $this->assertNull($this->documentoNoDestino($this->rg));
        Storage::disk('local')->assertMissing($caminhoDaCopia);
        Storage::disk('local')->assertExists($original->arquivo_path);
        $this->assertNotNull(DocumentoInserido::find($original->id));
    }

    public function test_tipo_fora_dos_exigidos_pelo_destino_e_vinculado_para_aparecer_na_tela(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Médio', 'nome_interno' => 'EM']);
        $exclusivo = TipoDocumento::create(['nome' => 'Histórico do Ensino Médio']);
        $exclusivo->cursos()->attach($curso->id);

        $docExclusivo = $this->enviar($this->origem, $exclusivo);
        $docGlobal = $this->enviar($this->origem, $this->rg);

        $this->servico()->importar($this->origem, $this->destino, [$docExclusivo->id, $docGlobal->id]);

        $this->assertTrue($this->destino->tiposDocumentos()->whereKey($exclusivo->id)->exists());
        $this->assertFalse(
            $this->destino->tiposDocumentos()->whereKey($this->rg->id)->exists(),
            'Tipo que a matrícula já lista não deve ser vinculado de novo.',
        );

        $this->pagina($this->secretaria)->assertCanSeeTableRecords([$exclusivo, $this->rg]);
    }

    public function test_falha_no_meio_do_lote_nao_deixa_registros_nem_arquivos_orfaos(): void
    {
        $docRg = $this->enviar($this->origem, $this->rg);
        $docCpf = $this->enviar($this->origem, $this->cpf);
        $arquivosAntes = Storage::disk('local')->allFiles('documentos_alunos');

        $criados = 0;
        DocumentoInserido::creating(function () use (&$criados): void {
            if (++$criados === 2) {
                throw new RuntimeException('falha simulada');
            }
        });

        try {
            $this->servico()->importar($this->origem, $this->destino, [$docRg->id, $docCpf->id]);
            $this->fail('A falha simulada deveria interromper a importação.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada', $e->getMessage());
        }

        $this->assertSame(0, DocumentoInserido::where('matricula_id', $this->destino->id)->count());
        $this->assertEqualsCanonicalizing($arquivosAntes, Storage::disk('local')->allFiles('documentos_alunos'));
    }

    public function test_acao_so_aparece_para_quem_tem_a_permissao(): void
    {
        $this->pagina($this->semPermissao)->assertActionHidden(self::ACAO);
        $this->pagina($this->secretaria)->assertActionVisible(self::ACAO);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin = User::factory()->create(['activated_at' => now()]);
        $superAdmin->assignRole('super_admin');
        $superAdmin->givePermissionTo('ViewAny:Matricula');

        $this->pagina($superAdmin)->assertActionVisible(self::ACAO);
    }

    public function test_acao_fica_oculta_quando_a_pessoa_so_tem_uma_matricula(): void
    {
        $unica = Matricula::factory()->create();

        $this->pagina($this->secretaria, $unica)->assertActionHidden(self::ACAO);
    }

    public function test_registra_a_importacao_na_trilha_de_auditoria_da_matricula_de_destino(): void
    {
        $docRg = $this->enviar($this->origem, $this->rg);
        $docCpf = $this->enviar($this->origem, $this->cpf);

        $this->pagina($this->secretaria)->callAction(self::ACAO, [
            'matricula_origem_id' => $this->origem->id,
            'documentos' => [$docRg->id, $docCpf->id],
        ]);

        $log = Activity::query()
            ->where('subject_type', $this->destino->getMorphClass())
            ->where('subject_id', $this->destino->id)
            ->where('description', 'Importou documentos de outra matrícula')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->secretaria->id, $log->causer_id);
        $this->assertSame($this->origem->id, $log->properties['origem_matricula_id']);
        $this->assertEqualsCanonicalizing(['RG do Aluno', 'CPF do Aluno'], $log->properties['tipos_documento']);
    }
}
