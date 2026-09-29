<?php

namespace Tests\Feature;

use App\Enums\StatusComunicacaoEmMassa;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Mail\MensagemGenericaMail;
use App\Models\AlunoResponsavel;
use App\Models\ComunicacaoEmMassa;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Services\ComunicacaoEmMassaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ComunicacaoEmMassaTest extends TestCase
{
    use RefreshDatabase;

    private function interessado(array $attrs = [], array $pessoaAttrs = []): Interessado
    {
        $status = StatusInteressado::factory()->create();
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $pessoa = Pessoa::factory()->create(array_merge(['email' => 'lead'.uniqid().'@example.com'], $pessoaAttrs));

        return Interessado::factory()->create(array_merge([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ], $attrs));
    }

    // ─── Segmentação: interessados ──────────────────────────────────

    public function test_segmenta_interessados_por_status(): void
    {
        $alvo = $this->interessado();
        $this->interessado(); // outro status, não deve entrar

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'status_interessado_ids' => [$alvo->status_interessado_id],
        ])->create();

        $destinatarios = app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao);

        $this->assertCount(1, $destinatarios);
        $this->assertSame($alvo->pessoa_id, $destinatarios->first()->id);
    }

    public function test_lista_explicita_de_interessados_ignora_outros_filtros(): void
    {
        $alvo = $this->interessado();
        $this->interessado();

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'interessado_ids' => [$alvo->id],
            'status_interessado_ids' => [999999],
        ])->create();

        $destinatarios = app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao);

        $this->assertCount(1, $destinatarios);
        $this->assertSame($alvo->pessoa_id, $destinatarios->first()->id);
    }

    public function test_sem_nenhum_filtro_nem_selecao_nao_retorna_todos(): void
    {
        $this->interessado();
        $this->interessado();

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([])->create();

        $this->assertCount(0, app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao));
    }

    public function test_exclui_quem_nao_aceita_comunicacao_e_quem_nao_tem_email(): void
    {
        $status = StatusInteressado::factory()->create();
        $optOut = $this->interessado(['status_interessado_id' => $status->id], ['aceita_comunicacao' => false]);
        $semEmail = $this->interessado(['status_interessado_id' => $status->id], ['email' => null]);
        $valido = $this->interessado(['status_interessado_id' => $status->id]);

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'status_interessado_ids' => [$status->id],
        ])->create();

        $destinatarios = app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao);

        $this->assertCount(1, $destinatarios);
        $this->assertSame($valido->pessoa_id, $destinatarios->first()->id);
    }

    // ─── Segmentação: responsáveis por turma ────────────────────────

    private function alunoMatriculado(Turma $turma, string $situacao = 'ativa'): Pessoa
    {
        $aluno = Pessoa::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => $situacao,
        ]);

        $responsavel = Pessoa::factory()->create(['email' => 'resp'.uniqid().'@example.com']);
        AlunoResponsavel::create([
            'aluno_id' => $aluno->id,
            'responsavel_id' => $responsavel->id,
            'tipo_vinculo_id' => TipoVinculo::create(['nome' => 'Pai '.uniqid()])->id,
        ]);

        return $responsavel;
    }

    public function test_segmenta_responsaveis_de_turma_com_matricula_ativa(): void
    {
        $turma = Turma::factory()->create();
        $outraTurma = Turma::factory()->create();

        $responsavelAtivo = $this->alunoMatriculado($turma, 'ativa');
        $this->alunoMatriculado($turma, 'trancada');
        $this->alunoMatriculado($outraTurma, 'ativa');

        $comunicacao = ComunicacaoEmMassa::factory()->paraResponsaveisDaTurma([$turma->id])->create();

        $destinatarios = app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao);

        $this->assertCount(1, $destinatarios);
        $this->assertSame($responsavelAtivo->id, $destinatarios->first()->id);
    }

    public function test_responsaveis_sem_turma_selecionada_retorna_vazio(): void
    {
        $comunicacao = ComunicacaoEmMassa::factory()->paraResponsaveisDaTurma([])->create();

        $this->assertCount(0, app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao));
    }

    // ─── Job de envio ────────────────────────────────────────────────

    public function test_job_envia_para_todos_e_atualiza_contadores_e_status(): void
    {
        Mail::fake();

        $status = StatusInteressado::factory()->create();
        $this->interessado(['status_interessado_id' => $status->id]);
        $this->interessado(['status_interessado_id' => $status->id]);

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'status_interessado_ids' => [$status->id],
        ])->create(['assunto' => 'Assunto [Nome]', 'corpo' => '<p>Olá [Nome]</p>']);

        (new EnviarComunicacaoEmMassaJob($comunicacao))->handle(app(ComunicacaoEmMassaService::class));

        $comunicacao->refresh();

        $this->assertSame(StatusComunicacaoEmMassa::Concluida, $comunicacao->status);
        $this->assertSame(2, $comunicacao->total_destinatarios);
        $this->assertSame(2, $comunicacao->total_enviados);
        $this->assertSame(0, $comunicacao->total_falhas);
        $this->assertNotNull($comunicacao->enviado_em);
        Mail::assertQueued(MensagemGenericaMail::class, 2);
    }

    public function test_job_personaliza_nome_no_assunto_e_no_corpo(): void
    {
        Mail::fake();

        $pessoa = Pessoa::factory()->create(['nome' => 'Ana Souza', 'email' => 'ana@example.com']);
        $status = StatusInteressado::factory()->create();
        Interessado::factory()->create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
        ]);

        $comunicacao = ComunicacaoEmMassa::factory()->paraInteressados([
            'status_interessado_ids' => [$status->id],
        ])->create(['assunto' => 'Olá, [Nome]!', 'corpo' => '<p>Bem-vindo(a), [Nome].</p>']);

        (new EnviarComunicacaoEmMassaJob($comunicacao))->handle(app(ComunicacaoEmMassaService::class));

        Mail::assertQueued(MensagemGenericaMail::class, fn (MensagemGenericaMail $mail) => $mail->assunto === 'Olá, Ana!'
            && str_contains($mail->corpoHtml, 'Bem-vindo(a), Ana.'));
    }

    public function test_job_nao_reenvia_comunicacao_ja_concluida(): void
    {
        Mail::fake();

        $comunicacao = ComunicacaoEmMassa::factory()->create(['status' => StatusComunicacaoEmMassa::Concluida]);

        (new EnviarComunicacaoEmMassaJob($comunicacao))->handle(app(ComunicacaoEmMassaService::class));

        Mail::assertNothingQueued();
        $this->assertSame(StatusComunicacaoEmMassa::Concluida, $comunicacao->fresh()->status);
    }

    public function test_pode_ser_enviada_apenas_em_rascunho_ou_falhou(): void
    {
        $rascunho = ComunicacaoEmMassa::factory()->create(['status' => StatusComunicacaoEmMassa::Rascunho]);
        $falhou = ComunicacaoEmMassa::factory()->create(['status' => StatusComunicacaoEmMassa::Falhou]);
        $enviando = ComunicacaoEmMassa::factory()->create(['status' => StatusComunicacaoEmMassa::Enviando]);
        $concluida = ComunicacaoEmMassa::factory()->create(['status' => StatusComunicacaoEmMassa::Concluida]);

        $this->assertTrue($rascunho->podeSerEnviada());
        $this->assertTrue($falhou->podeSerEnviada());
        $this->assertFalse($enviando->podeSerEnviada());
        $this->assertFalse($concluida->podeSerEnviada());
    }
}
