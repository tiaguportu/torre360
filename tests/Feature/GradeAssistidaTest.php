<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoDisponibilidadeProfessor;
use App\Filament\Pages\GradeHorariaAssistida;
use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Filament\Resources\Turmas\RelationManagers\GradeHorariosRelationManager;
use App\Filament\Resources\Turnos\Pages\EditTurno;
use App\Filament\Resources\Turnos\RelationManagers\TemposAulaRelationManager;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\DisponibilidadeProfessor;
use App\Models\GradeHorario;
use App\Models\MatrizCurricular;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Sala;
use App\Models\Serie;
use App\Models\TempoAula;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\DisponibilidadeProfessorService;
use App\Services\GradeAssistidaService;
use App\Services\GradeHorarioConflitoService;
use App\Services\TempoAulaService;
use Database\Factories\PeriodoLetivoFactory;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Grade horária assistida (Onda 64): tempos de aula do turno, disponibilidade dos professores, conferência com a matriz
 * curricular e sugestão de horários.
 *
 * O turno do cenário tem 5 tempos: 07:30, 08:20 e 09:10 antes do intervalo; 10:20 e 11:10 depois (50 minutos cada).
 */
class GradeAssistidaTest extends TestCase
{
    use RefreshDatabase;

    private Serie $serie;

    private Turno $turno;

    private Turma $turma;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'coordenador', 'professor', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        Role::findByName('coordenador')->givePermissionTo(Permission::firstOrCreate(['name' => 'GradeAssistida:Turma', 'guard_name' => 'web']));

        $this->serie = Serie::create([
            'nome' => 'Série '.uniqid(),
            'curso_id' => Curso::create(['unidade_id' => Unidade::create(['nome' => 'Unidade '.uniqid()])->id, 'nome_externo' => 'Curso', 'nome_interno' => 'Curso'])->id,
            'sistema_avaliacao' => 'Nota',
        ]);

        $this->turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);
        $this->servicoDeTempos()->gerar($this->turno, '07:30', 5, 50, 3, 20);

        $this->turma = $this->novaTurma('6º A');
    }

    private function servicoDeTempos(): TempoAulaService
    {
        return app(TempoAulaService::class);
    }

    private function servico(): GradeAssistidaService
    {
        return app(GradeAssistidaService::class);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /**
     * As permissões valem pelo papel ativo da sessão, que é global no teste.
     */
    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function novaTurma(string $nome, ?PeriodoLetivo $periodo = null): Turma
    {
        return Turma::factory()->create([
            'nome' => $nome,
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $periodo?->id ?? PeriodoLetivoFactory::idPadrao(),
        ]);
    }

    private function professor(string $nome): Pessoa
    {
        return Pessoa::factory()->create(['nome' => $nome]);
    }

    /**
     * Disciplina na matriz da série (aulas por semana) e, se houver professor, vinculada à turma com ele.
     */
    private function disciplina(string $nome, ?int $aulas, ?Pessoa $professor = null, ?Turma $turma = null, int $ordem = 1): Disciplina
    {
        $disciplina = Disciplina::factory()->create(['nome' => $nome]);

        MatrizCurricular::create(['serie_id' => $this->serie->id, 'disciplina_id' => $disciplina->id, 'carga_horaria_semanal' => $aulas, 'obrigatoria' => true, 'ordem' => $ordem]);
        ($turma ?? $this->turma)->disciplinas()->attach($disciplina->id, ['professor_id' => $professor?->id]);

        return $disciplina;
    }

    private function horario(Turma $turma, Disciplina $disciplina, int $dia, string $inicio, string $fim, ?Pessoa $professor = null, ?Sala $sala = null): GradeHorario
    {
        return GradeHorario::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professor?->id,
            'sala_id' => $sala?->id,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio.':00',
            'hora_fim' => $fim.':00',
        ]);
    }

    private function janela(Pessoa $professor, int $dia, string $inicio, string $fim, TipoDisponibilidadeProfessor $tipo = TipoDisponibilidadeProfessor::Disponivel, ?string $observacao = null): DisponibilidadeProfessor
    {
        return DisponibilidadeProfessor::create(['pessoa_id' => $professor->id, 'dia_semana' => $dia, 'hora_inicio' => $inicio.':00', 'hora_fim' => $fim.':00', 'tipo' => $tipo, 'observacao' => $observacao]);
    }

    /**
     * @param  array{propostas: list<array<string, mixed>>}  $sugestao
     * @return array<string, int>
     */
    private function porDisciplina(array $sugestao): array
    {
        $contagem = [];

        foreach ($sugestao['propostas'] as $proposta) {
            $contagem[$proposta['disciplina']] = ($contagem[$proposta['disciplina']] ?? 0) + 1;
        }

        ksort($contagem);

        return $contagem;
    }

    // --- Tempos de aula ----------------------------------------------------------------------------------------------

    public function test_gerar_cria_os_tempos_com_o_intervalo_depois_do_terceiro(): void
    {
        $tempos = $this->servicoDeTempos()->doTurno($this->turno->id);

        $this->assertSame(
            ['07:30-08:20', '08:20-09:10', '09:10-10:00', '10:20-11:10', '11:10-12:00'],
            $tempos->map(fn (TempoAula $t): string => $t->inicio().'-'.$t->fim())->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5], $tempos->pluck('ordem')->all());
    }

    public function test_gerar_nao_sobrescreve_sem_pedir_e_valida_os_limites(): void
    {
        try {
            $this->servicoDeTempos()->gerar($this->turno, '07:30', 4, 50);
            $this->fail('o turno já tem tempos');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já tem tempos de aula', $e->getMessage());
        }

        $refeitos = $this->servicoDeTempos()->gerar($this->turno, '13:00', 3, 45, null, 0, true);

        $this->assertCount(3, $refeitos);
        $this->assertSame('13:00', $refeitos->first()->inicio());
        $this->assertSame(3, TempoAula::where('turno_id', $this->turno->id)->count());

        foreach ([[0, 50], [13, 50], [3, 5], [3, 200]] as [$quantidade, $duracao]) {
            try {
                $this->servicoDeTempos()->gerar(Turno::create(['nome' => 'Tarde '.uniqid(), 'hora_inicio' => '13:00:00', 'hora_fim' => '18:00:00']), '13:00', $quantidade, $duracao);
                $this->fail("{$quantidade} tempos de {$duracao} min deveria ser recusado");
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('passam da meia-noite');

        $this->servicoDeTempos()->gerar(Turno::create(['nome' => 'Noite '.uniqid(), 'hora_inicio' => '22:00:00', 'hora_fim' => '23:59:00']), '22:30', 4, 50);
    }

    public function test_aula_dupla_vale_dois_tempos_e_horario_fora_do_sinal_vale_um(): void
    {
        $tempos = $this->servicoDeTempos()->doTurno($this->turno->id);

        $this->assertSame(1, $this->servicoDeTempos()->aulasDoHorario('07:30', '08:20', $tempos));
        $this->assertSame(2, $this->servicoDeTempos()->aulasDoHorario('07:30:00', '09:10:00', $tempos));
        $this->assertSame(2, $this->servicoDeTempos()->aulasDoHorario('09:10', '11:10', $tempos), 'o intervalo entre os tempos não conta');
        $this->assertSame(1, $this->servicoDeTempos()->aulasDoHorario('08:00', '08:50', $tempos), 'não cobre nenhum tempo inteiro: vale uma aula');
        $this->assertSame(1, $this->servicoDeTempos()->aulasDoHorario('07:30', '08:20', collect()), 'turno sem tempos');
    }

    public function test_sobreposicao_de_tempos(): void
    {
        $segundo = TempoAula::where('turno_id', $this->turno->id)->where('ordem', 2)->firstOrFail();

        $this->assertSame(2, $this->servicoDeTempos()->sobreposicao($this->turno->id, '08:30', '08:40')?->ordem);
        $this->assertSame(1, $this->servicoDeTempos()->sobreposicao($this->turno->id, '08:00', '08:30')?->ordem, 'cruza dois tempos: devolve o primeiro');
        $this->assertNull($this->servicoDeTempos()->sobreposicao($this->turno->id, '10:00', '10:20'), 'o intervalo está livre');
        $this->assertNull($this->servicoDeTempos()->sobreposicao($this->turno->id, '08:20', '09:10', $segundo->id), 'ele mesmo não conta');
    }

    // --- Disponibilidade ---------------------------------------------------------------------------------------------

    public function test_sem_janelas_o_professor_esta_livre(): void
    {
        $servico = app(DisponibilidadeProfessorService::class);

        $this->assertNull($servico->motivoDeIndisponibilidade(collect(), 1, '07:30', '08:20'));
    }

    public function test_indisponivel_bloqueia_e_diz_o_porque(): void
    {
        $ana = $this->professor('Ana');
        $this->janela($ana, 1, '10:00', '11:00', TipoDisponibilidadeProfessor::Indisponivel, 'consulta médica');
        $janelas = app(DisponibilidadeProfessorService::class)->janelasDe([$ana->id])->get($ana->id);

        $motivo = app(DisponibilidadeProfessorService::class)->motivoDeIndisponibilidade($janelas, 1, '10:20', '11:10');

        $this->assertSame('indisponível das 10:00 às 11:00 (consulta médica)', $motivo);
        $this->assertNull(app(DisponibilidadeProfessorService::class)->motivoDeIndisponibilidade($janelas, 1, '11:10', '12:00'), 'depois da janela');
        $this->assertNull(app(DisponibilidadeProfessorService::class)->motivoDeIndisponibilidade($janelas, 2, '10:20', '11:10'), 'em outro dia');
    }

    public function test_com_janelas_disponiveis_so_vale_dentro_delas_e_janelas_coladas_valem_como_uma(): void
    {
        $ana = $this->professor('Ana');
        $this->janela($ana, 2, '07:30', '09:10');
        $this->janela($ana, 2, '09:10', '12:00');
        $this->janela($ana, 4, '07:30', '08:20');
        $janelas = app(DisponibilidadeProfessorService::class)->janelasDe([$ana->id])->get($ana->id);
        $servico = app(DisponibilidadeProfessorService::class);

        $this->assertNull($servico->motivoDeIndisponibilidade($janelas, 2, '08:20', '09:10'));
        $this->assertNull($servico->motivoDeIndisponibilidade($janelas, 2, '09:10', '10:00'), 'a segunda janela começa onde a primeira termina');
        $this->assertNull($servico->motivoDeIndisponibilidade($janelas, 2, '08:50', '09:40'), 'cruza as duas janelas coladas');
        $this->assertSame('fora das janelas em que informou estar disponível', $servico->motivoDeIndisponibilidade($janelas, 4, '08:20', '09:10'));
        $this->assertSame('fora das janelas em que informou estar disponível', $servico->motivoDeIndisponibilidade($janelas, 1, '07:30', '08:20'), 'dia sem janela');
    }

    public function test_substituir_troca_as_janelas_e_recusa_dados_invalidos(): void
    {
        $ana = $this->professor('Ana');
        $this->janela($ana, 1, '07:30', '12:00');
        $servico = app(DisponibilidadeProfessorService::class);

        $servico->substituir($ana->id, [
            ['dia_semana' => 3, 'hora_inicio' => '07:30', 'hora_fim' => '10:00', 'tipo' => 'disponivel'],
            ['dia_semana' => 5, 'hora_inicio' => '10:00', 'hora_fim' => '11:00', 'tipo' => TipoDisponibilidadeProfessor::Indisponivel, 'observacao' => 'reunião'],
        ]);

        $this->assertSame([3, 5], DisponibilidadeProfessor::where('pessoa_id', $ana->id)->orderBy('dia_semana')->pluck('dia_semana')->all());

        try {
            $servico->substituir($ana->id, [['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fim' => '09:00', 'tipo' => 'disponivel']]);
            $this->fail('fim antes do início');
        } catch (\DomainException) {
            $this->assertSame(2, DisponibilidadeProfessor::where('pessoa_id', $ana->id)->count(), 'a troca inválida não apaga o que havia');
        }

        $servico->substituir($ana->id, []);
        $this->assertSame(0, DisponibilidadeProfessor::where('pessoa_id', $ana->id)->count(), 'lista vazia libera o professor');
    }

    // --- Conflitos por período ---------------------------------------------------------------------------------------

    public function test_professor_de_turma_de_periodo_que_nao_se_cruza_nao_conflita(): void
    {
        $ana = $this->professor('Ana');
        $antigo = PeriodoLetivo::create(['nome' => '2024', 'data_inicio' => '2024-02-01', 'data_fim' => '2024-12-15']);
        $turmaAntiga = $this->novaTurma('6º A (2024)', $antigo);
        $matematica = Disciplina::factory()->create(['nome' => 'Matemática']);
        $this->horario($turmaAntiga, $matematica, 1, '07:30', '08:20', $ana);

        $novo = new GradeHorario(['turma_id' => $this->turma->id, 'disciplina_id' => $matematica->id, 'professor_id' => $ana->id, 'dia_semana' => 1, 'hora_inicio' => '07:30:00', 'hora_fim' => '08:20:00']);

        $this->assertSame([], app(GradeHorarioConflitoService::class)->conflitos($novo), 'a grade do ano passado não trava a deste ano');
    }

    public function test_professor_e_sala_conflitam_em_periodo_que_se_cruza_e_turma_cancelada_nao_ocupa(): void
    {
        $ana = $this->professor('Ana');
        $sala = Sala::factory()->create();
        $matematica = Disciplina::factory()->create(['nome' => 'Matemática']);
        $semestre = PeriodoLetivo::create(['nome' => '2026.2', 'data_inicio' => '2026-08-01', 'data_fim' => '2026-12-20']);
        $outraTurma = $this->novaTurma('7º B', $semestre);
        $existente = $this->horario($outraTurma, $matematica, 2, '08:20', '09:10', $ana, $sala);

        $novo = new GradeHorario(['turma_id' => $this->turma->id, 'disciplina_id' => $matematica->id, 'professor_id' => $ana->id, 'sala_id' => $sala->id, 'dia_semana' => 2, 'hora_inicio' => '08:30:00', 'hora_fim' => '09:00:00']);
        $mensagens = app(GradeHorarioConflitoService::class)->conflitos($novo);

        $this->assertCount(2, $mensagens, '2026 e 2026.2 se cruzam: professor e sala conflitam');

        $outraTurma->update(['status' => 'cancelada']);

        $this->assertSame([], app(GradeHorarioConflitoService::class)->conflitos($novo), 'turma cancelada não ocupa professor nem sala');
        $this->assertNotNull($existente->fresh());
    }

    public function test_conflito_na_mesma_turma_vale_sempre(): void
    {
        $matematica = Disciplina::factory()->create(['nome' => 'Matemática']);
        $this->horario($this->turma, $matematica, 1, '07:30', '08:20');

        $novo = new GradeHorario(['turma_id' => $this->turma->id, 'disciplina_id' => $matematica->id, 'dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fim' => '08:40:00']);

        $this->assertCount(1, app(GradeHorarioConflitoService::class)->conflitos($novo));
    }

    public function test_aviso_de_disponibilidade_nao_bloqueia(): void
    {
        $ana = $this->professor('Ana');
        $this->janela($ana, 1, '07:30', '08:20', TipoDisponibilidadeProfessor::Indisponivel);
        $matematica = Disciplina::factory()->create(['nome' => 'Matemática']);
        $horario = new GradeHorario(['turma_id' => $this->turma->id, 'disciplina_id' => $matematica->id, 'professor_id' => $ana->id, 'dia_semana' => 1, 'hora_inicio' => '07:30:00', 'hora_fim' => '08:20:00']);

        $servico = app(GradeHorarioConflitoService::class);

        $this->assertSame([], $servico->conflitos($horario));
        $this->assertSame(['O professor está indisponível das 07:30 às 08:20 neste horário.'], $servico->avisos($horario));
        $this->assertSame([], $servico->avisos(new GradeHorario(['turma_id' => $this->turma->id, 'dia_semana' => 1, 'hora_inicio' => '07:30:00', 'hora_fim' => '08:20:00'])), 'sem professor, sem aviso');
    }

    // --- Conferência com a matriz ------------------------------------------------------------------------------------

    public function test_cobertura_compara_a_matriz_com_a_grade(): void
    {
        $ana = $this->professor('Ana');
        $matematica = $this->disciplina('Matemática', 5, $ana, null, 1);
        $portugues = $this->disciplina('Português', 3, null, null, 2);
        $artes = $this->disciplina('Artes', 1, null, null, 3);
        $ciencias = $this->disciplina('Ciências', 2, null, null, 4);
        $semCarga = $this->disciplina('Ed. Física', null, null, null, 5);
        $extra = Disciplina::factory()->create(['nome' => 'Xadrez']);

        $this->horario($this->turma, $matematica, 1, '07:30', '09:10', $ana); // aula dupla = 2
        $this->horario($this->turma, $matematica, 2, '07:30', '08:20', $ana); // 3 no total
        $this->horario($this->turma, $portugues, 1, '09:10', '10:00');
        $this->horario($this->turma, $portugues, 2, '09:10', '10:00');
        $this->horario($this->turma, $portugues, 3, '09:10', '10:00');
        $this->horario($this->turma, $artes, 4, '07:30', '08:20');
        $this->horario($this->turma, $artes, 5, '07:30', '08:20'); // 2 de 1: sobra
        $this->horario($this->turma, $semCarga, 3, '07:30', '08:20');
        $this->horario($this->turma, $extra, 4, '08:20', '09:10');

        $linhas = $this->servico()->cobertura($this->turma)->keyBy('disciplina');

        $this->assertSame(['Matemática', 'Português', 'Artes', 'Ciências', 'Ed. Física', 'Xadrez'], $linhas->keys()->all(), 'a ordem da matriz primeiro, o que está fora dela por último');

        $this->assertEquals(['esperadas' => 5, 'alocadas' => 3, 'faltam' => 2, 'excedem' => 0, 'situacao' => 'incompleta', 'professor' => 'Ana'], collect($linhas['Matemática'])->only(['esperadas', 'alocadas', 'faltam', 'excedem', 'situacao', 'professor'])->all());
        $this->assertSame('completa', $linhas['Português']['situacao']);
        $this->assertSame(['excedente', 1], [$linhas['Artes']['situacao'], $linhas['Artes']['excedem']]);
        $this->assertSame(['incompleta', 2, 0], [$linhas['Ciências']['situacao'], $linhas['Ciências']['faltam'], $linhas['Ciências']['alocadas']]);
        $this->assertSame(['sem_carga', 0], [$linhas['Ed. Física']['situacao'], $linhas['Ed. Física']['faltam']]);
        $this->assertSame(['fora_da_matriz', false, null], [$linhas['Xadrez']['situacao'], $linhas['Xadrez']['na_matriz'], $linhas['Xadrez']['esperadas']]);
        $this->assertNull($linhas['Português']['professor']);
        $this->assertSame($ciencias->id, $linhas['Ciências']['disciplina_id']);
    }

    public function test_visao_geral_soma_por_turma_e_ignora_as_encerradas(): void
    {
        $this->disciplina('Matemática', 5, $this->professor('Ana'));
        $segunda = $this->novaTurma('6º B');
        $this->novaTurma('6º C')->update(['status' => 'concluida']);
        $semTempos = Turma::factory()->create(['nome' => '6º D', 'serie_id' => $this->serie->id, 'turno_id' => Turno::create(['nome' => 'Sem sinal', 'hora_inicio' => '13:00:00', 'hora_fim' => '18:00:00'])->id, 'periodo_letivo_id' => $this->turma->periodo_letivo_id]);

        $visao = $this->servico()->visaoGeral($this->turma->periodoLetivo)->keyBy(fn (array $l): string => $l['turma']->nome);

        $this->assertSame(['6º A', '6º B', '6º D'], $visao->keys()->all());
        $this->assertSame([5, 0, 5], [$visao['6º A']['esperadas'], $visao['6º A']['alocadas'], $visao['6º A']['faltam']]);
        $this->assertFalse($visao['6º A']['sem_tempos']);
        $this->assertTrue($visao['6º D']['sem_tempos']);
        $this->assertNotNull($segunda);
        $this->assertNotNull($semTempos);
    }

    // --- Sugestão ----------------------------------------------------------------------------------------------------

    public function test_sugestao_preenche_o_que_falta_sem_sobrepor_nada_e_espalhando_pela_semana(): void
    {
        $this->disciplina('Matemática', 5, $this->professor('Ana'), null, 1);
        $this->disciplina('Português', 5, $this->professor('Bruno'), null, 2);
        $carla = $this->professor('Carla');
        $this->disciplina('História', 2, $carla, null, 3);
        $this->disciplina('Ciências', 3, $carla, null, 4);

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertSame(['Ciências' => 3, 'História' => 2, 'Matemática' => 5, 'Português' => 5], $this->porDisciplina($sugestao));
        $this->assertSame([], $sugestao['nao_alocadas']);
        $this->assertCount(15, $sugestao['propostas']);

        $lugares = array_map(fn (array $p): string => $p['dia_semana'].'-'.$p['tempo_ordem'], $sugestao['propostas']);
        $this->assertSame($lugares, array_values(array_unique($lugares)), 'a turma nunca tem duas aulas no mesmo tempo');

        $carlaPorLugar = collect($sugestao['propostas'])->where('professor_id', $carla->id)->map(fn (array $p): string => $p['dia_semana'].'-'.$p['tempo_ordem']);
        $this->assertSame($carlaPorLugar->values()->all(), $carlaPorLugar->unique()->values()->all(), 'o mesmo professor em duas disciplinas da turma não fica em dois lugares');

        foreach (['Matemática', 'Português'] as $nome) {
            $dias = collect($sugestao['propostas'])->where('disciplina', $nome)->pluck('dia_semana')->sort()->values()->all();
            $this->assertSame([1, 2, 3, 4, 5], $dias, "{$nome} tem uma aula por dia");
        }

        foreach (collect($sugestao['propostas'])->groupBy(fn (array $p): string => $p['disciplina'].'/'.$p['dia_semana']) as $grupo) {
            $this->assertLessThanOrEqual(2, $grupo->count());
        }
    }

    public function test_sugestao_e_deterministica_e_nao_grava_nada(): void
    {
        $this->disciplina('Matemática', 4, $this->professor('Ana'));
        $this->disciplina('Português', 4, $this->professor('Bruno'), null, 2);

        $primeira = $this->servico()->sugerir($this->turma);
        $segunda = $this->servico()->sugerir($this->turma);

        $this->assertSame($primeira, $segunda);
        $this->assertSame(0, GradeHorario::count());
    }

    public function test_sugestao_nao_mexe_no_que_ja_esta_na_grade(): void
    {
        $ana = $this->professor('Ana');
        $matematica = $this->disciplina('Matemática', 5, $ana);
        $segunda = $this->horario($this->turma, $matematica, 1, '07:30', '08:20', $ana);
        $terca = $this->horario($this->turma, $matematica, 2, '07:30', '08:20', $ana);

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertSame(['Matemática' => 3], $this->porDisciplina($sugestao), 'só as 3 que faltam');
        $this->assertNotContains('1-1', array_map(fn (array $p): string => $p['dia_semana'].'-'.$p['tempo_ordem'], $sugestao['propostas']));
        $this->assertNotContains('2-1', array_map(fn (array $p): string => $p['dia_semana'].'-'.$p['tempo_ordem'], $sugestao['propostas']));
        $this->assertSame([1, 2, 3, 4, 5], collect($sugestao['propostas'])->pluck('dia_semana')->merge([$segunda->dia_semana, $terca->dia_semana])->unique()->sort()->values()->all(), 'completa a semana com as que já existem');
    }

    public function test_sugestao_respeita_o_professor_ocupado_em_outra_turma_do_periodo(): void
    {
        $ana = $this->professor('Ana');
        $matematica = $this->disciplina('Matemática', 5, $ana);
        $outra = $this->novaTurma('7º B');
        $this->horario($outra, Disciplina::factory()->create(['nome' => 'Geometria']), 1, '07:30', '12:00', $ana); // segunda inteira

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertSame(['Matemática' => 5], $this->porDisciplina($sugestao));
        $this->assertNotContains(1, array_column($sugestao['propostas'], 'dia_semana'), 'Ana está ocupada na segunda inteira');
        $this->assertNotNull($matematica);
    }

    public function test_professor_de_turma_de_outro_periodo_nao_atrapalha_a_sugestao(): void
    {
        $ana = $this->professor('Ana');
        $this->disciplina('Matemática', 5, $ana);
        $antigo = PeriodoLetivo::create(['nome' => '2024', 'data_inicio' => '2024-02-01', 'data_fim' => '2024-12-15']);
        $this->horario($this->novaTurma('6º A (2024)', $antigo), Disciplina::factory()->create(['nome' => 'Geometria']), 1, '07:30', '12:00', $ana);

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertContains(1, array_column($sugestao['propostas'], 'dia_semana'), 'a segunda volta a estar livre');
    }

    public function test_sugestao_respeita_a_disponibilidade_e_o_limite_diario(): void
    {
        $ana = $this->professor('Ana');
        $this->disciplina('Matemática', 5, $ana);
        $this->janela($ana, 2, '07:30', '10:00'); // terça, tempos 1 a 3
        $this->janela($ana, 4, '07:30', '10:00'); // quinta, tempos 1 a 3

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertCount(4, $sugestao['propostas'], 'dois dias x limite de 2 aulas por dia');
        $this->assertEqualsCanonicalizing([2, 4], array_unique(array_column($sugestao['propostas'], 'dia_semana')));
        $this->assertSame([['disciplina' => 'Matemática', 'quantidade' => 1, 'motivo' => 'A disciplina já está no limite de 2 aulas por dia nos horários que sobraram.']], array_map(fn (array $i): array => Arr::only($i, ['disciplina', 'quantidade', 'motivo']), $sugestao['nao_alocadas']));
    }

    public function test_professor_indisponivel_em_todos_os_horarios_livres_explica_o_motivo(): void
    {
        $ana = $this->professor('Ana');
        $this->disciplina('Matemática', 2, $ana);

        foreach ([1, 2, 3, 4, 5] as $dia) {
            $this->janela($ana, $dia, '07:30', '12:00', TipoDisponibilidadeProfessor::Indisponivel);
        }

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertSame([], $sugestao['propostas']);
        $this->assertSame('Ana não tem disponibilidade ou já está ocupado em todos os horários livres da turma.', $sugestao['nao_alocadas'][0]['motivo']);
    }

    public function test_disciplina_sem_professor_so_e_sugerida_se_a_configuracao_deixar(): void
    {
        $this->disciplina('Artes', 2, null);

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertSame([], $sugestao['propostas']);
        $this->assertStringContainsString('Defina o professor', $sugestao['nao_alocadas'][0]['motivo']);

        config(['grade_horaria.sugerir_sem_professor' => true]);

        $this->assertSame(['Artes' => 2], $this->porDisciplina($this->servico()->sugerir($this->turma)));
    }

    public function test_quando_falta_tempo_na_semana_diz_quantas_aulas_nao_couberam(): void
    {
        $this->disciplina('Matemática', 10, $this->professor('Ana'), null, 1);
        $this->disciplina('Português', 10, $this->professor('Bruno'), null, 2);
        $this->disciplina('História', 10, $this->professor('Carla'), null, 3);

        $sugestao = $this->servico()->sugerir($this->turma);

        $this->assertCount(25, $sugestao['propostas'], 'a semana tem 5 dias x 5 tempos');
        $this->assertSame(5, collect($sugestao['nao_alocadas'])->sum('quantidade'));
        $this->assertSame('A turma não tem mais tempo livre na semana.', $sugestao['nao_alocadas'][0]['motivo']);
    }

    public function test_turno_sem_tempos_avisa_e_nao_sugere(): void
    {
        $semTempos = Turma::factory()->create(['nome' => '6º Z', 'serie_id' => $this->serie->id, 'turno_id' => Turno::create(['nome' => 'Tarde', 'hora_inicio' => '13:00:00', 'hora_fim' => '18:00:00'])->id, 'periodo_letivo_id' => $this->turma->periodo_letivo_id]);
        $this->disciplina('Matemática', 5, $this->professor('Ana'), $semTempos);

        $sugestao = $this->servico()->sugerir($semTempos);

        $this->assertSame([], $sugestao['propostas']);
        $this->assertStringContainsString('não tem tempos de aula cadastrados', $sugestao['avisos'][0]);
    }

    public function test_turma_sem_matriz_nao_tem_o_que_sugerir(): void
    {
        $outraSerie = Serie::create(['nome' => 'Sem matriz '.uniqid(), 'curso_id' => $this->serie->curso_id, 'sistema_avaliacao' => 'Nota']);
        $turma = Turma::factory()->create(['nome' => '9º A', 'serie_id' => $outraSerie->id, 'turno_id' => $this->turno->id, 'periodo_letivo_id' => $this->turma->periodo_letivo_id]);

        $this->assertSame(['propostas' => [], 'nao_alocadas' => [], 'avisos' => []], $this->servico()->sugerir($turma));
    }

    // --- Aplicação ---------------------------------------------------------------------------------------------------

    public function test_aplicar_grava_os_horarios_sem_conflito_e_registra_o_log(): void
    {
        $ana = $this->professor('Ana');
        $this->disciplina('Matemática', 3, $ana);
        $coordenador = $this->como($this->usuario('coordenador'));

        $resultado = $this->servico()->aplicar($this->turma, $coordenador);

        $this->assertSame(3, $resultado['criadas']);
        $this->assertSame([], $resultado['ignoradas']);

        $gravados = GradeHorario::where('turma_id', $this->turma->id)->get();

        $this->assertCount(3, $gravados);
        $this->assertTrue($gravados->every(fn (GradeHorario $g): bool => $g->professor_id === $ana->id && $g->sala_id === null));
        $this->assertSame([], app(GradeHorarioConflitoService::class)->conflitos($gravados->first()));
        $this->assertSame('00', substr((string) $gravados->first()->hora_inicio, -2), 'horário gravado com segundos');

        $this->assertSame(0, $this->servico()->cobertura($this->turma)->sum('faltam'), 'a conferência passa a ficar completa');

        $log = Activity::where('description', 'Sugestão da grade horária aplicada.')->firstOrFail();

        $this->assertSame(3, $log->properties['criadas']);
        $this->assertSame($coordenador->id, $log->causer_id);
    }

    public function test_aplicar_duas_vezes_nao_duplica(): void
    {
        $this->disciplina('Matemática', 3, $this->professor('Ana'));

        $this->servico()->aplicar($this->turma);
        $segunda = $this->servico()->aplicar($this->turma);

        $this->assertSame(0, $segunda['criadas']);
        $this->assertSame(3, GradeHorario::where('turma_id', $this->turma->id)->count());
    }

    public function test_horario_que_conflita_na_hora_de_gravar_fica_de_fora_e_e_avisado(): void
    {
        $this->disciplina('Matemática', 2, $this->professor('Ana'));

        $this->partialMock(GradeHorarioConflitoService::class, function ($mock): void {
            $mock->shouldReceive('conflitos')->andReturn(['O professor já está escalado em outra turma neste dia.']);
        });

        $resultado = app(GradeAssistidaService::class)->aplicar($this->turma);

        $this->assertSame(0, $resultado['criadas']);
        $this->assertCount(2, $resultado['ignoradas']);
        $this->assertStringContainsString('Matemática', $resultado['ignoradas'][0]);
        $this->assertSame(0, GradeHorario::count());
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_pagina_abre_para_quem_tem_a_permissao_e_nao_para_o_professor(): void
    {
        Livewire::actingAs($this->como($this->usuario('coordenador')))->test(GradeHorariaAssistida::class)->assertSuccessful();
        Livewire::actingAs($this->como($this->usuario('professor')))->test(GradeHorariaAssistida::class)->assertForbidden();
        Livewire::actingAs($this->como($this->usuario('secretaria')))->test(GradeHorariaAssistida::class)->assertForbidden();
    }

    public function test_pagina_mostra_a_conferencia_da_turma_escolhida(): void
    {
        $ana = $this->professor('Ana');
        $matematica = $this->disciplina('Matemática', 5, $ana);
        $this->disciplina('Artes', 2, null, null, 2);
        $this->horario($this->turma, $matematica, 1, '07:30', '08:20', $ana);

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->assertSet('turmaId', $this->turma->id)
            ->assertSet('periodoId', $this->turma->periodo_letivo_id)
            ->assertSee('Conferência com a matriz curricular')
            ->assertSee('Matemática')
            ->assertSee('Faltam aulas (4)')
            ->assertSee('sem professor definido')
            ->assertSee('1º tempo (07:30 às 08:20)')
            ->assertSee('Ana');
    }

    public function test_trocar_o_periodo_limpa_a_turma_que_nao_e_dele(): void
    {
        $outro = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->assertSet('turmaId', $this->turma->id)
            ->set('periodoId', $outro->id)
            ->assertSet('turmaId', null);
    }

    public function test_turma_inexistente_na_url_e_ignorada(): void
    {
        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => 99999])
            ->test(GradeHorariaAssistida::class)
            ->assertSet('turmaId', null)
            ->assertSee('Escolha uma turma');
    }

    public function test_acao_sugerir_mostra_a_proposta_e_aplica(): void
    {
        $this->disciplina('Matemática', 3, $this->professor('Ana'));

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->mountAction('sugerir')
            ->assertMountedActionModalSee(['3 horário(s) a colocar'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(3, GradeHorario::where('turma_id', $this->turma->id)->count());
    }

    public function test_acao_sugerir_sem_nada_a_colocar_avisa(): void
    {
        $this->disciplina('Matemática', 0, $this->professor('Ana'));

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->callAction('sugerir')
            ->assertNotified('Nada para aplicar');

        $this->assertSame(0, GradeHorario::count());
    }

    public function test_acao_sugerir_nao_aparece_sem_turma_escolhida(): void
    {
        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->test(GradeHorariaAssistida::class)
            ->assertActionHidden('sugerir');
    }

    public function test_salvar_a_disponibilidade_do_professor_pela_pagina(): void
    {
        $ana = $this->professor('Ana');
        $this->disciplina('Matemática', 3, $ana);

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->assertSee('Sem restrições')
            ->callAction('disponibilidade', data: [
                'janelas' => [
                    ['dia_semana' => 1, 'hora_inicio' => '07:30', 'hora_fim' => '10:00', 'tipo' => 'disponivel', 'observacao' => null],
                    ['dia_semana' => 3, 'hora_inicio' => '10:20', 'hora_fim' => '12:00', 'tipo' => 'indisponivel', 'observacao' => 'reunião'],
                ],
            ], arguments: ['professor' => $ana->id])
            ->assertHasNoActionErrors();

        $janelas = DisponibilidadeProfessor::where('pessoa_id', $ana->id)->orderBy('dia_semana')->get();

        $this->assertCount(2, $janelas);
        $this->assertSame(TipoDisponibilidadeProfessor::Indisponivel, $janelas[1]->tipo);
        $this->assertSame('reunião', $janelas[1]->observacao);
    }

    public function test_janela_com_fim_antes_do_inicio_e_recusada_na_tela(): void
    {
        $ana = $this->professor('Ana');
        $this->disciplina('Matemática', 3, $ana);

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->callAction('disponibilidade', data: [
                'janelas' => [['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fim' => '08:00', 'tipo' => 'disponivel']],
            ], arguments: ['professor' => $ana->id])
            ->assertHasActionErrors();

        $this->assertSame(0, DisponibilidadeProfessor::count());
    }

    public function test_so_edita_a_disponibilidade_de_professor_da_turma_escolhida(): void
    {
        $this->disciplina('Matemática', 3, $this->professor('Ana'));
        $estranho = $this->professor('Professor de outra turma');

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->withQueryParams(['turma' => $this->turma->id])
            ->test(GradeHorariaAssistida::class)
            ->callAction('disponibilidade', data: [
                'janelas' => [['dia_semana' => 1, 'hora_inicio' => '07:30', 'hora_fim' => '10:00', 'tipo' => 'disponivel']],
            ], arguments: ['professor' => $estranho->id]);

        $this->assertSame(0, DisponibilidadeProfessor::where('pessoa_id', $estranho->id)->count());
    }

    // --- Tempos de aula pela tela do turno --------------------------------------------------------------------------

    private function tempos(?Turno $turno = null)
    {
        return Livewire::actingAs($this->como($this->usuario('super_admin')))
            ->test(TemposAulaRelationManager::class, ['ownerRecord' => $turno ?? $this->turno, 'pageClass' => EditTurno::class]);
    }

    public function test_criar_tempo_que_cruza_com_outro_e_recusado(): void
    {
        $this->tempos()
            ->callAction(TestAction::make('create')->table(), data: ['ordem' => 6, 'hora_inicio' => '11:30', 'hora_fim' => '12:30']);

        $this->assertSame(5, TempoAula::where('turno_id', $this->turno->id)->count());
    }

    public function test_criar_tempo_depois_do_ultimo_funciona_e_a_ordem_nao_repete(): void
    {
        $this->tempos()
            ->callAction(TestAction::make('create')->table(), data: ['ordem' => 6, 'hora_inicio' => '12:10', 'hora_fim' => '13:00'])
            ->assertHasNoActionErrors();

        $this->assertSame(6, TempoAula::where('turno_id', $this->turno->id)->count());

        $this->tempos()
            ->callAction(TestAction::make('create')->table(), data: ['ordem' => 6, 'hora_inicio' => '14:00', 'hora_fim' => '14:50'])
            ->assertHasActionErrors(['ordem']);
    }

    public function test_gerar_tempos_pela_tela_do_turno(): void
    {
        $tarde = Turno::create(['nome' => 'Tarde', 'hora_inicio' => '13:00:00', 'hora_fim' => '18:00:00']);

        $this->tempos($tarde)
            ->callAction(TestAction::make('gerar')->table(), data: ['hora_inicio' => '13:00', 'quantidade' => 4, 'duracao' => 50, 'intervalo_apos' => 2, 'intervalo_minutos' => 15, 'substituir' => false])
            ->assertHasNoActionErrors();

        $this->assertSame(['13:00-13:50', '13:50-14:40', '14:55-15:45', '15:45-16:35'], $this->servicoDeTempos()->doTurno($tarde->id)->map(fn (TempoAula $t): string => $t->inicio().'-'.$t->fim())->all());

        $this->tempos($tarde)
            ->callAction(TestAction::make('gerar')->table(), data: ['hora_inicio' => '08:00', 'quantidade' => 2, 'duracao' => 50, 'substituir' => false]);

        $this->assertSame(4, TempoAula::where('turno_id', $tarde->id)->count(), 'sem "substituir", o turno com tempos não muda');
    }

    // --- Grade manual da turma ---------------------------------------------------------------------------------------

    public function test_grade_manual_avisa_da_indisponibilidade_mas_deixa_salvar(): void
    {
        $ana = $this->professor('Ana');
        $matematica = $this->disciplina('Matemática', 5, $ana);
        $this->janela($ana, 1, '07:30', '08:20', TipoDisponibilidadeProfessor::Indisponivel);

        Livewire::actingAs($this->como($this->usuario('super_admin')))
            ->test(GradeHorariosRelationManager::class, ['ownerRecord' => $this->turma, 'pageClass' => EditTurma::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'disciplina_id' => $matematica->id,
                'professor_id' => $ana->id,
                'dia_semana' => 1,
                'hora_inicio' => '07:30',
                'hora_fim' => '08:20',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Atenção com a disponibilidade');

        $this->assertSame(1, GradeHorario::where('turma_id', $this->turma->id)->count());
    }
}
