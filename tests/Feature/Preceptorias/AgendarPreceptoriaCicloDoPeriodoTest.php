<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Filament\Widgets\PreceptoriaSchedulingWidget;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\Turma;
use App\Models\User;
use App\Services\PreceptoriaAgendaService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * O ciclo de preceptoria pertence a um período letivo: a matrícula só agenda em ciclo válido (que não
 * venceu) do período letivo da turma dela. Sem ciclo assim, não há opção de agendar.
 */
class AgendarPreceptoriaCicloDoPeriodoTest extends TestCase
{
    use RefreshDatabase;

    private PreceptoriaAgendaService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servico = app(PreceptoriaAgendaService::class);
    }

    private function usuarioCom(array $permissoes, string $papelAtivo = 'admin'): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => $papelAtivo, 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay()]);
        $user->assignRole($papel);
        session(['active_role' => $papelAtivo]);

        return $user;
    }

    private function periodo(string $nome): PeriodoLetivo
    {
        return PeriodoLetivo::factory()->create(['nome' => $nome]);
    }

    /**
     * @param  array<string, string>  $datas  data_inicio e/ou data_fim; o padrão é um ciclo em andamento
     */
    private function ciclo(PeriodoLetivo $periodo, string $nome, array $datas = []): CicloPreceptoria
    {
        return CicloPreceptoria::create([
            'nome' => $nome,
            'data_inicio' => $datas['data_inicio'] ?? now()->subMonth()->toDateString(),
            'data_fim' => $datas['data_fim'] ?? now()->addMonths(2)->toDateString(),
            'periodo_letivo_id' => $periodo->id,
        ]);
    }

    private function matricula(Pessoa $conselheiro, PeriodoLetivo $periodo, ?Pessoa $aluno = null): Matricula
    {
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $conselheiro->id, 'periodo_letivo_id' => $periodo->id]);

        return Matricula::factory()->create(['pessoa_id' => ($aluno ?? Pessoa::factory()->create())->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
    }

    private function livre(Pessoa $professor, ?CicloPreceptoria $ciclo, int $dias): Preceptoria
    {
        return Preceptoria::factory()->create([
            'professor_id' => $professor->id,
            'matricula_id' => null,
            'ciclo_preceptoria_id' => $ciclo?->id,
            'data' => now()->addDays($dias)->toDateString(),
        ]);
    }

    private function paginaDe(User $user, Matricula $matricula)
    {
        return Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $matricula->id]);
    }

    /**
     * @return array<int|string, string>
     */
    private function horariosOferecidos($pagina): array
    {
        $oferecidos = [];

        $pagina->assertFormComponentExists('preceptoria_id', 'form', function (Select $campo) use (&$oferecidos): bool {
            $oferecidos = $campo->getOptions();

            return true;
        });

        return $oferecidos;
    }

    private function avisoSemCicloVisivel($pagina): bool
    {
        $visivel = null;

        $pagina->assertFormComponentExists('sem_ciclo_valido', 'form', function (Placeholder $aviso) use (&$visivel): bool {
            $visivel = $aviso->isVisible();

            return true;
        });

        return $visivel;
    }

    public function test_oferece_so_horarios_de_ciclo_do_periodo_letivo_da_matricula(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $periodoDela = $this->periodo('2026');
        $outroPeriodo = $this->periodo('2027');
        $matricula = $this->matricula($conselheiro, $periodoDela);

        $doPeriodo = $this->livre($conselheiro, $this->ciclo($periodoDela, 'Ciclo 2026'), 3);
        $deOutroPeriodo = $this->livre($conselheiro, $this->ciclo($outroPeriodo, 'Ciclo 2027'), 4);
        $semCiclo = $this->livre($conselheiro, null, 5);

        $oferecidos = array_keys($this->horariosOferecidos($this->paginaDe($user, $matricula)));

        $this->assertSame([$doPeriodo->id], $oferecidos);
        $this->assertNotContains($deOutroPeriodo->id, $oferecidos);
        $this->assertNotContains($semCiclo->id, $oferecidos);
    }

    public function test_ciclo_que_ainda_nao_comecou_e_valido_e_o_vencido_nao(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $periodo = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodo);

        $vencido = $this->ciclo($periodo, 'Vencido', ['data_inicio' => now()->subMonths(3)->toDateString(), 'data_fim' => now()->subDay()->toDateString()]);
        $futuro = $this->ciclo($periodo, 'Futuro', ['data_inicio' => now()->addDays(5)->toDateString(), 'data_fim' => now()->addDays(40)->toDateString()]);
        $doVencido = $this->livre($conselheiro, $vencido, 3);
        $doFuturo = $this->livre($conselheiro, $futuro, 10);

        $oferecidos = array_keys($this->horariosOferecidos($this->paginaDe($user, $matricula)));

        $this->assertSame([$doFuturo->id], $oferecidos);
        $this->assertNotContains($doVencido->id, $oferecidos);
    }

    public function test_matricula_sem_ciclo_valido_no_periodo_nao_tem_opcao_de_agendar(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $periodoDela = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodoDela);
        // só existe ciclo de outro período, e o do período dela venceu
        $horarioDeOutroPeriodo = $this->livre($conselheiro, $this->ciclo($this->periodo('2027'), 'Ciclo 2027'), 3);
        $this->ciclo($periodoDela, 'Ciclo vencido', ['data_inicio' => now()->subMonths(3)->toDateString(), 'data_fim' => now()->subDay()->toDateString()]);

        $pagina = $this->paginaDe($user, $matricula);

        $this->assertFalse($this->servico->podeAgendar($matricula->id));
        $this->assertSame([], $this->horariosOferecidos($pagina));
        $this->assertTrue($this->avisoSemCicloVisivel($pagina));
        $this->assertFalse($pagina->instance()->mostrarBotaoAgendar());

        // mesmo forçando o horário no formulário, nada é agendado
        Notification::fake();
        $pagina->fillForm(['preceptoria_id' => $horarioDeOutroPeriodo->id])->call('agendar');
        $this->assertNull($horarioDeOutroPeriodo->fresh()->matricula_id);
    }

    public function test_com_ciclo_valido_o_aviso_some_e_o_botao_aparece(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $periodo = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodo);
        $this->ciclo($periodo, 'Ciclo 2026');

        $pagina = $this->paginaDe($user, $matricula);

        $this->assertTrue($this->servico->podeAgendar($matricula->id));
        $this->assertFalse($this->avisoSemCicloVisivel($pagina));
        $this->assertTrue($pagina->instance()->mostrarBotaoAgendar());
    }

    public function test_agenda_em_horario_de_ciclo_do_periodo_dela(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $periodo = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodo);
        $horario = $this->livre($conselheiro, $this->ciclo($periodo, 'Ciclo 2026'), 3);

        $this->paginaDe($user, $matricula)
            ->fillForm(['preceptoria_id' => $horario->id])
            ->call('agendar');

        $this->assertSame($matricula->id, $horario->fresh()->matricula_id);
    }

    public function test_agendamento_no_ciclo_atual_e_conferido_so_nos_ciclos_do_periodo_da_matricula(): void
    {
        $conselheiro = Pessoa::factory()->create();
        $periodoDela = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodoDela);
        $cicloDela = $this->ciclo($periodoDela, 'Ciclo 2026');
        // outro período com ciclo em andamento: não pode ser tomado como "o ciclo atual" dela
        $this->ciclo($this->periodo('2027'), 'Ciclo 2027');

        $this->assertTrue($this->servico->podeAgendar($matricula->id));
        $this->assertSame($cicloDela->id, $this->servico->cicloAtualDaMatricula($matricula->id)?->id);

        Preceptoria::factory()->create(['professor_id' => $conselheiro->id, 'matricula_id' => $matricula->id, 'ciclo_preceptoria_id' => $cicloDela->id]);

        $this->assertFalse($this->servico->podeAgendar($matricula->id));
    }

    public function test_trocar_o_aluno_pelo_calendario_exige_ciclo_do_periodo_da_matricula(): void
    {
        Notification::fake();

        $conselheiro = Pessoa::factory()->create();
        $periodoDela = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodoDela);

        $doOutroPeriodo = $this->livre($conselheiro, $this->ciclo($this->periodo('2027'), 'Ciclo 2027'), 3);
        $semCiclo = $this->livre($conselheiro, null, 4);
        $doPeriodo = $this->livre($conselheiro, $this->ciclo($periodoDela, 'Ciclo 2026'), 5);

        $recusa = $this->servico->atualizarParticipantes($doOutroPeriodo, $conselheiro->id, $matricula->id);
        $this->assertFalse($recusa['ok']);
        $this->assertStringContainsString('a matrícula é do período letivo 2026 e o ciclo "Ciclo 2027" é do período 2027', $recusa['mensagem']);

        $semCicloResultado = $this->servico->atualizarParticipantes($semCiclo, $conselheiro->id, $matricula->id);
        $this->assertFalse($semCicloResultado['ok']);
        $this->assertStringContainsString('não tem ciclo de preceptoria com período letivo', $semCicloResultado['mensagem']);

        $this->assertNull($doOutroPeriodo->fresh()->matricula_id);
        $this->assertNull($semCiclo->fresh()->matricula_id);
        $this->assertCount(0, $doOutroPeriodo->historico());

        $this->assertTrue($this->servico->atualizarParticipantes($doPeriodo, $conselheiro->id, $matricula->id, notificar: false)['ok']);
        $this->assertSame($matricula->id, $doPeriodo->fresh()->matricula_id);
    }

    public function test_janelas_de_preceptoria_so_contam_no_periodo_do_ciclo(): void
    {
        $conselheiro = Pessoa::factory()->create();
        $comJanela = $this->periodo('2026');
        $cicloVencido = $this->periodo('2025');
        $semCiclo = $this->periodo('2024');

        $matriculaComJanela = $this->matricula($conselheiro, $comJanela);
        $matriculaCicloVencido = $this->matricula($conselheiro, $cicloVencido);
        $matriculaSemCiclo = $this->matricula($conselheiro, $semCiclo);

        $this->livre($conselheiro, $this->ciclo($comJanela, 'Ciclo 2026'), 3);
        $this->livre($conselheiro, $this->ciclo($cicloVencido, 'Ciclo 2025', ['data_inicio' => now()->subMonths(3)->toDateString(), 'data_fim' => now()->subDay()->toDateString()]), 4);

        $this->assertSame([$comJanela->id], $this->servico->periodosComHorariosLivres());
        $this->assertTrue($matriculaComJanela->hasAvailablePreceptoriaWindows());
        $this->assertFalse($matriculaCicloVencido->hasAvailablePreceptoriaWindows());
        $this->assertFalse($matriculaSemCiclo->hasAvailablePreceptoriaWindows());
    }

    public function test_horario_ja_agendado_nao_conta_como_janela(): void
    {
        $conselheiro = Pessoa::factory()->create();
        $periodo = $this->periodo('2026');
        $matricula = $this->matricula($conselheiro, $periodo);
        $horario = $this->livre($conselheiro, $this->ciclo($periodo, 'Ciclo 2026'), 3);
        $horario->update(['matricula_id' => $this->matricula($conselheiro, $periodo)->id]);

        $this->assertSame([], $this->servico->periodosComHorariosLivres());
        $this->assertFalse($matricula->hasAvailablePreceptoriaWindows());
    }

    public function test_aviso_por_e_mail_so_aparece_para_matricula_com_janela_no_periodo(): void
    {
        $admin = $this->usuarioCom([], 'super_admin');

        $conselheiro = Pessoa::factory()->create();
        $comJanela = $this->periodo('2026');
        $semJanela = $this->periodo('2025');
        $matriculaComJanela = $this->matricula($conselheiro, $comJanela);
        $matriculaSemJanela = $this->matricula($conselheiro, $semJanela);
        $this->livre($conselheiro, $this->ciclo($comJanela, 'Ciclo 2026'), 3);

        Livewire::actingAs($admin)
            ->test(ListMatriculas::class)
            ->assertTableActionVisible('avisar_possibilidade_preceptoria', $matriculaComJanela)
            ->assertTableActionHidden('avisar_possibilidade_preceptoria', $matriculaSemJanela);
    }

    public function test_painel_so_oferece_agendar_quando_ha_horario_no_periodo_do_aluno(): void
    {
        $conselheiro = Pessoa::factory()->create();
        $comJanela = $this->periodo('2026');
        $semJanela = $this->periodo('2025');
        $alunoComJanela = Pessoa::factory()->create(['nome' => 'Aluna Com Janela']);
        $alunoSemJanela = Pessoa::factory()->create(['nome' => 'Aluna Sem Janela']);
        $this->matricula($conselheiro, $comJanela, $alunoComJanela);
        $this->matricula($conselheiro, $semJanela, $alunoSemJanela);
        $this->livre($conselheiro, $this->ciclo($comJanela, 'Ciclo 2026'), 3);

        $responsavel = $this->usuarioCom([], 'responsavel');
        $responsavelPessoa = Pessoa::factory()->create();
        $responsavel->pessoas()->attach($responsavelPessoa->id);
        $alunoComJanela->responsaveis()->attach($responsavelPessoa->id);
        $alunoSemJanela->responsaveis()->attach($responsavelPessoa->id);

        Livewire::actingAs($responsavel)
            ->test(PreceptoriaSchedulingWidget::class)
            ->assertSee('Aluna Com Janela')
            ->assertSee('Preceptoria Disponível')
            ->assertDontSee('Aluna Sem Janela');
    }
}
