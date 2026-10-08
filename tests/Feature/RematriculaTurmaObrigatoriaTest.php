<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusRematricula;
use App\Exceptions\TurmaIndisponivelException;
use App\Filament\Resources\PeriodoRematriculas\Pages\CreatePeriodoRematricula;
use App\Filament\Resources\Rematriculas\Pages\ListRematriculas;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\RematriculaService;
use App\Services\TurmaVagasService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RematriculaTurmaObrigatoriaTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $origem;

    private PeriodoLetivo $destino;

    private PeriodoRematricula $campanha;

    private Serie $serie;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();

        // O .env local pode ter credenciais reais do Assinafy: nada pode sair para a API de verdade.
        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);
        $this->mock(AssinafyService::class, fn ($mock) => $mock->shouldReceive('enviarContrato')->andReturn(['success' => true]));

        $this->origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $unidade = Unidade::create(['nome' => 'Sede']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $this->serie = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);

        // Sem template de contrato: a rematrícula vira Confirmada ao efetivar (não depende de Assinafy).
        $this->campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'valor_taxa' => 0,
            'quantidade_parcelas_padrao' => 12,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'is_ativo' => true,
        ]);
    }

    private function turmaDestino(array $atributos = []): Turma
    {
        return Turma::create(array_merge([
            'nome' => '5º Ano A',
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $this->destino->id,
            'status' => 'planejada',
            'vagas_maximas' => 2,
        ], $atributos));
    }

    private function rematricula(string $nomeAluno = 'Aluno Teste', ?int $serieDestino = null): Rematricula
    {
        $aluno = Pessoa::create(['nome' => $nomeAluno]);
        $turmaOrigem = Turma::create(['nome' => '4º Ano '.$nomeAluno, 'periodo_letivo_id' => $this->origem->id]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaOrigem->id,
            'periodo_letivo_id' => $this->origem->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $rematricula = app(RematriculaService::class)->iniciarOuObter($matricula, $this->campanha, User::factory()->create());
        $rematricula->update([
            'status' => StatusRematricula::DadosConfirmados,
            'serie_destino_id' => $serieDestino,
        ]);

        return $rematricula;
    }

    private function ocupar(Turma $turma, int $quantidade, SituacaoMatricula $situacao = SituacaoMatricula::ATIVA): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            Matricula::create([
                'pessoa_id' => Pessoa::create(['nome' => "Ocupante {$turma->id}-{$i}"])->id,
                'turma_id' => $turma->id,
                'periodo_letivo_id' => $turma->periodo_letivo_id,
                'situacao' => $situacao,
            ]);
        }
    }

    // --- RematriculaService::efetivar() ---

    public function test_efetivar_exige_a_turma_de_destino(): void
    {
        $rematricula = $this->rematricula();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Escolha a turma de destino');

        app(RematriculaService::class)->efetivar($rematricula);
    }

    public function test_efetivar_cria_a_matricula_na_turma_escolhida_e_grava_a_turma_na_rematricula(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula();

        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);

        $this->assertSame($turma->id, $nova->turma_id);
        $this->assertSame($this->serie->id, $nova->serie_id);
        $this->assertSame($this->destino->id, $nova->periodo_letivo_id);
        $this->assertEquals(SituacaoMatricula::ATIVA, $nova->situacao);

        $rematricula->refresh();
        $this->assertSame($turma->id, $rematricula->turma_destino_id);
        $this->assertSame($this->serie->id, $rematricula->serie_destino_id);
        $this->assertSame($this->turno->id, $rematricula->turno_pretendido_id);
        $this->assertSame($nova->id, $rematricula->nova_matricula_id);
    }

    public function test_efetivar_usa_a_turma_ja_definida_na_rematricula_quando_nao_recebe_argumento(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula();
        $rematricula->update(['turma_destino_id' => $turma->id]);

        $nova = app(RematriculaService::class)->efetivar($rematricula);

        $this->assertSame($turma->id, $nova->turma_id);
    }

    public function test_efetivar_recusa_turma_lotada_e_nao_deixa_nada_pela_metade(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 1]);
        $this->ocupar($turma, 1);
        $rematricula = $this->rematricula();
        $matriculas = Matricula::count();

        try {
            app(RematriculaService::class)->efetivar($rematricula, $turma->id);
            $this->fail('Deveria recusar turma lotada.');
        } catch (TurmaIndisponivelException $e) {
            $this->assertStringContainsString('atingiu a lotação máxima de 1 vagas', $e->getMessage());
            $this->assertTrue($e->turmaSemVaga());
        }

        $this->assertSame($matriculas, Matricula::count());
        $this->assertNull($rematricula->fresh()->nova_matricula_id);
        $this->assertNull($rematricula->fresh()->turma_destino_id);
    }

    public function test_efetivar_recusa_turma_de_outro_periodo(): void
    {
        $turmaDe2026 = Turma::create(['nome' => '5º Ano 2026', 'serie_id' => $this->serie->id, 'periodo_letivo_id' => $this->origem->id]);
        $rematricula = $this->rematricula();

        $this->expectException(TurmaIndisponivelException::class);
        $this->expectExceptionMessage('não pertence ao período letivo de destino');

        app(RematriculaService::class)->efetivar($rematricula, $turmaDe2026->id);
    }

    public function test_efetivar_recusa_turma_de_outra_serie_que_a_familia_pediu(): void
    {
        $outraSerie = Serie::create(['nome' => '6º Ano', 'curso_id' => $this->serie->curso_id, 'sistema_avaliacao' => 'Nota']);
        $turmaOutraSerie = $this->turmaDestino(['serie_id' => $outraSerie->id]);
        $rematricula = $this->rematricula(serieDestino: $this->serie->id);

        $this->expectException(TurmaIndisponivelException::class);
        $this->expectExceptionMessage('não é da série pretendida');

        app(RematriculaService::class)->efetivar($rematricula, $turmaOutraSerie->id);
    }

    public function test_efetivar_recusa_turma_concluida_ou_cancelada(): void
    {
        $rematricula = $this->rematricula();
        $service = app(RematriculaService::class);

        foreach (['concluida', 'cancelada'] as $status) {
            $turma = $this->turmaDestino(['nome' => "Turma {$status}", 'status' => $status]);

            try {
                $service->efetivar($rematricula, $turma->id);
                $this->fail("Deveria recusar turma {$status}.");
            } catch (TurmaIndisponivelException $e) {
                $this->assertStringContainsString('não está aberta para matrículas', $e->getMessage());
            }
        }

        $this->assertNull($rematricula->fresh()->nova_matricula_id);
    }

    public function test_efetivar_segue_idempotente_e_nao_ocupa_duas_vagas(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 1]);
        $rematricula = $this->rematricula();
        $service = app(RematriculaService::class);

        $primeira = $service->efetivar($rematricula, $turma->id);
        $segunda = $service->efetivar($rematricula, $turma->id);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, app(TurmaVagasService::class)->ocupadas($turma));
    }

    // --- TurmaVagasService ---

    public function test_so_matriculas_ativa_pendente_e_reserva_ocupam_vaga(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 10]);
        $this->ocupar($turma, 1, SituacaoMatricula::ATIVA);
        $this->ocupar($turma, 1, SituacaoMatricula::PENDENTE);
        $this->ocupar($turma, 1, SituacaoMatricula::RESERVA);
        $this->ocupar($turma, 1, SituacaoMatricula::CANCELADA);
        $this->ocupar($turma, 1, SituacaoMatricula::TRANCADA);
        $this->ocupar($turma, 1, SituacaoMatricula::EVASAO);

        $vagas = app(TurmaVagasService::class);

        $this->assertSame(3, $vagas->ocupadas($turma));
        $this->assertSame(7, $vagas->disponiveis($turma));
        $this->assertFalse($vagas->estaLotada($turma));
    }

    public function test_matricula_ja_desativada_nao_ocupa_vaga(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 5]);
        $this->ocupar($turma, 2);
        Matricula::where('turma_id', $turma->id)->limit(1)->update(['data_desativacao' => now()->subDay()->toDateString()]);

        $this->assertSame(1, app(TurmaVagasService::class)->ocupadas($turma));
    }

    public function test_turma_sem_limite_nunca_lota(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 0]);
        $this->ocupar($turma, 3);
        $vagas = app(TurmaVagasService::class);

        $this->assertNull($vagas->limite($turma));
        $this->assertNull($vagas->disponiveis($turma));
        $this->assertFalse($vagas->estaLotada($turma));
        $this->assertStringContainsString('3 matriculados', $vagas->rotulo($turma));
    }

    public function test_rotulo_mostra_ocupacao_e_lotada(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 2]);
        $vagas = app(TurmaVagasService::class);
        $this->assertSame('5º Ano A — Manhã (0/2 vagas)', $vagas->rotulo($turma, 'Manhã'));

        $this->ocupar($turma, 2);
        $this->assertSame('5º Ano A — Manhã (2/2) — LOTADA', $vagas->rotulo($turma, 'Manhã'));
    }

    public function test_garantir_vaga_para_varios_alunos_informa_quantas_vagas_restam(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 3]);
        $this->ocupar($turma, 2);

        DB::transaction(function () use ($turma) {
            try {
                app(TurmaVagasService::class)->garantirVaga($turma, 2);
                $this->fail('Só resta 1 vaga.');
            } catch (TurmaIndisponivelException $e) {
                $this->assertStringContainsString('possui apenas 1 vaga(s) disponível(is) e você tentou matricular 2 aluno(s)', $e->getMessage());
            }

            $this->assertSame($turma->id, app(TurmaVagasService::class)->garantirVaga($turma, 1)->id);
        });
    }

    public function test_garantir_vaga_fora_de_transacao_e_um_erro_de_programacao(): void
    {
        $turma = $this->turmaDestino();

        // O RefreshDatabase mantém uma transação aberta; fecha-a só para provar a guarda.
        $nivel = DB::transactionLevel();
        for ($i = 0; $i < $nivel; $i++) {
            DB::rollBack();
        }

        try {
            $this->expectException(\LogicException::class);
            app(TurmaVagasService::class)->garantirVaga($turma);
        } finally {
            DB::beginTransaction();
        }
    }

    // --- Ações da secretaria ---

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        return $admin;
    }

    public function test_turma_unica_com_vaga_e_sugerida_e_varias_nao(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula(serieDestino: $this->serie->id);
        $service = app(RematriculaService::class);

        $this->assertSame($turma->id, $service->turmaSugerida($rematricula, $this->campanha));

        $this->turmaDestino(['nome' => '5º Ano B']);
        $this->assertNull($service->turmaSugerida($rematricula, $this->campanha), 'Com duas turmas a secretaria decide.');

        $rematricula->update(['turma_destino_id' => $turma->id]);
        $this->assertSame($turma->id, $service->turmaSugerida($rematricula->fresh(), $this->campanha), 'Turma já definida prevalece.');
    }

    public function test_acao_efetivar_da_secretaria_exige_turma_e_cria_a_matricula(): void
    {
        $turma = $this->turmaDestino();
        $this->turmaDestino(['nome' => '5º Ano B']); // duas candidatas: nenhuma é pré-selecionada
        $rematricula = $this->rematricula();

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableAction('efetivar', $rematricula, data: [])
            ->assertHasTableActionErrors(['turma_id' => 'required']);

        $this->assertNull($rematricula->fresh()->nova_matricula_id);

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableAction('efetivar', $rematricula, data: ['turma_id' => $turma->id])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Rematrícula Efetivada');

        $this->assertSame($turma->id, $rematricula->fresh()->novaMatricula->turma_id);
    }

    public function test_acao_efetivar_nao_aceita_turma_de_outro_periodo_nem_lotada(): void
    {
        $lotada = $this->turmaDestino(['nome' => 'Lotada', 'vagas_maximas' => 1]);
        $this->ocupar($lotada, 1);
        $deOutroPeriodo = Turma::create(['nome' => 'Turma 2026', 'serie_id' => $this->serie->id, 'periodo_letivo_id' => $this->origem->id]);
        $rematricula = $this->rematricula();

        foreach ([$lotada->id, $deOutroPeriodo->id] as $turmaId) {
            Livewire::actingAs($this->admin())
                ->test(ListRematriculas::class)
                ->callTableAction('efetivar', $rematricula, data: ['turma_id' => $turmaId])
                ->assertHasTableActionErrors(['turma_id']);
        }

        $this->assertNull($rematricula->fresh()->nova_matricula_id);
    }

    public function test_acao_em_lote_efetiva_na_mesma_turma_e_para_quando_ela_lota(): void
    {
        $turma = $this->turmaDestino(['vagas_maximas' => 2]);
        $r1 = $this->rematricula('Aluno 1');
        $r2 = $this->rematricula('Aluno 2');
        $r3 = $this->rematricula('Aluno 3');

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableBulkAction('efetivarEmLote', [$r1, $r2, $r3], data: ['turma_id' => $turma->id])
            ->assertNotified();

        $this->assertSame(2, Rematricula::whereNotNull('nova_matricula_id')->count());
        $this->assertSame(1, Rematricula::whereNull('nova_matricula_id')->count(), 'A terceira continua pendente: a turma lotou.');
        $this->assertSame(2, app(TurmaVagasService::class)->ocupadas($turma));
    }

    // --- Campanha ---

    public function test_campanha_nao_aceita_destino_igual_a_origem(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePeriodoRematricula::class)
            ->fillForm([
                'nome' => 'Campanha inválida',
                'periodo_letivo_origem_id' => $this->origem->id,
                'periodo_letivo_destino_id' => $this->origem->id,
                'data_inicio' => '2026-10-01',
                'data_fim' => '2026-10-30',
                'quantidade_parcelas_padrao' => 12,
                'is_ativo' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['periodo_letivo_destino_id']);
    }

    public function test_campanha_ativa_exige_turma_aberta_no_periodo_de_destino(): void
    {
        $dados = [
            'nome' => 'Rematrícula 2028',
            'periodo_letivo_origem_id' => $this->destino->id,
            'periodo_letivo_destino_id' => PeriodoLetivo::create(['nome' => '2028', 'data_inicio' => '2028-02-01', 'data_fim' => '2028-12-15'])->id,
            'data_inicio' => '2026-10-01',
            'data_fim' => '2026-10-30',
            'quantidade_parcelas_padrao' => 12,
            'is_ativo' => true,
        ];

        // Sem turmas em 2028: não pode ativar.
        Livewire::actingAs($this->admin())
            ->test(CreatePeriodoRematricula::class)
            ->fillForm($dados)
            ->call('create')
            ->assertHasFormErrors(['is_ativo']);

        // Com uma turma Planejada em 2028: pode.
        Turma::create(['nome' => '1º Ano', 'serie_id' => $this->serie->id, 'periodo_letivo_id' => $dados['periodo_letivo_destino_id'], 'status' => 'planejada']);

        Livewire::actingAs($this->admin())
            ->test(CreatePeriodoRematricula::class)
            ->fillForm($dados)
            ->call('create')
            ->assertHasNoFormErrors();
    }

    // --- Migration ---

    public function test_indice_unico_impede_duas_rematriculas_da_mesma_matricula_na_campanha(): void
    {
        $rematricula = $this->rematricula();

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('rematriculas')->insert([
            'periodo_rematricula_id' => $rematricula->periodo_rematricula_id,
            'matricula_origem_id' => $rematricula->matricula_origem_id,
            'solicitante_user_id' => $rematricula->solicitante_user_id,
            'status' => 'iniciada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
