<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusEncaminhamento;
use App\Enums\StatusParecerDescritivo;
use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Exceptions\ReuniaoInvalidaException;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Coordenador;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\OcorrenciaEscolar;
use App\Models\ParecerDescritivo;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Reuniao;
use App\Models\ReuniaoAluno;
use App\Models\Serie;
use App\Models\TipoOcorrencia;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ConselhoClasseService;
use App\Services\ReuniaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Conselho de classe (Onda 27): a pauta aluno por aluno com a fotografia dos indicadores, a deliberação com encaminhamento e o
 * fechamento da ata só com todos deliberados. A deliberação nunca altera nota, frequência nem a matrícula. "Hoje" é 28/10/2026.
 */
class ConselhoClasseTest extends TestCase
{
    use RefreshDatabase;

    private User $organizador;

    private User $conselheiro;

    private Pessoa $pessoaConselheira;

    private Turma $turma;

    private EtapaAvaliativa $etapa;

    private Matricula $paula;

    private Matricula $rui;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-28 09:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31', 'nota_aprovacao' => 6]);
        $this->organizador = User::factory()->create(['name' => 'Coordenadora Célia', 'activated_at' => now()->subDay()]);
        $this->conselheiro = User::factory()->create(['name' => 'Professor Conselheiro', 'activated_at' => now()->subDay()]);
        $this->pessoaConselheira = Pessoa::create(['nome' => 'Professor Conselheiro']);
        $this->conselheiro->pessoas()->attach($this->pessoaConselheira->id);

        $this->turma = Turma::create(['nome' => '6º ano A', 'periodo_letivo_id' => $periodo->id, 'professor_conselheiro_id' => $this->pessoaConselheira->id]);
        $this->etapa = EtapaAvaliativa::create(['turma_id' => $this->turma->id, 'nome' => '3º bimestre', 'periodo_letivo_id' => $periodo->id, 'data_inicio' => '2026-08-01', 'data_fim' => '2026-10-15']);

        $matematica = Disciplina::create(['nome' => 'Matemática', 'ordem_boletim' => 1]);
        $historia = Disciplina::create(['nome' => 'História', 'ordem_boletim' => 2]);
        $categoria = CategoriaAvaliacao::first() ?? CategoriaAvaliacao::create(['nome' => 'Prova']);

        $this->paula = Matricula::factory()->create(['pessoa_id' => Pessoa::create(['nome' => 'Paula Souza'])->id, 'turma_id' => $this->turma->id]);
        $this->rui = Matricula::factory()->create(['pessoa_id' => Pessoa::create(['nome' => 'Rui Andrade'])->id, 'turma_id' => $this->turma->id]);
        // Fora da pauta: matrícula cancelada.
        Matricula::factory()->create(['pessoa_id' => Pessoa::create(['nome' => 'Zélia Cancelada'])->id, 'turma_id' => $this->turma->id, 'situacao' => 'cancelada']);

        foreach ([[$matematica, 4.0, 9.0], [$historia, 8.0, 9.0]] as [$disciplina, $notaPaula, $notaRui]) {
            $avaliacao = Avaliacao::create([
                'turma_id' => $this->turma->id,
                'disciplina_id' => $disciplina->id,
                'etapa_avaliativa_id' => $this->etapa->id,
                'categoria_avaliacao_id' => $categoria->id,
                'data_prevista' => '2026-09-10',
                'data_ocorrencia' => '2026-09-10',
                'data_limite_lancamento' => '2026-09-25',
                'nota_maxima' => 10,
            ]);
            Nota::create(['matricula_id' => $this->paula->id, 'avaliacao_id' => $avaliacao->id, 'valor' => $notaPaula]);
            Nota::create(['matricula_id' => $this->rui->id, 'avaliacao_id' => $avaliacao->id, 'valor' => $notaRui]);

            $professor = Pessoa::firstOrCreate(['nome' => 'Docente de '.$disciplina->nome]);
            $aulas = collect(['2026-09-01', '2026-09-02'])->map(fn (string $dia): CronogramaAula => CronogramaAula::create([
                'turma_id' => $this->turma->id, 'disciplina_id' => $disciplina->id, 'pessoa_id' => $professor->id, 'data' => $dia,
            ]));
            // Paula falta a metade das aulas; Rui nunca falta.
            FrequenciaEscolar::create(['matricula_id' => $this->paula->id, 'cronograma_aula_id' => $aulas[0]->id, 'situacao' => 'presente']);
            FrequenciaEscolar::create(['matricula_id' => $this->paula->id, 'cronograma_aula_id' => $aulas[1]->id, 'situacao' => 'ausente']);
            FrequenciaEscolar::create(['matricula_id' => $this->rui->id, 'cronograma_aula_id' => $aulas[0]->id, 'situacao' => 'presente']);
            FrequenciaEscolar::create(['matricula_id' => $this->rui->id, 'cronograma_aula_id' => $aulas[1]->id, 'situacao' => 'presente']);
        }

        $tipo = TipoOcorrencia::create(['nome' => 'Conversa em aula', 'categoria' => 'disciplinar', 'gravidade' => 'leve']);
        OcorrenciaEscolar::create(['matricula_id' => $this->paula->id, 'tipo_ocorrencia_id' => $tipo->id, 'data_hora' => '2026-10-01 10:00:00', 'descricao' => 'Conversa durante a prova', 'notificar_responsaveis' => false]);
        ParecerDescritivo::create(['matricula_id' => $this->rui->id, 'etapa_avaliativa_id' => $this->etapa->id, 'texto' => 'Parecer.', 'status' => StatusParecerDescritivo::Publicado, 'publicado_em' => now()]);
    }

    private function reunioes(): ReuniaoService
    {
        return app(ReuniaoService::class);
    }

    private function conselho(): ConselhoClasseService
    {
        return app(ConselhoClasseService::class);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function marcarConselho(array $extra = []): Reuniao
    {
        return $this->reunioes()->agendar(array_merge([
            'tipo' => TipoReuniao::ConselhoClasse,
            'titulo' => 'Conselho de classe — 3º bimestre — 6º ano A',
            'inicio_em' => '2026-10-30 14:00',
            'turma_id' => $this->turma->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'participantes' => [['user_id' => $this->conselheiro->id, 'papel' => 'presidente']],
        ], $extra), $this->organizador);
    }

    private function linha(Reuniao $conselho, Matricula $matricula): ReuniaoAluno
    {
        return $conselho->alunos()->where('matricula_id', $matricula->id)->firstOrFail();
    }

    // ─── Pauta ───────────────────────────────────────────────────────

    public function test_a_pauta_traz_os_alunos_ativos_em_ordem_alfabetica(): void
    {
        $conselho = $this->marcarConselho();

        $nomes = $conselho->alunos()->with('matricula.pessoa')->get()->map(fn (ReuniaoAluno $a) => $a->matricula->pessoa->nome)->all();

        $this->assertSame(['Paula Souza', 'Rui Andrade'], $nomes);
        $this->assertSame([1, 2], $conselho->alunos()->pluck('ordem')->all());
    }

    public function test_a_pauta_fotografa_os_indicadores_de_cada_aluno(): void
    {
        $conselho = $this->marcarConselho();

        $paula = $this->linha($conselho, $this->paula)->indicadores;
        $this->assertSame(6.0, (float) $paula['nota_aprovacao']);
        $this->assertSame(['Matemática'], $paula['disciplinas_abaixo_da_media']);
        $this->assertEqualsWithDelta(6.0, $paula['media_geral'], 0.01);
        $this->assertEqualsWithDelta(50.0, $paula['frequencia'], 0.01);
        $this->assertSame(2, $paula['faltas']);
        $this->assertSame(1, $paula['ocorrencias']);
        $this->assertSame(['Conversa em aula'], $paula['ocorrencias_tipos']);
        $this->assertFalse($paula['parecer_publicado']);

        $rui = $this->linha($conselho, $this->rui)->indicadores;
        $this->assertSame([], $rui['disciplinas_abaixo_da_media']);
        $this->assertEqualsWithDelta(9.0, $rui['media_geral'], 0.01);
        $this->assertEqualsWithDelta(100.0, $rui['frequencia'], 0.01);
        $this->assertSame(0, $rui['ocorrencias']);
        $this->assertTrue($rui['parecer_publicado']);
    }

    public function test_um_so_conselho_por_turma_e_etapa(): void
    {
        $this->marcarConselho();

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('já tem conselho de classe');

        $this->marcarConselho(['titulo' => 'Conselho duplicado', 'inicio_em' => '2026-10-31 14:00']);
    }

    public function test_um_conselho_cancelado_libera_a_turma_para_outro(): void
    {
        $primeiro = $this->marcarConselho();
        $this->reunioes()->cancelar($primeiro, $this->organizador, 'Remarcado para a próxima semana');

        $segundo = $this->marcarConselho(['inicio_em' => '2026-11-04 14:00']);

        $this->assertSame(StatusReuniao::Agendada, $segundo->status);
    }

    public function test_a_etapa_precisa_ser_da_turma(): void
    {
        $outra = Turma::create(['nome' => '6º ano B', 'periodo_letivo_id' => $this->turma->periodo_letivo_id]);
        $etapaDaOutra = EtapaAvaliativa::create(['turma_id' => $outra->id, 'nome' => '3º bimestre', 'periodo_letivo_id' => $this->turma->periodo_letivo_id, 'data_inicio' => '2026-08-01', 'data_fim' => '2026-10-15']);

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('não é desta turma');

        $this->marcarConselho(['etapa_avaliativa_id' => $etapaDaOutra->id]);
    }

    public function test_sem_a_coluna_turma_id_a_etapa_e_do_periodo_letivo(): void
    {
        // Em MySQL a migration `refine_educational_relationships` remove `etapa_avaliativa.turma_id`: a etapa é do período letivo.
        $this->assertStringContainsString('"turma_id" = ?', EtapaAvaliativa::query()->daTurma($this->turma)->toSql());

        Schema::partialMock()->shouldReceive('hasColumn')->with('etapa_avaliativa', 'turma_id')->andReturnFalse();

        $sql = EtapaAvaliativa::query()->daTurma($this->turma)->toSql();
        $this->assertStringContainsString('"periodo_letivo_id" = ?', $sql);
        $this->assertStringNotContainsString('"turma_id"', $sql);
    }

    public function test_conselho_exige_turma_e_etapa(): void
    {
        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('turma e de etapa');

        $this->marcarConselho(['turma_id' => null]);
    }

    public function test_sugere_os_participantes_da_turma(): void
    {
        $professor = User::factory()->create(['name' => 'Docente de Matemática', 'activated_at' => now()->subDay()]);
        $professor->pessoas()->attach(Pessoa::where('nome', 'Docente de Matemática')->value('id'));

        $curso = Curso::create(['unidade_id' => Unidade::create(['nome' => 'Unidade Central'])->id, 'nome_externo' => 'Ensino Fundamental II', 'nome_interno' => 'EF2']);
        $serie = Serie::create(['nome' => '6º ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->turma->update(['serie_id' => $serie->id]);
        $coordenadorPessoa = Pessoa::create(['nome' => 'Coordenador do Curso']);
        $coordenadorUser = User::factory()->create(['name' => 'Coordenador do Curso', 'activated_at' => now()->subDay()]);
        $coordenadorUser->pessoas()->attach($coordenadorPessoa->id);
        Coordenador::create(['curso_id' => $curso->id, 'pessoa_id' => $coordenadorPessoa->id, 'cargo' => 'Coordenação', 'data_inicio' => '2026-01-01']);
        // Quem não tem usuário ativo fica de fora.
        $inativo = User::factory()->create(['name' => 'Docente de História', 'activated_at' => now()->subDay(), 'deactivated_at' => now()->subHour()]);
        $inativo->pessoas()->attach(Pessoa::where('nome', 'Docente de História')->value('id'));

        $ids = $this->conselho()->sugerirParticipantes($this->turma->refresh())->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$this->conselheiro->id, $professor->id, $coordenadorUser->id], $ids);
        $this->assertSame($this->conselheiro->id, $this->conselho()->presidente($this->turma)?->id);
    }

    public function test_atualizar_a_pauta_inclui_quem_entrou_e_preserva_quem_ja_foi_deliberado(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 15:00:00'));

        $this->conselho()->deliberar($this->linha($conselho, $this->rui), 'sem_pendencias', null, $this->organizador);
        $fotoDoRui = $this->linha($conselho, $this->rui)->indicadores;

        // Rui ganha uma ocorrência depois da fotografia; entra uma aluna nova na turma.
        $tipo = TipoOcorrencia::first();
        OcorrenciaEscolar::create(['matricula_id' => $this->rui->id, 'tipo_ocorrencia_id' => $tipo->id, 'data_hora' => '2026-10-30 10:00:00', 'descricao' => 'Atraso', 'notificar_responsaveis' => false]);
        $zeca = Matricula::factory()->create(['pessoa_id' => Pessoa::create(['nome' => 'Ana Nova'])->id, 'turma_id' => $this->turma->id]);

        $this->assertSame(3, $this->conselho()->montarPauta($conselho->refresh()));

        $this->assertSame($fotoDoRui, $this->linha($conselho, $this->rui)->indicadores, 'Quem já foi deliberado mantém o que o conselho viu.');
        $this->assertSame(1, $this->linha($conselho, $this->paula)->indicadores['ocorrencias']);
        $this->assertSame(1, $this->linha($conselho, $zeca)->ordem, 'A ordem alfabética é refeita.');
        $this->assertSame(3, $conselho->alunos()->count());
    }

    // ─── Deliberação ─────────────────────────────────────────────────

    public function test_nao_delibera_antes_do_conselho_comecar(): void
    {
        $conselho = $this->marcarConselho();

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('ainda não começou');

        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'sem_pendencias', null, $this->organizador);
    }

    public function test_delibera_com_encaminhamento_sem_mexer_em_nota_nem_matricula(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 15:00:00'));
        $notasAntes = Nota::query()->orderBy('id')->pluck('valor', 'id')->all();

        $linha = $this->conselho()->deliberar(
            $this->linha($conselho, $this->paula),
            'recuperacao',
            'Dificuldade em frações.',
            $this->conselheiro,
            ['descricao' => 'Recuperação paralela de Matemática', 'responsavel_user_id' => $this->conselheiro->id],
        );

        $this->assertSame('recuperacao', $linha->deliberacao);
        $this->assertSame('Indicar recuperação', $linha->rotuloDaDeliberacao());
        $this->assertSame($this->conselheiro->id, $linha->deliberado_por_user_id);

        $encaminhamento = $linha->encaminhamentos()->firstOrFail();
        $this->assertSame('Recuperação paralela de Matemática', $encaminhamento->descricao);
        $this->assertSame($this->paula->id, $encaminhamento->matricula_id);
        $this->assertSame($this->conselheiro->id, $encaminhamento->responsavel_user_id);
        // Prazo padrão: 15 dias a partir de hoje.
        $this->assertSame('2026-11-14', $encaminhamento->prazo->toDateString());

        $this->assertSame($notasAntes, Nota::query()->orderBy('id')->pluck('valor', 'id')->all());
        $this->assertSame('ativa', $this->paula->refresh()->situacao->value);
        $this->assertSame($this->turma->id, $this->paula->turma_id);
    }

    public function test_deliberacao_que_pede_acompanhamento_exige_o_encaminhamento(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 15:00:00'));

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('pede um encaminhamento');

        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'chamar_familia', null, $this->organizador);
    }

    public function test_deliberacao_fora_da_lista_e_recusada(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 15:00:00'));

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('uma das deliberações');

        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'reprovar', null, $this->organizador);
    }

    public function test_rever_a_deliberacao_troca_o_encaminhamento_aberto(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 15:00:00'));
        $paula = $this->linha($conselho, $this->paula);

        $this->conselho()->deliberar($paula, 'recuperacao', null, $this->organizador, ['descricao' => 'Recuperação paralela']);
        $this->conselho()->deliberar($paula->refresh(), 'sem_pendencias', 'A família já combinou reforço particular.', $this->organizador);

        $this->assertSame('sem_pendencias', $paula->refresh()->deliberacao);
        $this->assertSame(0, $paula->encaminhamentos()->count());
    }

    public function test_a_ata_do_conselho_so_fecha_com_todos_deliberados(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 16:00:00'));
        $this->reunioes()->marcarRealizada($conselho, $this->organizador, [$this->organizador->id => true, $this->conselheiro->id => true]);

        $this->conselho()->deliberar($this->linha($conselho, $this->rui), 'destaque', null, $this->organizador);
        $this->assertSame(1, $this->conselho()->pendentesDeDeliberacao($conselho));

        try {
            $this->reunioes()->fecharAta($conselho->refresh(), $this->organizador);
            $this->fail('Falta deliberar sobre a Paula.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertStringContainsString('Falta deliberar sobre 1 aluno', $e->getMessage());
        }

        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'acompanhar', null, $this->organizador, ['descricao' => 'Acompanhar as faltas']);

        // No conselho de classe o texto da ata é opcional: a pauta por aluno é o registro.
        $fechada = $this->reunioes()->fecharAta($conselho->refresh(), $this->organizador);
        $this->assertSame(StatusReuniao::AtaFechada, $fechada->status);
        $this->assertSame(0, $this->conselho()->pendentesDeDeliberacao($fechada));
    }

    public function test_depois_da_ata_fechada_a_deliberacao_nao_muda(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 16:00:00'));
        $this->reunioes()->marcarRealizada($conselho, $this->organizador, [$this->organizador->id => true]);
        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'sem_pendencias', null, $this->organizador);
        $this->conselho()->deliberar($this->linha($conselho, $this->rui), 'sem_pendencias', null, $this->organizador);
        $this->reunioes()->fecharAta($conselho->refresh(), $this->organizador);

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('não pode mais mudar');

        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'destaque', null, $this->organizador);
    }

    public function test_o_pdf_da_ata_do_conselho_lista_as_deliberacoes(): void
    {
        $conselho = $this->marcarConselho();
        $this->travelTo(Carbon::parse('2026-10-30 16:00:00'));
        $this->reunioes()->marcarRealizada($conselho, $this->organizador, [$this->organizador->id => true]);
        $this->conselho()->deliberar($this->linha($conselho, $this->paula), 'acompanhar', 'Faltas frequentes.', $this->organizador, ['descricao' => 'Acompanhar as faltas', 'responsavel_user_id' => $this->conselheiro->id]);

        $html = view('pdfs.ata-reuniao', [
            'reuniao' => $conselho->refresh()->load(['organizador', 'turma', 'etapaAvaliativa', 'participantes.user', 'alunos.matricula.pessoa', 'alunos.encaminhamentos.responsavel', 'encaminhamentos.responsavel', 'adendos.autor', 'ataFechadaPor']),
            'deliberacoes' => config('reunioes.deliberacoes'),
        ])->render();

        $this->assertStringContainsString('Paula Souza', $html);
        $this->assertStringContainsString('Acompanhamento pedagógico', $html);
        $this->assertStringContainsString('Faltas frequentes.', $html);
        $this->assertStringContainsString('Acompanhar as faltas', $html);
        $this->assertStringContainsString('Minuta', $html);
        $this->assertSame(StatusEncaminhamento::Aberto, $conselho->encaminhamentos()->first()->status);
    }
}
