<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\DocumentoInserido;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use App\Models\Turma;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regras de segurança do Portal de Pré-Admissão (link público por token, sem login):
 * validade do link, isolamento entre famílias e proteção de documentos já verificados.
 */
class PortalDocumentosCandidatoSegurancaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function criarCandidato(string $nome = 'Carlos Silva'): Interessado
    {
        $status = StatusInteressado::firstOrCreate(['nome' => 'Novo'], ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $pessoa = Pessoa::create(['nome' => $nome, 'email' => strtolower(str_replace(' ', '.', $nome)).'@example.com']);

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);
    }

    private function criarTipoDocumento(): TipoDocumento
    {
        return TipoDocumento::create(['nome' => 'Certidão de Nascimento', 'flag_obrigatorio' => true, 'status' => 'ativo']);
    }

    private function criarDocumento(Interessado $lead, TipoDocumento $tipo, array $atributos = []): DocumentoInserido
    {
        $caminho = 'documentos_candidatos/'.$lead->id.'/original.pdf';
        Storage::disk('local')->put($caminho, 'conteudo-original');

        return DocumentoInserido::create($atributos + [
            'interessado_id' => $lead->id,
            'tipo_documento_id' => $tipo->id,
            'arquivo_path' => $caminho,
            'nome_arquivo_original' => 'original.pdf',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);
    }

    private function enviar(string $token, array $dados)
    {
        return $this->post(route('candidato.documentos.upload', ['token' => $token]), $dados + [
            'arquivo' => UploadedFile::fake()->create('novo.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_link_expirado_ou_inexistente_responde_410_em_todas_as_acoes(): void
    {
        $lead = $this->criarCandidato();
        $tipo = $this->criarTipoDocumento();
        $doc = $this->criarDocumento($lead, $tipo);
        $token = $lead->obterOuCriarTokenDocumentos();
        $lead->update(['token_documentos_expira_em' => now()->subMinute()]);

        $this->get(route('candidato.documentos.show', ['token' => $token]))
            ->assertStatus(410)
            ->assertSee('Este link não é mais válido');

        $this->enviar($token, ['tipo_documento_id' => $tipo->id])->assertStatus(410);

        $this->delete(route('candidato.documentos.remover', ['token' => $token, 'documento' => $doc->id]))->assertStatus(410);
        $this->assertDatabaseHas('documento_inserido', ['id' => $doc->id]);

        $this->get(route('candidato.documentos.show', ['token' => 'token-que-nunca-existiu']))->assertStatus(410);
    }

    public function test_gerar_o_link_define_e_renova_a_validade(): void
    {
        $lead = $this->criarCandidato();

        $token = $lead->obterOuCriarTokenDocumentos();
        $lead->refresh();

        $this->assertSame(7, Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS);
        $this->assertEqualsWithDelta(
            now()->addDays(Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS)->timestamp,
            $lead->token_documentos_expira_em->timestamp,
            10
        );

        // Link quase vencendo: a equipe copiar o link de novo renova a janela, mantendo o mesmo token.
        $lead->update(['token_documentos_expira_em' => now()->addDay()]);
        $this->assertSame($token, $lead->fresh()->obterOuCriarTokenDocumentos());
        $this->assertGreaterThan(now()->addDays(6), $lead->fresh()->token_documentos_expira_em);
    }

    public function test_link_vencido_nao_e_revivido_a_equipe_recebe_um_novo(): void
    {
        $lead = $this->criarCandidato();
        $antigo = $lead->obterOuCriarTokenDocumentos();
        $lead->update(['token_documentos_expira_em' => now()->subDay()]);

        $this->get(route('candidato.documentos.show', ['token' => $antigo]))->assertStatus(410);

        $novo = $lead->fresh()->obterOuCriarTokenDocumentos();

        $this->assertNotSame($antigo, $novo);
        // A URL antiga continua morta mesmo depois de a equipe gerar o link outra vez.
        $this->get(route('candidato.documentos.show', ['token' => $antigo]))->assertStatus(410);
        $this->get(route('candidato.documentos.show', ['token' => $novo]))->assertOk();
    }

    public function test_portal_pede_que_buscadores_e_referer_ignorem_a_pagina(): void
    {
        $lead = $this->criarCandidato();
        $this->criarTipoDocumento();

        $this->get($lead->urlPortalDocumentos())
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSee('name="referrer" content="no-referrer"', false);
    }

    public function test_nao_aceita_dependente_de_outra_familia(): void
    {
        $lead = $this->criarCandidato('Família A');
        $outraFamilia = $this->criarCandidato('Família B');
        $dependenteAlheio = InteressadoDependente::create(['interessado_id' => $outraFamilia->id, 'nome_crianca' => 'Filho da B']);
        $tipo = $this->criarTipoDocumento();

        $this->enviar($lead->obterOuCriarTokenDocumentos(), [
            'tipo_documento_id' => $tipo->id,
            'interessado_dependente_id' => $dependenteAlheio->id,
        ])->assertSessionHasErrors('interessado_dependente_id');

        $this->assertSame(0, DocumentoInserido::count());
        Queue::assertNothingPushed();
    }

    public function test_aceita_dependente_do_proprio_candidato(): void
    {
        $lead = $this->criarCandidato();
        $dependente = InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);
        $tipo = $this->criarTipoDocumento();

        $this->enviar($lead->obterOuCriarTokenDocumentos(), [
            'tipo_documento_id' => $tipo->id,
            'interessado_dependente_id' => $dependente->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $this->assertDatabaseHas('documento_inserido', [
            'interessado_id' => $lead->id,
            'interessado_dependente_id' => $dependente->id,
            'tipo_documento_id' => $tipo->id,
        ]);
        Queue::assertPushed(ValidarDocumentoComIaJob::class);
    }

    public function test_documento_verificado_nao_pode_ser_substituido_pela_familia(): void
    {
        $lead = $this->criarCandidato();
        $tipo = $this->criarTipoDocumento();
        $doc = $this->criarDocumento($lead, $tipo, ['status' => SituacaoDocumento::VERIFICADO]);
        $arquivoOriginal = $doc->arquivo_path;

        $this->enviar($lead->obterOuCriarTokenDocumentos(), ['tipo_documento_id' => $tipo->id])
            ->assertSessionHas('erro')
            ->assertSessionMissing('sucesso');

        $doc->refresh();
        $this->assertSame(SituacaoDocumento::VERIFICADO, $doc->status);
        $this->assertSame($arquivoOriginal, $doc->arquivo_path);
        $this->assertTrue(Storage::disk('local')->exists($arquivoOriginal));
        $this->assertSame(1, DocumentoInserido::count());
        Queue::assertNothingPushed();
    }

    public function test_substituir_documento_apaga_o_arquivo_antigo_e_zera_o_parecer_da_ia(): void
    {
        $lead = $this->criarCandidato();
        $tipo = $this->criarTipoDocumento();
        $doc = $this->criarDocumento($lead, $tipo, [
            'status' => SituacaoDocumento::REJEITADO,
            'observacoes' => 'Foto cortada',
            'dados_ia' => ['score_confianca' => 40],
            'analisado_ia_em' => now(),
        ]);
        $arquivoAntigo = $doc->arquivo_path;

        $this->enviar($lead->obterOuCriarTokenDocumentos(), ['tipo_documento_id' => $tipo->id])
            ->assertSessionHas('sucesso');

        $doc->refresh();

        $this->assertSame(1, DocumentoInserido::count());
        $this->assertSame(SituacaoDocumento::EM_ANALISE, $doc->status);
        $this->assertNull($doc->observacoes);
        $this->assertNull($doc->dados_ia);
        $this->assertNull($doc->analisado_ia_em);
        $this->assertNotSame($arquivoAntigo, $doc->arquivo_path);
        $this->assertFalse(Storage::disk('local')->exists($arquivoAntigo));
        $this->assertTrue(Storage::disk('local')->exists($doc->arquivo_path));
        Queue::assertPushed(ValidarDocumentoComIaJob::class, fn (ValidarDocumentoComIaJob $job): bool => $job->documentoId === $doc->id);
    }

    public function test_documento_ja_migrado_para_matricula_nao_e_sobrescrito_nem_removido_pelo_portal(): void
    {
        $lead = $this->criarCandidato();
        $tipo = $this->criarTipoDocumento();

        $matricula = Matricula::create([
            'pessoa_id' => Pessoa::create(['nome' => 'Aluno Matriculado'])->id,
            'turma_id' => Turma::create(['nome' => '1º Ano A', 'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao()])->id,
            'situacao' => SituacaoMatricula::ATIVA->value,
        ]);
        $migrado = $this->criarDocumento($lead, $tipo, ['matricula_id' => $matricula->id]);
        $arquivoMigrado = $migrado->arquivo_path;
        $token = $lead->obterOuCriarTokenDocumentos();

        $this->enviar($token, ['tipo_documento_id' => $tipo->id])->assertSessionHas('sucesso');

        $migrado->refresh();
        $this->assertSame($arquivoMigrado, $migrado->arquivo_path);
        $this->assertTrue(Storage::disk('local')->exists($arquivoMigrado));
        $this->assertSame(2, DocumentoInserido::count());

        $this->delete(route('candidato.documentos.remover', ['token' => $token, 'documento' => $migrado->id]))->assertNotFound();
        $this->assertDatabaseHas('documento_inserido', ['id' => $migrado->id]);
    }

    public function test_limite_de_envios_por_candidato_protege_a_cota_da_ia(): void
    {
        $lead = $this->criarCandidato();
        $tipo = $this->criarTipoDocumento();
        $token = $lead->obterOuCriarTokenDocumentos();

        $chave = 'portal-documentos-upload:'.$lead->id;
        RateLimiter::clear($chave);

        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit($chave, 3600);
        }

        $this->enviar($token, ['tipo_documento_id' => $tipo->id])->assertSessionHas('erro');

        $this->assertSame(0, DocumentoInserido::count());
        Queue::assertNothingPushed();
    }
}
