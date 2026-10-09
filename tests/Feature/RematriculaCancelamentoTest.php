<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusFatura;
use App\Enums\StatusRematricula;
use App\Filament\Resources\Rematriculas\Pages\EditRematricula;
use App\Filament\Resources\Rematriculas\Pages\ListRematriculas;
use App\Filament\Resources\Rematriculas\RematriculaResource;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\RematriculaService;
use App\Services\TurmaVagasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cancelamento da rematrícula (libera vaga e cancela faturas em aberto), exclusão protegida e
 * situação da nova matrícula (Pendente até o contrato ser assinado).
 */
class RematriculaCancelamentoTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $origem;

    private PeriodoLetivo $destino;

    private Serie $serie;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();

        // O .env local pode ter credenciais reais do Assinafy: o envio falha de forma controlada e nada sai para a API.
        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);

        $this->origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $unidade = Unidade::create(['nome' => 'Sede']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $this->serie = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);
    }

    private function campanha(bool $comContrato = true): PeriodoRematricula
    {
        return PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'template_contrato_id' => $comContrato
                ? TemplateContrato::create(['nome' => 'Contrato 2027', 'conteudo' => '<p>Termos.</p>', 'is_padrao' => true])->id
                : null,
            'valor_taxa' => 1200,
            'quantidade_parcelas_padrao' => 4,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'is_ativo' => true,
        ]);
    }

    private function turmaDestino(int $vagas = 5): Turma
    {
        return Turma::create([
            'nome' => '5º Ano A',
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $this->destino->id,
            'status' => 'planejada',
            'vagas_maximas' => $vagas,
        ]);
    }

    private function rematricula(PeriodoRematricula $campanha, string $aluno = 'Aluno Teste'): Rematricula
    {
        $pessoa = Pessoa::create(['nome' => $aluno]);
        $turmaOrigem = Turma::create(['nome' => '4º Ano '.$aluno, 'periodo_letivo_id' => $this->origem->id]);
        $matricula = Matricula::create(['pessoa_id' => $pessoa->id, 'turma_id' => $turmaOrigem->id, 'situacao' => SituacaoMatricula::ATIVA]);

        $rematricula = app(RematriculaService::class)->iniciarOuObter($matricula, $campanha, User::factory()->create());
        $rematricula->update(['status' => StatusRematricula::DadosConfirmados]);

        return $rematricula;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        return $admin;
    }

    // --- Pendência 3: situação da nova matrícula ---

    public function test_com_contrato_a_matricula_nasce_pendente_e_ja_reserva_a_vaga(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula($this->campanha());

        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);

        $this->assertEquals(SituacaoMatricula::PENDENTE, $nova->situacao);
        $this->assertSame(1, app(TurmaVagasService::class)->ocupadas($turma), 'Pendente ocupa vaga.');
    }

    public function test_sem_contrato_a_matricula_ja_nasce_ativa(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula($this->campanha(comContrato: false));

        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);

        $this->assertEquals(SituacaoMatricula::ATIVA, $nova->situacao);
        $this->assertEquals(StatusRematricula::Confirmada, $rematricula->fresh()->status);
    }

    public function test_assinatura_do_contrato_confirma_a_rematricula_e_ativa_a_matricula(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula($this->campanha());
        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);

        $rematricula->refresh()->contrato->update(['assinafy_id' => 'DOC-ATIVA-1']);
        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-ATIVA-1']]);

        $this->assertEquals(StatusRematricula::Confirmada, $rematricula->fresh()->status);
        $this->assertEquals(SituacaoMatricula::ATIVA, $nova->fresh()->situacao);
    }

    // --- Pendência 2: cancelamento ---

    public function test_cancelar_libera_a_vaga_e_cancela_so_as_faturas_em_aberto(): void
    {
        $turma = $this->turmaDestino(vagas: 1);
        $rematricula = $this->rematricula($this->campanha());
        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);
        $vagas = app(TurmaVagasService::class);
        $this->assertTrue($vagas->estaLotada($turma), 'A única vaga está reservada pela matrícula pendente.');

        $faturas = $rematricula->fresh()->contrato->faturas()->orderBy('vencimento')->get();
        $this->assertCount(4, $faturas);
        $faturas[0]->update(['status' => StatusFatura::Pago]);
        $faturas[1]->update(['status' => StatusFatura::Parcial]);
        $faturas[2]->update(['status' => StatusFatura::Atrasado]);

        $resumo = app(RematriculaService::class)->cancelar($rematricula, 'Família desistiu');

        $this->assertSame(['ja_cancelada' => false, 'matricula_cancelada' => true, 'faturas_canceladas' => 2, 'faturas_pagas' => 2], $resumo);

        $rematricula->refresh();
        $this->assertEquals(StatusRematricula::Cancelada, $rematricula->status);
        $this->assertStringContainsString('Família desistiu', (string) $rematricula->observacoes);
        $this->assertSame($nova->id, $rematricula->nova_matricula_id, 'O vínculo é mantido para histórico.');

        $nova->refresh();
        $this->assertEquals(SituacaoMatricula::CANCELADA, $nova->situacao);
        $this->assertSame(today()->toDateString(), $nova->data_desativacao->toDateString());
        $this->assertFalse($vagas->estaLotada($turma), 'A vaga foi liberada.');

        $status = $rematricula->contrato->faturas()->orderBy('vencimento')->pluck('status')->all();
        $this->assertEquals([StatusFatura::Pago, StatusFatura::Parcial, StatusFatura::Cancelado, StatusFatura::Cancelado], $status);
    }

    public function test_cancelar_e_idempotente(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula($this->campanha());
        app(RematriculaService::class)->efetivar($rematricula, $turma->id);
        $service = app(RematriculaService::class);

        $service->cancelar($rematricula);
        $segunda = $service->cancelar($rematricula);

        $this->assertTrue($segunda['ja_cancelada']);
        $this->assertFalse($segunda['matricula_cancelada']);
    }

    public function test_cancelar_rematricula_que_nao_foi_efetivada_so_muda_o_status(): void
    {
        $rematricula = $this->rematricula($this->campanha());

        $resumo = app(RematriculaService::class)->cancelar($rematricula);

        $this->assertFalse($resumo['matricula_cancelada']);
        $this->assertSame(0, $resumo['faturas_canceladas']);
        $this->assertEquals(StatusRematricula::Cancelada, $rematricula->fresh()->status);
        $this->assertSame(1, Matricula::count(), 'Nenhuma matrícula nova foi criada nem alterada.');
    }

    public function test_marcar_como_cancelada_pelo_formulario_de_edicao_tambem_libera_a_vaga(): void
    {
        $turma = $this->turmaDestino(vagas: 1);
        $rematricula = $this->rematricula($this->campanha());
        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);

        Livewire::actingAs($this->admin())
            ->test(EditRematricula::class, ['record' => $rematricula->getKey()])
            ->fillForm(['status' => StatusRematricula::Cancelada->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(SituacaoMatricula::CANCELADA, $nova->fresh()->situacao);
        $this->assertFalse(app(TurmaVagasService::class)->estaLotada($turma));
        $this->assertSame(0, $rematricula->fresh()->contrato->faturas()->where('status', StatusFatura::Pendente->value)->count());
    }

    public function test_rematricula_cancelada_nao_pode_ser_efetivada(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula($this->campanha());
        app(RematriculaService::class)->cancelar($rematricula);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('foi cancelada');

        app(RematriculaService::class)->efetivar($rematricula, $turma->id);
    }

    public function test_assinatura_tardia_nao_reativa_rematricula_cancelada(): void
    {
        $turma = $this->turmaDestino();
        $rematricula = $this->rematricula($this->campanha());
        $nova = app(RematriculaService::class)->efetivar($rematricula, $turma->id);
        $rematricula->refresh()->contrato->update(['assinafy_id' => 'DOC-TARDIA-1']);

        app(RematriculaService::class)->cancelar($rematricula);

        // O documento continua no Assinafy e a família assina depois do cancelamento
        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-TARDIA-1']]);

        $this->assertEquals(StatusRematricula::Cancelada, $rematricula->fresh()->status);
        $this->assertEquals(SituacaoMatricula::CANCELADA, $nova->fresh()->situacao);
    }

    // --- Exclusão protegida ---

    public function test_rematricula_efetivada_nao_pode_ser_excluida_ate_ser_cancelada(): void
    {
        $turma = $this->turmaDestino();
        $efetivada = $this->rematricula($this->campanha(), 'Aluno Efetivado');
        app(RematriculaService::class)->efetivar($efetivada, $turma->id);

        $this->assertFalse($efetivada->fresh()->podeSerExcluida());
        $this->assertFalse($efetivada->fresh()->delete(), 'O evento deleting cancela a exclusão.');
        $this->assertNotNull(Rematricula::find($efetivada->id));

        app(RematriculaService::class)->cancelar($efetivada);

        $this->assertTrue($efetivada->fresh()->podeSerExcluida());
        $this->assertTrue($efetivada->fresh()->delete());
        $this->assertNull(Rematricula::find($efetivada->id));
    }

    public function test_rematricula_nunca_efetivada_pode_ser_excluida(): void
    {
        $rematricula = $this->rematricula($this->campanha());

        $this->assertTrue($rematricula->podeSerExcluida());
        $this->assertTrue($rematricula->delete());
    }

    public function test_tabela_esconde_excluir_da_efetivada_e_oferece_cancelar(): void
    {
        $turma = $this->turmaDestino();
        $campanha = $this->campanha();
        $efetivada = $this->rematricula($campanha, 'Aluno Efetivado');
        app(RematriculaService::class)->efetivar($efetivada, $turma->id);
        $pendente = $this->rematricula($campanha, 'Aluno Pendente');

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->assertTableActionHidden('delete', $efetivada)
            ->assertTableActionVisible('delete', $pendente)
            ->assertTableActionVisible('cancelar', $efetivada)
            ->callTableAction('cancelar', $efetivada, data: ['motivo' => 'Mudou de cidade'])
            ->assertNotified('Rematrícula cancelada')
            ->assertTableActionHidden('cancelar', $efetivada)
            ->assertTableActionHidden('efetivar', $efetivada)
            ->assertTableActionVisible('delete', $efetivada);

        $this->assertEquals(StatusRematricula::Cancelada, $efetivada->fresh()->status);
    }

    public function test_acao_cancelar_exige_a_permissao_de_atualizar_rematricula(): void
    {
        Permission::findOrCreate('ViewAny:Rematricula', 'web');
        $semPermissao = User::factory()->create(['activated_at' => now()]);
        $semPermissao->givePermissionTo('ViewAny:Rematricula');
        $rematricula = $this->rematricula($this->campanha());

        Livewire::actingAs($semPermissao)
            ->test(ListRematriculas::class)
            ->assertTableActionHidden('cancelar', $rematricula)
            ->assertTableActionHidden('efetivar', $rematricula);
    }

    // --- Pendência 4: contador e filtro ---

    public function test_contador_do_menu_e_o_filtro_mostram_as_rematriculas_aguardando_turma(): void
    {
        $turma = $this->turmaDestino();
        $campanha = $this->campanha();

        $this->assertNull(RematriculaResource::getNavigationBadge(), 'Sem pendências o contador some.');

        $aguardando1 = $this->rematricula($campanha, 'Aluno 1');
        $aguardando2 = $this->rematricula($campanha, 'Aluno 2');
        $efetivada = $this->rematricula($campanha, 'Aluno 3');
        app(RematriculaService::class)->efetivar($efetivada, $turma->id);
        $iniciada = $this->rematricula($campanha, 'Aluno 4');
        $iniciada->update(['status' => StatusRematricula::Iniciada]);

        $this->assertSame('2', RematriculaResource::getNavigationBadge());

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->filterTable('aguardando_turma')
            ->assertCanSeeTableRecords([$aguardando1, $aguardando2])
            ->assertCanNotSeeTableRecords([$efetivada, $iniciada]);

        app(RematriculaService::class)->efetivar($aguardando1, $turma->id);
        $this->assertSame('1', RematriculaResource::getNavigationBadge());
    }
}
