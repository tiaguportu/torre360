<?php

namespace Tests\Feature;

use App\Enums\ConceitoHabilidade;
use App\Filament\Portal\Pages\Calendario;
use App\Filament\Portal\Pages\Frequencia;
use App\Filament\Portal\Pages\Horarios;
use App\Filament\Portal\Pages\Notas;
use App\Models\Avaliacao;
use App\Models\AvaliacaoHabilidade;
use App\Models\CategoriaAvaliacao;
use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\NotaHabilidade;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Services\FrequenciaAlunoService;
use App\Services\HorarioAlunoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalNotasFrequenciaHorariosTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $periodo;

    private Pessoa $professor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $this->periodo = PeriodoLetivo::factory()->create(['nota_aprovacao' => 7]);
        $this->professor = Pessoa::factory()->create(['nome' => 'Professora Helena']);
    }

    /**
     * Cria um responsável (usuário) com um aluno matriculado numa turma nova.
     *
     * @return array{user: User, aluno: Pessoa, turma: Turma, matricula: Matricula}
     */
    private function familia(string $nomeAluno = 'Aluno Portal', ?User $user = null): array
    {
        $turma = Turma::factory()->create(['periodo_letivo_id' => $this->periodo->id]);

        if (! $user) {
            $responsavel = Pessoa::factory()->create();
            $user = User::factory()->create(['activated_at' => now()]);
            $responsavel->users()->save($user);
            $user->assignRole('responsavel');
        }

        $aluno = Pessoa::factory()->create(['nome' => $nomeAluno]);
        $user->pessoas()->first()->alunos()->attach($aluno->id);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        return compact('user', 'aluno', 'turma', 'matricula');
    }

    private function aula(Turma $turma, Disciplina $disciplina, Carbon $data, array $extra = []): CronogramaAula
    {
        return CronogramaAula::factory()->create(array_merge([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $this->professor->id,
            'data' => $data->toDateString(),
        ], $extra));
    }

    private function frequencia(Matricula $matricula, CronogramaAula $aula, string $situacao): FrequenciaEscolar
    {
        return FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aula->id,
            'situacao' => $situacao,
        ]);
    }

    private function lancarNota(array $familia, Disciplina $disciplina, EtapaAvaliativa $etapa, CategoriaAvaliacao $categoria, float $valor): void
    {
        $avaliacao = Avaliacao::create([
            'turma_id' => $familia['turma']->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'professor_id' => $this->professor->id,
            'data_prevista' => now()->subDays(3),
            'data_ocorrencia' => now()->subDays(3),
            'data_limite_lancamento' => now()->addDays(20),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        Nota::create(['matricula_id' => $familia['matricula']->id, 'avaliacao_id' => $avaliacao->id, 'valor' => $valor]);
    }

    private function etapa(Turma $turma, string $nome = '1º Bimestre'): EtapaAvaliativa
    {
        return EtapaAvaliativa::create([
            'nome' => $nome,
            'periodo_letivo_id' => $this->periodo->id,
            'turma_id' => $turma->id,
            'data_inicio' => now()->subMonth()->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);
    }

    // ─── Acesso ────────────────────────────────────────────────────

    public function test_responsavel_acessa_as_tres_paginas_do_portal(): void
    {
        $familia = $this->familia();

        foreach (['notas', 'frequencia', 'horarios'] as $slug) {
            $this->actingAs($familia['user'])->get("/portal/{$slug}")->assertOk();
        }
    }

    public function test_visitante_nao_autenticado_e_redirecionado(): void
    {
        foreach (['notas', 'frequencia', 'horarios'] as $slug) {
            $this->get("/portal/{$slug}")->assertRedirect();
        }
    }

    public function test_dashboard_do_portal_tem_atalhos_para_as_novas_paginas(): void
    {
        $familia = $this->familia('Aluno Atalho');

        $this->actingAs($familia['user'])
            ->get('/portal')
            ->assertOk()
            ->assertSee(Notas::getUrl(['aluno' => $familia['matricula']->id]), false)
            ->assertSee(Frequencia::getUrl(['aluno' => $familia['matricula']->id]), false)
            ->assertSee(Horarios::getUrl(['aluno' => $familia['matricula']->id]), false);
    }

    // ─── Seleção de aluno / isolamento ─────────────────────────────

    public function test_nao_e_possivel_ver_matricula_de_outra_familia_adulterando_o_parametro(): void
    {
        $minha = $this->familia('Meu Filho');
        $outra = $this->familia('Filho Alheio');

        $disciplina = Disciplina::factory()->create(['nome' => 'Segredo Alheio']);
        $aula = $this->aula($outra['turma'], $disciplina, now());
        $this->frequencia($outra['matricula'], $aula, 'ausente');

        $pagina = Livewire::actingAs($minha['user'])
            ->withQueryParams(['aluno' => $outra['matricula']->id])
            ->test(Frequencia::class);

        $this->assertSame($minha['matricula']->id, $pagina->instance()->getMatriculaSelecionada()->id);
        $pagina->assertDontSee('Filho Alheio')->assertDontSee('Segredo Alheio');

        $pagina->set('matriculaId', $outra['matricula']->id);
        $this->assertSame($minha['matricula']->id, $pagina->instance()->getMatriculaSelecionada()->id);
    }

    public function test_responsavel_com_dois_filhos_alterna_entre_eles(): void
    {
        $primeiro = $this->familia('Filho Um');
        $segundo = $this->familia('Filho Dois', $primeiro['user']);

        $pagina = Livewire::actingAs($primeiro['user'])->test(Horarios::class);

        $opcoes = $pagina->instance()->getOpcoesAlunos();
        $this->assertCount(2, $opcoes);

        $pagina->set('matriculaId', $primeiro['matricula']->id);
        $this->assertSame('Filho Um', $pagina->instance()->getMatriculaSelecionada()->pessoa->nome);

        $pagina->set('matriculaId', $segundo['matricula']->id);
        $this->assertSame('Filho Dois', $pagina->instance()->getMatriculaSelecionada()->pessoa->nome);
    }

    public function test_usuario_sem_alunos_ve_mensagem_de_vazio(): void
    {
        $responsavel = Pessoa::factory()->create();
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');

        foreach ([Notas::class, Frequencia::class, Horarios::class] as $pagina) {
            Livewire::actingAs($user)->test($pagina)->assertSee('Nenhum aluno vinculado');
        }
    }

    // ─── Notas ─────────────────────────────────────────────────────

    public function test_notas_mostram_media_por_disciplina_e_destacam_abaixo_da_media(): void
    {
        $familia = $this->familia();
        $etapa = $this->etapa($familia['turma']);
        $categoria = CategoriaAvaliacao::create(['nome' => 'Prova Mensal']);
        $matematica = Disciplina::factory()->create(['nome' => 'Matematica Portal']);
        $historia = Disciplina::factory()->create(['nome' => 'Historia Portal']);

        $this->lancarNota($familia, $matematica, $etapa, $categoria, 9.5);
        $this->lancarNota($familia, $historia, $etapa, $categoria, 4.0);

        $pagina = Livewire::actingAs($familia['user'])->test(Notas::class);

        $pagina->assertSee('1º Bimestre')
            ->assertSee('Matematica Portal')
            ->assertSee('9,5')
            ->assertSee('Historia Portal')
            ->assertSee('4,0')
            ->assertSee('Prova Mensal');

        $dados = $pagina->instance()->getDados();
        $this->assertSame(7.0, $dados['nota_aprovacao']);
        $this->assertCount(1, $dados['etapas']);
    }

    public function test_notas_sem_lancamentos_mostram_aviso(): void
    {
        $familia = $this->familia();

        Livewire::actingAs($familia['user'])
            ->test(Notas::class)
            ->assertSee('Ainda não há notas lançadas');
    }

    public function test_notas_nao_expoem_notas_de_outro_aluno(): void
    {
        $minha = $this->familia('Meu Filho');
        $outra = $this->familia('Filho Alheio');
        $etapa = $this->etapa($outra['turma'], 'Etapa Alheia');
        $categoria = CategoriaAvaliacao::create(['nome' => 'Categoria Alheia']);
        $this->lancarNota($outra, Disciplina::factory()->create(['nome' => 'Disciplina Alheia']), $etapa, $categoria, 8);

        Livewire::actingAs($minha['user'])
            ->test(Notas::class)
            ->assertDontSee('Etapa Alheia')
            ->assertDontSee('Disciplina Alheia');
    }

    public function test_notas_exibem_conceitos_de_habilidades_bncc(): void
    {
        $familia = $this->familia();
        $etapa = $this->etapa($familia['turma'], '1º Trimestre');
        $habilidade = Habilidade::factory()->create([
            'codigo' => 'EI01EO01',
            'nome' => 'Perceber que suas ações têm efeitos nas outras pessoas.',
            'tipo' => 'BNCC',
        ]);
        $avaliacao = AvaliacaoHabilidade::factory()->create([
            'turma_id' => $familia['turma']->id,
            'etapa_avaliativa_id' => $etapa->id,
        ]);
        NotaHabilidade::create([
            'avaliacao_habilidade_id' => $avaliacao->id,
            'matricula_id' => $familia['matricula']->id,
            'habilidade_id' => $habilidade->id,
            'conceito' => ConceitoHabilidade::EM_DESENVOLVIMENTO->value,
            'observacao' => 'Está evoluindo bem.',
        ]);

        Livewire::actingAs($familia['user'])
            ->test(Notas::class)
            ->assertSee('Habilidades — 1º Trimestre')
            ->assertSee('EI01EO01')
            ->assertSee('Em desenvolvimento')
            ->assertSee('Está evoluindo bem.')
            ->assertDontSee('Ainda não há notas lançadas');
    }

    // ─── Frequência ────────────────────────────────────────────────

    public function test_resumo_de_frequencia_calcula_percentual_por_disciplina_e_geral(): void
    {
        $familia = $this->familia();
        $portugues = Disciplina::factory()->create(['nome' => 'Portugues']);
        $ciencias = Disciplina::factory()->create(['nome' => 'Ciencias']);

        foreach (['presente', 'presente', 'presente', 'ausente'] as $i => $situacao) {
            $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $portugues, now()->subDays($i + 1)), $situacao);
        }
        foreach (['ausente', 'ausente'] as $i => $situacao) {
            $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $ciencias, now()->subDays($i + 10)), $situacao);
        }

        $resumo = app(FrequenciaAlunoService::class)->resumo($familia['matricula']);

        $this->assertSame(6, $resumo['total']);
        $this->assertSame(3, $resumo['presencas']);
        $this->assertSame(3, $resumo['faltas']);
        $this->assertSame(50.0, $resumo['percentual']);
        $this->assertTrue($resumo['abaixo_minimo']);

        $porNome = collect($resumo['por_disciplina'])->keyBy('nome');
        $this->assertSame(75.0, $porNome['Portugues']['percentual']);
        $this->assertFalse($porNome['Portugues']['abaixo_minimo']);
        $this->assertSame(0.0, $porNome['Ciencias']['percentual']);
        $this->assertTrue($porNome['Ciencias']['abaixo_minimo']);
        $this->assertSame(['Ciencias', 'Portugues'], array_column($resumo['por_disciplina'], 'nome'));
    }

    public function test_frequencia_exatamente_no_minimo_nao_e_considerada_abaixo(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create();

        foreach (['presente', 'presente', 'presente', 'ausente'] as $i => $situacao) {
            $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDays($i + 1)), $situacao);
        }

        $resumo = app(FrequenciaAlunoService::class)->resumo($familia['matricula']);

        $this->assertSame(75.0, $resumo['percentual']);
        $this->assertFalse($resumo['abaixo_minimo']);
    }

    public function test_registros_sem_situacao_nao_entram_no_resumo(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create();

        $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDay()), 'presente');
        // "Sem registro": no MySQL a coluna é nullable; no SQLite de teste usa-se string vazia.
        $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDays(2)), '');

        $resumo = app(FrequenciaAlunoService::class)->resumo($familia['matricula']);

        $this->assertSame(1, $resumo['total']);
        $this->assertSame(100.0, $resumo['percentual']);
    }

    public function test_aluno_sem_registros_tem_resumo_vazio(): void
    {
        $familia = $this->familia();

        $resumo = app(FrequenciaAlunoService::class)->resumo($familia['matricula']);

        $this->assertSame(0, $resumo['total']);
        $this->assertNull($resumo['percentual']);
        $this->assertFalse($resumo['abaixo_minimo']);
        $this->assertSame([], $resumo['por_disciplina']);

        Livewire::actingAs($familia['user'])->test(Frequencia::class)->assertSee('Ainda não há registros de frequência');
    }

    public function test_pagina_de_frequencia_lista_registros_alerta_abaixo_do_minimo_e_filtra_faltas(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create(['nome' => 'Geografia Portal']);

        $presenca = $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDays(3)), 'presente');
        $falta = $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDays(2)), 'ausente');
        $falta2 = $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDay()), 'ausente');

        $pagina = Livewire::actingAs($familia['user'])
            ->test(Frequencia::class)
            ->assertSee('abaixo do mínimo exigido')
            ->assertSee('Geografia Portal')
            ->assertCanSeeTableRecords([$presenca, $falta, $falta2]);

        $pagina->filterTable('situacao', 'ausente')
            ->assertCanSeeTableRecords([$falta, $falta2])
            ->assertCanNotSeeTableRecords([$presenca]);
    }

    public function test_frequencia_boa_nao_exibe_alerta(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create();

        foreach (range(1, 4) as $dia) {
            $this->frequencia($familia['matricula'], $this->aula($familia['turma'], $disciplina, now()->subDays($dia)), 'presente');
        }

        Livewire::actingAs($familia['user'])->test(Frequencia::class)->assertDontSee('abaixo do mínimo exigido');
    }

    public function test_tabela_de_frequencia_nao_mostra_registros_de_outro_aluno(): void
    {
        $minha = $this->familia('Meu Filho');
        $outra = $this->familia('Filho Alheio');
        $disciplina = Disciplina::factory()->create();

        $meu = $this->frequencia($minha['matricula'], $this->aula($minha['turma'], $disciplina, now()->subDay()), 'ausente');
        $alheio = $this->frequencia($outra['matricula'], $this->aula($outra['turma'], $disciplina, now()->subDay()), 'ausente');

        Livewire::actingAs($minha['user'])
            ->test(Frequencia::class)
            ->assertCanSeeTableRecords([$meu])
            ->assertCanNotSeeTableRecords([$alheio]);
    }

    // ─── Horários ──────────────────────────────────────────────────

    public function test_horarios_agrupam_aulas_da_semana_por_dia_e_ordenam_por_horario(): void
    {
        $familia = $this->familia();
        $matematica = Disciplina::factory()->create(['nome' => 'Matematica Horario']);
        $artes = Disciplina::factory()->create(['nome' => 'Artes Horario']);

        $segunda = HorarioAlunoService::inicioDaSemana(now());

        $this->aula($familia['turma'], $matematica, $segunda->copy(), ['hora_inicio' => '09:00:00', 'hora_fim' => '09:50:00']);
        $this->aula($familia['turma'], $artes, $segunda->copy(), ['hora_inicio' => '07:30:00', 'hora_fim' => '08:20:00', 'dever_casa' => 'Trazer tintas']);

        $agenda = app(HorarioAlunoService::class)->semana($familia['matricula'], now());

        $this->assertCount(5, $agenda['dias'], 'Segunda a sexta sempre; fim de semana só com conteúdo.');
        $primeiroDia = $agenda['dias'][0];
        $this->assertSame('Segunda-feira', $primeiroDia['nome']);
        $this->assertSame(['Artes Horario', 'Matematica Horario'], $primeiroDia['aulas']->map(fn ($a) => $a->disciplina->nome)->all());

        Livewire::actingAs($familia['user'])
            ->test(Horarios::class)
            ->assertSee('Matematica Horario')
            ->assertSee('07:30')
            ->assertSee('Trazer tintas')
            ->assertSee('Prof. Professora Helena');
    }

    public function test_horarios_incluem_fim_de_semana_apenas_quando_ha_aula(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create();
        $sabado = HorarioAlunoService::inicioDaSemana(now())->addDays(5);

        $this->aula($familia['turma'], $disciplina, $sabado);

        $agenda = app(HorarioAlunoService::class)->semana($familia['matricula'], now());

        $this->assertCount(6, $agenda['dias']);
        $this->assertSame('Sábado', end($agenda['dias'])['nome']);
    }

    public function test_horarios_marcam_dia_nao_letivo(): void
    {
        $familia = $this->familia();
        $quarta = HorarioAlunoService::inicioDaSemana(now())->addDays(2);

        DiaNaoLetivo::create([
            'periodo_letivo_id' => $this->periodo->id,
            'data' => $quarta->toDateString(),
            'descricao' => 'Recesso Escolar Teste',
            'flag_ativo' => true,
        ]);

        Livewire::actingAs($familia['user'])
            ->test(Horarios::class)
            ->assertSee('Recesso Escolar Teste');
    }

    public function test_horarios_nao_mostram_aulas_de_outra_turma_nem_de_outra_semana(): void
    {
        $familia = $this->familia();
        $outraTurma = Turma::factory()->create(['periodo_letivo_id' => $this->periodo->id]);
        $disciplina = Disciplina::factory()->create(['nome' => 'Disciplina Fora']);

        $this->aula($outraTurma, $disciplina, HorarioAlunoService::inicioDaSemana(now()), ['hora_inicio' => '08:00:00']);
        $this->aula($familia['turma'], $disciplina, HorarioAlunoService::inicioDaSemana(now())->addWeeks(2), ['hora_inicio' => '08:00:00']);

        Livewire::actingAs($familia['user'])
            ->test(Horarios::class)
            ->assertDontSee('Disciplina Fora')
            ->assertSee('Não há aulas cadastradas para esta semana.');
    }

    public function test_navegacao_semanal_muda_a_semana_exibida(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create(['nome' => 'Aula Semana Seguinte']);
        $proxima = HorarioAlunoService::inicioDaSemana(now())->addWeek();

        $this->aula($familia['turma'], $disciplina, $proxima, ['hora_inicio' => '10:00:00']);

        Livewire::actingAs($familia['user'])
            ->test(Horarios::class)
            ->assertDontSee('Aula Semana Seguinte')
            ->call('proximaSemana')
            ->assertSet('semana', $proxima->toDateString())
            ->assertSee('Aula Semana Seguinte')
            ->call('semanaAnterior')
            ->assertDontSee('Aula Semana Seguinte')
            ->call('proximaSemana')
            ->call('semanaAtual')
            ->assertSet('semana', null);
    }

    public function test_data_de_semana_invalida_cai_na_semana_atual(): void
    {
        $familia = $this->familia();

        $pagina = Livewire::actingAs($familia['user'])
            ->withQueryParams(['semana' => 'isso-nao-e-data'])
            ->test(Horarios::class)
            ->assertOk();

        $this->assertTrue($pagina->instance()->getInicioSemana()->isSameDay(HorarioAlunoService::inicioDaSemana(now())));
    }

    // ─── Calendário ────────────────────────────────────────────────

    public function test_calendario_inclui_aulas_da_turma_dentro_da_janela(): void
    {
        $familia = $this->familia();
        $disciplina = Disciplina::factory()->create(['nome' => 'Fisica Calendario']);

        $dentro = $this->aula($familia['turma'], $disciplina, now()->addDays(5), ['hora_inicio' => '08:00:00', 'hora_fim' => '08:50:00']);
        $semHorario = $this->aula($familia['turma'], $disciplina, now()->addDays(6));
        $longe = $this->aula($familia['turma'], $disciplina, now()->addDays(Calendario::AULAS_DIAS_FUTUROS + 30));
        $outraTurma = $this->aula(Turma::factory()->create(), $disciplina, now()->addDays(5));

        $eventos = collect(Livewire::actingAs($familia['user'])->test(Calendario::class)->instance()->getEvents());
        $ids = $eventos->pluck('id');

        $this->assertTrue($ids->contains('aula-'.$dentro->id));
        $this->assertTrue($ids->contains('aula-'.$semHorario->id));
        $this->assertFalse($ids->contains('aula-'.$longe->id));
        $this->assertFalse($ids->contains('aula-'.$outraTurma->id));

        $evento = $eventos->firstWhere('id', 'aula-'.$dentro->id);
        $this->assertSame('Fisica Calendario', $evento['title']);
        $this->assertFalse($evento['allDay']);
        $this->assertStringEndsWith('T08:00:00', $evento['start']);
        $this->assertStringEndsWith('T08:50:00', $evento['end']);
        $this->assertTrue($eventos->firstWhere('id', 'aula-'.$semHorario->id)['allDay']);
    }

    public function test_calendario_sem_turmas_nao_gera_eventos_de_aula(): void
    {
        $responsavel = Pessoa::factory()->create();
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');

        $eventos = Livewire::actingAs($user)->test(Calendario::class)->instance()->getEvents();

        $this->assertSame([], $eventos);
    }
}
