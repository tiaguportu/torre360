<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusAtestadoAluno;
use App\Filament\Resources\AtestadosAluno\Pages\CreateAtestadoAluno;
use App\Filament\Resources\AtestadosAluno\Pages\ListAtestadosAluno;
use App\Models\AtestadoAluno;
use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Services\AtestadoAlunoService;
use App\Services\ResumoFaltasDiarioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Atestado do aluno e abono das faltas do período.
 *
 * "Hoje" é quarta-feira, 21/10/2026. O cenário padrão tem aulas de segunda (19/10) a sexta (23/10) e um atestado de 20 a 22/10.
 */
class AtestadoAlunoTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    private Matricula $matricula;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        Storage::fake('local');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Cancelar'] as $acao) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:AtestadoAluno", 'guard_name' => 'web']));
        }

        foreach (['ViewAny', 'View'] as $acao) {
            Role::findByName('coordenador')->givePermissionTo(Permission::findByName("{$acao}:AtestadoAluno"));
        }

        $this->turma = Turma::factory()->create();
        $this->matricula = $this->novaMatricula('Ana Lima');
        $this->secretaria = $this->usuario('secretaria');
    }

    private function servico(): AtestadoAlunoService
    {
        return app(AtestadoAlunoService::class);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /**
     * As permissões valem pelo papel ativo da sessão, que é global no teste: quem age precisa ser declarado antes de cada
     * chamada, senão vale o papel do último usuário criado.
     */
    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function novaMatricula(string $nome): Matricula
    {
        return Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => $nome])->id,
            'turma_id' => $this->turma->id,
        ]);
    }

    private function aula(string $data): CronogramaAula
    {
        return CronogramaAula::factory()->create(['turma_id' => $this->turma->id, 'data' => $data]);
    }

    private function lancar(Matricula $matricula, string $data, string $situacao = 'ausente', array $extra = []): FrequenciaEscolar
    {
        return FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $this->aula($data)->id,
            'situacao' => $situacao,
        ] + $extra);
    }

    private function pdf(string $nome = 'atestado.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n%%EOF");
    }

    private function registrar(string $inicio = '2026-10-20', string $fim = '2026-10-22', ?Matricula $matricula = null, ?string $observacao = null): AtestadoAluno
    {
        return $this->servico()->registrar($matricula ?? $this->matricula, Carbon::parse($inicio), Carbon::parse($fim), $this->pdf(), $observacao, $this->secretaria);
    }

    // --- Abono das faltas já lançadas ----------------------------------------------------------------------------------

    public function test_registrar_abona_as_faltas_do_periodo_e_so_elas(): void
    {
        $antes = $this->lancar($this->matricula, '2026-10-19');
        $dentro1 = $this->lancar($this->matricula, '2026-10-20');
        $dentro2 = $this->lancar($this->matricula, '2026-10-22');
        $presente = $this->lancar($this->matricula, '2026-10-21', 'presente');
        $depois = $this->lancar($this->matricula, '2026-10-23');
        $outroAluno = $this->lancar($this->novaMatricula('Bia Souza'), '2026-10-21');

        $atestado = $this->registrar();

        foreach ([$dentro1, $dentro2] as $falta) {
            $falta = $falta->fresh();
            $this->assertTrue($falta->justificada);
            $this->assertSame('Atestado médico (20/10/2026 a 22/10/2026)', $falta->motivo_justificativa);
            $this->assertSame($atestado->id, $falta->atestado_aluno_id);
        }

        foreach ([$antes, $presente, $depois, $outroAluno] as $intacta) {
            $intacta = $intacta->fresh();
            $this->assertFalse($intacta->justificada);
            $this->assertNull($intacta->atestado_aluno_id);
        }

        $this->assertSame(2, $atestado->frequencias()->count());
    }

    public function test_atestado_de_um_dia_so_mostra_a_data_uma_vez(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');

        $this->registrar('2026-10-21', '2026-10-21');

        $this->assertSame('Atestado médico (21/10/2026)', $falta->fresh()->motivo_justificativa);
    }

    public function test_falta_justificada_a_mao_nao_e_sobrescrita_nem_desfeita_ao_cancelar(): void
    {
        $manual = $this->lancar($this->matricula, '2026-10-20', 'ausente', ['justificada' => true, 'motivo_justificativa' => 'Consulta marcada']);
        $abonada = $this->lancar($this->matricula, '2026-10-21');

        $atestado = $this->registrar();

        $this->assertSame('Consulta marcada', $manual->fresh()->motivo_justificativa);
        $this->assertNull($manual->fresh()->atestado_aluno_id);
        $this->assertTrue($abonada->fresh()->justificada);

        $this->servico()->cancelar($atestado, 'Lançado por engano', $this->secretaria);

        $this->assertTrue($manual->fresh()->justificada, 'a justificativa manual continua');
        $this->assertSame('Consulta marcada', $manual->fresh()->motivo_justificativa);
        $this->assertFalse($abonada->fresh()->justificada);
        $this->assertNull($abonada->fresh()->motivo_justificativa);
        $this->assertNull($abonada->fresh()->atestado_aluno_id);
    }

    // --- Faltas lançadas depois do atestado -----------------------------------------------------------------------------

    public function test_falta_lancada_depois_do_atestado_nasce_justificada(): void
    {
        $atestado = $this->registrar('2026-10-21', '2026-10-23');

        $dentro = $this->lancar($this->matricula, '2026-10-22');
        $fora = $this->lancar($this->matricula, '2026-10-26');
        $outroAluno = $this->lancar($this->novaMatricula('Bia Souza'), '2026-10-22');
        $presenca = $this->lancar($this->matricula, '2026-10-23', 'presente');

        $this->assertTrue($dentro->fresh()->justificada);
        $this->assertSame($atestado->id, $dentro->fresh()->atestado_aluno_id);
        $this->assertSame('Atestado médico (21/10/2026 a 23/10/2026)', $dentro->fresh()->motivo_justificativa);

        foreach ([$fora, $outroAluno, $presenca] as $falta) {
            $this->assertFalse($falta->fresh()->justificada);
        }
    }

    public function test_presenca_corrigida_para_falta_dentro_do_periodo_e_abonada(): void
    {
        $this->registrar();
        $frequencia = $this->lancar($this->matricula, '2026-10-21', 'presente');

        $frequencia->update(['situacao' => 'ausente']);

        $this->assertTrue($frequencia->fresh()->justificada);
    }

    public function test_falta_abonada_corrigida_para_presenca_perde_o_abono(): void
    {
        $atestado = $this->registrar();
        $frequencia = $this->lancar($this->matricula, '2026-10-21');
        $this->assertTrue($frequencia->fresh()->justificada);

        $frequencia->update(['situacao' => 'presente']);

        $this->assertFalse($frequencia->fresh()->justificada);
        $this->assertNull($frequencia->fresh()->atestado_aluno_id);
        $this->assertNull($frequencia->fresh()->motivo_justificativa);

        // Se voltar a ser falta com o atestado ainda vigente, é abonada de novo.
        $frequencia->update(['situacao' => 'ausente']);
        $this->assertSame($atestado->id, $frequencia->fresh()->atestado_aluno_id);
    }

    public function test_atestado_cancelado_nao_abona_faltas_novas(): void
    {
        $atestado = $this->registrar();
        $this->servico()->cancelar($atestado, 'Sem validade', $this->secretaria);

        $falta = $this->lancar($this->matricula, '2026-10-21');

        $this->assertFalse($falta->fresh()->justificada);
    }

    public function test_falta_abonada_sai_do_resumo_diario_para_a_familia(): void
    {
        $abonada = $this->lancar($this->matricula, '2026-10-21');
        $comum = $this->lancar($this->matricula, '2026-10-19');

        $this->registrar('2026-10-21', '2026-10-21');

        $pendentes = app(ResumoFaltasDiarioService::class)->pendentes()->pluck('id');

        $this->assertTrue($pendentes->contains($comum->id));
        $this->assertFalse($pendentes->contains($abonada->id));
    }

    // --- Cancelar e corrigir --------------------------------------------------------------------------------------------

    public function test_cancelar_exige_motivo_e_so_vale_uma_vez(): void
    {
        $atestado = $this->registrar();

        try {
            $this->servico()->cancelar($atestado, '   ', $this->secretaria);
            $this->fail('Cancelar sem motivo deveria falhar.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $this->assertTrue($atestado->fresh()->estaVigente());

        $this->servico()->cancelar($atestado, 'Atestado de outro aluno', $this->secretaria);

        $atestado = $atestado->fresh();
        $this->assertSame(StatusAtestadoAluno::Cancelado, $atestado->status);
        $this->assertSame('Atestado de outro aluno', $atestado->motivo_cancelamento);
        $this->assertSame($this->secretaria->id, $atestado->cancelado_por_user_id);
        $this->assertNotNull($atestado->cancelado_em);
        $this->assertTrue($atestado->temComprovante(), 'o comprovante fica guardado para o histórico');

        $this->expectException(\DomainException::class);
        $this->servico()->cancelar($atestado, 'De novo', $this->secretaria);
    }

    public function test_corrigir_periodo_devolve_as_faltas_de_fora_e_abona_as_novas(): void
    {
        $dia19 = $this->lancar($this->matricula, '2026-10-19');
        $dia20 = $this->lancar($this->matricula, '2026-10-20');
        $dia22 = $this->lancar($this->matricula, '2026-10-22');
        $dia23 = $this->lancar($this->matricula, '2026-10-23');
        $atestado = $this->registrar('2026-10-20', '2026-10-22');

        $abonadas = $this->servico()->corrigirPeriodo($atestado, Carbon::parse('2026-10-21'), Carbon::parse('2026-10-23'), $this->secretaria);

        $this->assertSame(1, $abonadas, 'só o dia 23 é novo');
        $this->assertFalse($dia19->fresh()->justificada);
        $this->assertFalse($dia20->fresh()->justificada, 'saiu do período');
        $this->assertNull($dia20->fresh()->atestado_aluno_id);
        $this->assertTrue($dia22->fresh()->justificada);
        $this->assertTrue($dia23->fresh()->justificada);
        $this->assertSame('Atestado médico (21/10/2026 a 23/10/2026)', $dia22->fresh()->motivo_justificativa);
        $this->assertSame('2026-10-21', $atestado->fresh()->data_inicio->toDateString());
    }

    public function test_corrigir_atestado_cancelado_e_recusado(): void
    {
        $atestado = $this->registrar();
        $this->servico()->cancelar($atestado, 'Engano', $this->secretaria);

        $this->expectException(\DomainException::class);
        $this->servico()->corrigirPeriodo($atestado->fresh(), Carbon::parse('2026-10-20'), Carbon::parse('2026-10-21'));
    }

    public function test_cancelar_um_atestado_mantem_o_abono_dado_por_outro_que_cobre_o_dia(): void
    {
        $dia21 = $this->lancar($this->matricula, '2026-10-21');
        $dia20 = $this->lancar($this->matricula, '2026-10-20');

        $primeiro = $this->registrar('2026-10-20', '2026-10-21');
        $segundo = $this->registrar('2026-10-21', '2026-10-22');

        $this->assertSame($primeiro->id, $dia21->fresh()->atestado_aluno_id, 'o mais antigo abona primeiro');

        $this->servico()->cancelar($primeiro, 'Duplicado', $this->secretaria);

        $this->assertTrue($dia21->fresh()->justificada, 'o segundo atestado também cobre o dia 21');
        $this->assertSame($segundo->id, $dia21->fresh()->atestado_aluno_id);
        $this->assertFalse($dia20->fresh()->justificada, 'o dia 20 só era coberto pelo primeiro');
    }

    // --- Justificar não é abonar ----------------------------------------------------------------------------------------

    public function test_por_padrao_o_atestado_so_justifica_e_a_falta_continua_contando(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');

        $atestado = $this->registrar();

        $this->assertFalse($atestado->abona_faltas);
        $this->assertTrue($falta->fresh()->justificada);
        $this->assertFalse($falta->fresh()->abonada);
        $this->assertSame(1, FrequenciaEscolar::query()->contaComoFalta()->count(), 'justificada sem abono ainda é falta');
    }

    public function test_atestado_registrado_como_abono_justifica_e_abona_as_faltas(): void
    {
        $existente = $this->lancar($this->matricula, '2026-10-21');
        $fora = $this->lancar($this->matricula, '2026-10-26');

        $atestado = $this->servico()->registrar($this->matricula, Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->pdf(), null, $this->secretaria, abonaFaltas: true);

        $this->assertTrue($atestado->fresh()->abona_faltas);
        $this->assertTrue($existente->fresh()->justificada);
        $this->assertTrue($existente->fresh()->abonada);
        $this->assertFalse($fora->fresh()->abonada);
        $this->assertSame(1, FrequenciaEscolar::query()->contaComoFalta()->count(), 'só a de fora do período conta');

        // Falta lançada depois, dentro do período, já nasce justificada e abonada.
        $depois = $this->lancar($this->matricula, '2026-10-22');
        $this->assertTrue($depois->fresh()->justificada);
        $this->assertTrue($depois->fresh()->abonada);
    }

    public function test_abono_nao_sobrescreve_falta_justificada_a_mao_sem_abono(): void
    {
        $manual = $this->lancar($this->matricula, '2026-10-21', 'ausente', ['justificada' => true, 'motivo_justificativa' => 'Consulta marcada']);

        $this->servico()->registrar($this->matricula, Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->pdf(), null, $this->secretaria, abonaFaltas: true);

        $this->assertTrue($manual->fresh()->justificada);
        $this->assertFalse($manual->fresh()->abonada, 'a justificativa manual não é promovida a abono pelo atestado');
        $this->assertNull($manual->fresh()->atestado_aluno_id);
    }

    public function test_cancelar_atestado_com_abono_devolve_a_falta_para_a_conta(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');
        $atestado = $this->servico()->registrar($this->matricula, Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->pdf(), null, $this->secretaria, abonaFaltas: true);
        $this->assertSame(0, FrequenciaEscolar::query()->contaComoFalta()->count());

        $this->servico()->cancelar($atestado, 'Engano', $this->secretaria);

        $this->assertFalse($falta->fresh()->justificada);
        $this->assertFalse($falta->fresh()->abonada);
        $this->assertSame(1, FrequenciaEscolar::query()->contaComoFalta()->count());
    }

    public function test_corrigir_o_abono_vale_para_todas_as_faltas_do_atestado(): void
    {
        $f1 = $this->lancar($this->matricula, '2026-10-20');
        $f2 = $this->lancar($this->matricula, '2026-10-21');
        $atestado = $this->registrar('2026-10-20', '2026-10-22');
        $this->assertFalse($f1->fresh()->abonada);

        $this->servico()->corrigirPeriodo($atestado, Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->secretaria, abonaFaltas: true);

        $this->assertTrue($atestado->fresh()->abona_faltas);
        $this->assertTrue($f1->fresh()->abonada);
        $this->assertTrue($f2->fresh()->abonada);
        $this->assertSame(0, FrequenciaEscolar::query()->contaComoFalta()->count());

        // Desmarcar devolve as faltas a "só justificadas": voltam a contar, mas seguem justificadas.
        $this->servico()->corrigirPeriodo($atestado->fresh(), Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->secretaria, abonaFaltas: false);

        $this->assertFalse($f1->fresh()->abonada);
        $this->assertTrue($f1->fresh()->justificada);
        $this->assertSame(2, FrequenciaEscolar::query()->contaComoFalta()->count());

        // Sem informar o abono, ele não muda.
        $this->servico()->corrigirPeriodo($atestado->fresh(), Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->secretaria);
        $this->assertFalse($atestado->fresh()->abona_faltas);
    }

    public function test_falta_abonada_corrigida_para_presenca_perde_justificativa_e_abono(): void
    {
        $this->servico()->registrar($this->matricula, Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $this->pdf(), null, $this->secretaria, abonaFaltas: true);
        $frequencia = $this->lancar($this->matricula, '2026-10-21');
        $this->assertTrue($frequencia->fresh()->abonada);

        $frequencia->update(['situacao' => 'presente']);

        $this->assertFalse($frequencia->fresh()->justificada);
        $this->assertFalse($frequencia->fresh()->abonada);
    }

    public function test_tela_registra_o_abono_e_a_lista_mostra_a_escolha(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateAtestadoAluno::class)
            ->fillForm([
                'matricula_id' => $this->matricula->id,
                'data_inicio' => '2026-10-20',
                'data_fim' => '2026-10-22',
                'comprovante' => $this->pdf(),
                'abona_faltas' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $atestado = AtestadoAluno::firstOrFail();
        $this->assertTrue($atestado->abona_faltas);
        $this->assertTrue($falta->fresh()->abonada);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListAtestadosAluno::class)
            ->assertCanSeeTableRecords([$atestado])
            ->filterTable('abona_faltas', true)
            ->assertCanSeeTableRecords([$atestado])
            ->filterTable('abona_faltas', false)
            ->assertCanNotSeeTableRecords([$atestado]);
    }

    public function test_corrigir_pela_tela_muda_o_abono(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');
        $atestado = $this->registrar('2026-10-21', '2026-10-21');

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListAtestadosAluno::class)
            ->callTableAction('corrigir_periodo', $atestado, data: ['data_inicio' => '2026-10-21', 'data_fim' => '2026-10-21', 'abona_faltas' => true])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($atestado->fresh()->abona_faltas);
        $this->assertTrue($falta->fresh()->abonada);
    }

    // --- Validações -----------------------------------------------------------------------------------------------------

    public function test_periodo_invalido_e_recusado_sem_guardar_nada(): void
    {
        foreach ([
            ['2026-10-22', '2026-10-20', 'anterior'],
            ['2026-10-01', '2027-10-01', '180 dias'],
        ] as [$inicio, $fim, $trecho]) {
            try {
                $this->registrar($inicio, $fim);
                $this->fail('Período inválido deveria falhar.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->assertSame(0, AtestadoAluno::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_periodo_de_180_dias_e_aceito(): void
    {
        $atestado = $this->registrar('2026-08-01', '2027-01-27');

        $this->assertEquals(AtestadoAlunoService::PERIODO_MAXIMO_DIAS, Carbon::parse('2026-08-01')->diffInDays(Carbon::parse('2027-01-27')) + 1);
        $this->assertTrue($atestado->exists);
    }

    public function test_arquivo_que_nao_e_pdf_nem_foto_e_recusado(): void
    {
        $html = UploadedFile::fake()->createWithContent('atestado.pdf', '<html><script>alert(1)</script></html>');

        try {
            $this->servico()->registrar($this->matricula, Carbon::parse('2026-10-20'), Carbon::parse('2026-10-22'), $html, null, $this->secretaria);
            $this->fail('HTML disfarçado de PDF deveria ser recusado.');
        } catch (\DomainException) {
            // esperado
        }

        $this->assertSame(0, AtestadoAluno::count());
    }

    public function test_mesmo_periodo_vigente_nao_se_repete(): void
    {
        $this->registrar();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('mesmo período');
        $this->registrar();
    }

    // --- Privacidade ----------------------------------------------------------------------------------------------------

    public function test_comprovante_fica_em_pasta_privada_e_a_observacao_vai_criptografada(): void
    {
        $atestado = $this->registrar('2026-10-20', '2026-10-22', null, 'Cirurgia de amígdalas');

        $this->assertStringStartsWith("atestados_aluno/{$this->matricula->id}/atestado-", $atestado->arquivo_path);
        $this->assertStringEndsWith('.pdf', $atestado->arquivo_path);
        Storage::disk('local')->assertExists($atestado->arquivo_path);

        $this->assertSame('Cirurgia de amígdalas', $atestado->fresh()->observacao);
        $this->assertStringNotContainsString('amígdalas', (string) DB::table('atestados_aluno')->value('observacao'));
    }

    public function test_observacao_nao_vaza_para_o_motivo_da_falta_nem_para_o_log(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');
        $this->registrar('2026-10-20', '2026-10-22', null, 'Cirurgia de amígdalas');

        $this->assertStringNotContainsString('amígdalas', (string) $falta->fresh()->motivo_justificativa);

        $logs = Activity::query()->get()->map(fn (Activity $log): string => $log->description.json_encode($log->properties))->implode(' ');
        $this->assertStringNotContainsString('amígdalas', $logs);
        $this->assertStringContainsString('1 falta(s) justificada(s)', $logs);
    }

    public function test_so_quem_tem_permissao_abre_o_comprovante(): void
    {
        $atestado = $this->registrar();
        $url = route('documentos.visualizar', ['path' => $atestado->arquivo_path]);

        $this->actingAs($this->como($this->secretaria))->get($url)->assertOk();
        $this->actingAs($this->como($this->usuario('coordenador')))->get($url)->assertOk();
        $this->actingAs($this->como($this->usuario('professor')))->get($url)->assertForbidden();
        $this->actingAs($this->como($this->usuario('responsavel')))->get($url)->assertForbidden();
    }

    public function test_arquivo_da_pasta_sem_atestado_nao_abre_nem_para_a_secretaria(): void
    {
        Storage::disk('local')->put('atestados_aluno/99/solto.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs($this->como($this->secretaria))
            ->get(route('documentos.visualizar', ['path' => 'atestados_aluno/99/solto.pdf']))
            ->assertForbidden();
    }

    // --- Telas ----------------------------------------------------------------------------------------------------------

    public function test_registrar_pela_tela_guarda_o_atestado_e_abona_as_faltas(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateAtestadoAluno::class)
            ->fillForm([
                'matricula_id' => $this->matricula->id,
                'data_inicio' => '2026-10-20',
                'data_fim' => '2026-10-22',
                'comprovante' => $this->pdf(),
                'observacao' => 'Gripe forte',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $atestado = AtestadoAluno::firstOrFail();

        $this->assertSame($this->secretaria->id, $atestado->registrado_por_user_id);
        $this->assertSame('Gripe forte', $atestado->observacao);
        $this->assertTrue($falta->fresh()->justificada);
        Storage::disk('local')->assertExists($atestado->arquivo_path);
    }

    public function test_tela_recusa_data_final_anterior_e_falta_de_comprovante(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateAtestadoAluno::class)
            ->fillForm([
                'matricula_id' => $this->matricula->id,
                'data_inicio' => '2026-10-22',
                'data_fim' => '2026-10-20',
            ])
            ->call('create')
            ->assertHasFormErrors(['data_fim', 'comprovante']);

        $this->assertSame(0, AtestadoAluno::count());
    }

    public function test_lista_permissoes_e_acoes_por_papel_e_estado(): void
    {
        $vigente = $this->registrar();
        $cancelado = $this->registrar('2026-10-26', '2026-10-27');
        $this->servico()->cancelar($cancelado, 'Engano', $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListAtestadosAluno::class)
            ->assertCanSeeTableRecords([$vigente, $cancelado])
            ->assertTableActionVisible('corrigir_periodo', $vigente)
            ->assertTableActionVisible('cancelar', $vigente)
            ->assertTableActionVisible('ver_comprovante', $vigente)
            ->assertTableActionHidden('corrigir_periodo', $cancelado)
            ->assertTableActionHidden('cancelar', $cancelado);

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->test(ListAtestadosAluno::class)
            ->assertCanSeeTableRecords([$vigente])
            ->assertTableActionVisible('ver_comprovante', $vigente)
            ->assertTableActionHidden('corrigir_periodo', $vigente)
            ->assertTableActionHidden('cancelar', $vigente);

        Livewire::actingAs($this->como($this->usuario('professor')))
            ->test(ListAtestadosAluno::class)
            ->assertForbidden();
    }

    /**
     * O Gate::before dá `true` ao super admin até em método de policy: quem barra o atestado cancelado é a tela e o serviço.
     */
    public function test_super_admin_nao_corrige_nem_cancela_atestado_ja_cancelado(): void
    {
        $vigente = $this->registrar('2026-10-26', '2026-10-27');
        $cancelado = $this->registrar();
        $this->servico()->cancelar($cancelado, 'Engano', $this->secretaria);

        Livewire::actingAs($this->como($this->usuario('super_admin')))
            ->test(ListAtestadosAluno::class)
            ->assertTableActionVisible('cancelar', $vigente)
            ->assertTableActionVisible('corrigir_periodo', $vigente)
            ->assertTableActionHidden('cancelar', $cancelado)
            ->assertTableActionHidden('corrigir_periodo', $cancelado);

        $this->expectException(\DomainException::class);
        $this->servico()->cancelar($cancelado->fresh(), 'De novo');
    }

    public function test_cancelar_e_corrigir_pela_tela(): void
    {
        $falta = $this->lancar($this->matricula, '2026-10-21');
        $atestado = $this->registrar('2026-10-21', '2026-10-21');

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListAtestadosAluno::class)
            ->callTableAction('corrigir_periodo', $atestado, data: ['data_inicio' => '2026-10-22', 'data_fim' => '2026-10-23'])
            ->assertHasNoTableActionErrors();

        $this->assertFalse($falta->fresh()->justificada, 'o dia 21 saiu do período');
        $this->assertSame('2026-10-22', $atestado->fresh()->data_inicio->toDateString());

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListAtestadosAluno::class)
            ->callTableAction('cancelar', $atestado, data: ['motivo' => 'Atestado de outro aluno'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(StatusAtestadoAluno::Cancelado, $atestado->fresh()->status);
    }
}
