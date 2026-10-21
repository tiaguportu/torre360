<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusAcaoDesenvolvimento;
use App\Enums\StatusAutoavaliacaoDocente;
use App\Enums\StatusPlanoDesenvolvimento;
use App\Filament\Resources\AutoavaliacaoDocentes\Pages\EditAutoavaliacaoDocente;
use App\Filament\Resources\AutoavaliacaoDocentes\Pages\ListAutoavaliacaoDocentes;
use App\Filament\Resources\AvaliacaoDocentes\Pages\ListAvaliacaoDocentes;
use App\Filament\Resources\AvaliacaoDocentes\Pages\ViewAvaliacaoDocente;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\EditPlanoDesenvolvimentoDocente;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\ListPlanoDesenvolvimentoDocentes;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\ViewPlanoDesenvolvimentoDocente;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\RelationManagers\AcoesRelationManager;
use App\Models\AcaoDesenvolvimentoDocente;
use App\Models\AutoavaliacaoDocente;
use App\Models\AvaliacaoDocente;
use App\Models\AvaliacaoDocenteCriterio;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Models\User;
use App\Services\DesenvolvimentoDocenteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Telas da autoavaliação e do plano de desenvolvimento docente: acesso, ações e bloqueios.
 */
class DesenvolvimentoDocenteTelasTest extends TestCase
{
    use RefreshDatabase;

    private CicloAvaliacaoDocente $ciclo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        $this->ciclo = CicloAvaliacaoDocente::factory()->create(['data_inicio' => '2026-08-01', 'data_fim' => '2026-12-18']);
    }

    /**
     * @param  list<string>  $permissoes
     * @return array{user: User, func: ?Funcionario}
     */
    private function usuario(string $papel, array $permissoes, bool $comFuncionario = true): array
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        session(['active_role' => $papel]);

        $func = null;
        if ($comFuncionario) {
            $pessoa = Pessoa::factory()->create();
            $user->pessoas()->attach($pessoa->id);
            $func = Funcionario::factory()->create(['pessoa_id' => $pessoa->id, 'data_admissao' => '2020-02-03']);
        }

        return ['user' => $user, 'func' => $func];
    }

    /** @return array{user: User, func: ?Funcionario} */
    private function professor(): array
    {
        return $this->usuario('professor', [
            'ViewAny:AutoavaliacaoDocente', 'View:AutoavaliacaoDocente', 'Create:AutoavaliacaoDocente', 'Update:AutoavaliacaoDocente', 'Enviar:AutoavaliacaoDocente',
            'ViewAny:PlanoDesenvolvimentoDocente', 'View:PlanoDesenvolvimentoDocente', 'DarCiencia:PlanoDesenvolvimentoDocente', 'AtualizarAcao:PlanoDesenvolvimentoDocente',
            'ViewAny:AvaliacaoDocente', 'View:AvaliacaoDocente',
        ]);
    }

    private function coordenacao(): User
    {
        return $this->usuario('coordenador', [
            'ViewAny:AutoavaliacaoDocente', 'View:AutoavaliacaoDocente',
            'ViewAny:PlanoDesenvolvimentoDocente', 'View:PlanoDesenvolvimentoDocente', 'Create:PlanoDesenvolvimentoDocente', 'Update:PlanoDesenvolvimentoDocente',
            'Ativar:PlanoDesenvolvimentoDocente', 'Concluir:PlanoDesenvolvimentoDocente', 'AtualizarAcao:PlanoDesenvolvimentoDocente',
            'ViewAny:AvaliacaoDocente', 'View:AvaliacaoDocente',
        ], comFuncionario: false)['user'];
    }

    private function avaliacaoFinalizada(Funcionario $func, array $notas = [2, 4]): AvaliacaoDocente
    {
        $avaliacao = AvaliacaoDocente::factory()->finalizada(60)->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]);

        foreach ($this->ciclo->instrumento->criterios as $i => $criterio) {
            AvaliacaoDocenteCriterio::create(['avaliacao_docente_id' => $avaliacao->id, 'criterio_id' => $criterio->id, 'nota' => $notas[$i] ?? 3]);
        }

        return $avaliacao;
    }

    private function planoAtivo(Funcionario $func): PlanoDesenvolvimentoDocente
    {
        $plano = app(DesenvolvimentoDocenteService::class)->criarPlano($this->avaliacaoFinalizada($func), User::factory()->create());
        $plano->update(['objetivo' => 'Melhorar o planejamento das aulas']);
        $this->ativar($plano);

        return $plano->refresh();
    }

    private function ativar(PlanoDesenvolvimentoDocente $plano): void
    {
        $plano->update(['status' => StatusPlanoDesenvolvimento::Ativo, 'ativado_em' => now(), 'data_revisao' => '2027-01-15']);
    }

    // ─── Autoavaliação ───────────────────────────────────────────────

    public function test_professor_inicia_a_autoavaliacao_do_ciclo_aberto_e_vai_para_o_preenchimento(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();

        Livewire::actingAs($prof)
            ->test(ListAutoavaliacaoDocentes::class)
            ->callAction('iniciar', ['ciclo_id' => $this->ciclo->id])
            ->assertRedirect();

        $auto = AutoavaliacaoDocente::first();
        $this->assertSame($func->id, $auto->funcionario_id);
        $this->assertSame(StatusAutoavaliacaoDocente::Rascunho, $auto->status);
        $this->assertSame(2, $auto->notas()->count());
    }

    public function test_quem_nao_e_funcionario_nao_ve_o_botao_de_iniciar(): void
    {
        ['user' => $semCadastro] = $this->usuario('professor', ['ViewAny:AutoavaliacaoDocente', 'Create:AutoavaliacaoDocente'], comFuncionario: false);

        Livewire::actingAs($semCadastro)->test(ListAutoavaliacaoDocentes::class)->assertActionHidden('iniciar');
    }

    public function test_enviar_pela_tela_exige_todas_as_notas_e_depois_envia(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        $auto = AutoavaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]);
        foreach ($this->ciclo->instrumento->criterios as $criterio) {
            $auto->notas()->create(['criterio_id' => $criterio->id]);
        }

        $tela = Livewire::actingAs($prof)->test(EditAutoavaliacaoDocente::class, ['record' => $auto->getRouteKey()]);

        $tela->callAction('enviar')->assertNotified('Não foi possível enviar');
        $this->assertSame(StatusAutoavaliacaoDocente::Rascunho, $auto->fresh()->status);

        $auto->notas()->update(['nota' => 4]);
        Livewire::actingAs($prof)
            ->test(EditAutoavaliacaoDocente::class, ['record' => $auto->getRouteKey()])
            ->callAction('enviar')
            ->assertNotified('Autoavaliação enviada');

        $this->assertSame(StatusAutoavaliacaoDocente::Enviada, $auto->fresh()->status);
        $this->assertSame('80.00', (string) $auto->fresh()->pontuacao_percentual);
    }

    public function test_rascunho_e_privado_e_a_enviada_aparece_para_a_coordenacao(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        ['user' => $colega, 'func' => $funcColega] = $this->professor();
        $coord = $this->coordenacao();
        $rascunho = AutoavaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]);
        $enviada = AutoavaliacaoDocente::factory()->enviada()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $funcColega->id]);

        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)->test(ListAutoavaliacaoDocentes::class)
            ->assertCanSeeTableRecords([$rascunho])
            ->assertCanNotSeeTableRecords([$enviada]);

        session(['active_role' => 'coordenador']);
        Livewire::actingAs($coord)->test(ListAutoavaliacaoDocentes::class)
            ->assertCanSeeTableRecords([$enviada])
            ->assertCanNotSeeTableRecords([$rascunho]);

        // A coordenação não consegue abrir o rascunho nem pelo endereço (para ela, o registro não existe).
        Livewire::actingAs($coord)->test(EditAutoavaliacaoDocente::class, ['record' => $rascunho->getRouteKey()])->assertNotFound();
    }

    public function test_so_a_administracao_reabre_a_autoavaliacao_enviada(): void
    {
        ['func' => $func] = $this->professor();
        $enviada = AutoavaliacaoDocente::factory()->enviada()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]);
        ['user' => $admin] = $this->usuario('admin', ['ViewAny:AutoavaliacaoDocente', 'View:AutoavaliacaoDocente', 'Reabrir:AutoavaliacaoDocente'], comFuncionario: false);

        Livewire::actingAs($admin)->test(ListAutoavaliacaoDocentes::class)
            ->callTableAction('reabrir', $enviada)
            ->assertNotified('Autoavaliação reaberta');

        $this->assertSame(StatusAutoavaliacaoDocente::Rascunho, $enviada->fresh()->status);
    }

    // ─── Plano de desenvolvimento ────────────────────────────────────

    public function test_coordenacao_cria_o_plano_a_partir_da_avaliacao_finalizada_pela_lista(): void
    {
        ['func' => $func] = $this->professor();
        $coord = $this->coordenacao();
        $avaliacao = $this->avaliacaoFinalizada($func);

        Livewire::actingAs($coord)
            ->test(ListAvaliacaoDocentes::class)
            ->callTableAction('planoDesenvolvimento', $avaliacao)
            ->assertNotified('Plano criado em rascunho');

        $plano = PlanoDesenvolvimentoDocente::first();
        $this->assertSame($avaliacao->id, $plano->avaliacao_docente_id);
        $this->assertSame(StatusPlanoDesenvolvimento::Rascunho, $plano->status);
        $this->assertSame(1, $plano->acoes()->count());

        // A segunda vez só abre o que existe.
        Livewire::actingAs($coord)
            ->test(ListAvaliacaoDocentes::class)
            ->callTableAction('planoDesenvolvimento', $avaliacao);
        $this->assertSame(1, PlanoDesenvolvimentoDocente::count());
    }

    public function test_acao_de_criar_plano_so_aparece_para_avaliacao_finalizada_e_para_quem_pode(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        $coord = $this->coordenacao();
        $finalizada = $this->avaliacaoFinalizada($func);
        $rascunho = AvaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id, 'data_observacao' => '2026-10-01']);

        session(['active_role' => 'coordenador']);
        Livewire::actingAs($coord)->test(ListAvaliacaoDocentes::class)
            ->assertTableActionVisible('planoDesenvolvimento', $finalizada)
            ->assertTableActionHidden('planoDesenvolvimento', $rascunho);

        // O professor não cria plano e, sem plano ativo, não tem o que abrir.
        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)->test(ListAvaliacaoDocentes::class)
            ->assertTableActionHidden('planoDesenvolvimento', $finalizada);
    }

    public function test_professor_nao_ve_o_plano_em_rascunho_nem_o_de_colegas(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        ['func' => $funcColega] = $this->professor();
        $rascunho = app(DesenvolvimentoDocenteService::class)->criarPlano($this->avaliacaoFinalizada($func), User::factory()->create());
        $doColega = $this->planoAtivo($funcColega);

        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)->test(ListPlanoDesenvolvimentoDocentes::class)
            ->assertCanNotSeeTableRecords([$rascunho, $doColega]);

        Livewire::actingAs($prof)->test(ViewPlanoDesenvolvimentoDocente::class, ['record' => $rascunho->getRouteKey()])->assertNotFound();

        $this->ativar($rascunho);
        Livewire::actingAs($prof)->test(ListPlanoDesenvolvimentoDocentes::class)->assertCanSeeTableRecords([$rascunho->fresh()]);
    }

    public function test_coordenacao_ativa_o_plano_pela_tela_de_edicao(): void
    {
        ['func' => $func] = $this->professor();
        $coord = $this->coordenacao();
        $plano = app(DesenvolvimentoDocenteService::class)->criarPlano($this->avaliacaoFinalizada($func), $coord);

        Livewire::actingAs($coord)
            ->test(EditPlanoDesenvolvimentoDocente::class, ['record' => $plano->getRouteKey()])
            ->assertSee('Autoavaliação × observações do ciclo')
            ->assertSee('Planejamento da aula')
            ->callAction('ativar')
            ->assertNotified('Plano ativado');

        $this->assertSame(StatusPlanoDesenvolvimento::Ativo, $plano->fresh()->status);
    }

    public function test_professor_nao_edita_o_proprio_plano_e_da_ciencia_na_tela_de_visualizacao(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        $plano = $this->planoAtivo($func);

        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)->test(EditPlanoDesenvolvimentoDocente::class, ['record' => $plano->getRouteKey()])->assertForbidden();

        Livewire::actingAs($prof)
            ->test(ViewPlanoDesenvolvimentoDocente::class, ['record' => $plano->getRouteKey()])
            ->assertSee('Ainda não deu ciência')
            ->assertActionVisible('darCiencia')
            ->callAction('darCiencia')
            ->assertNotified('Ciência registrada');

        $this->assertNotNull($plano->fresh()->ciencia_em);

        Livewire::actingAs($prof)
            ->test(ViewPlanoDesenvolvimentoDocente::class, ['record' => $plano->getRouteKey()])
            ->assertActionHidden('darCiencia');
    }

    public function test_professor_registra_o_andamento_das_proprias_acoes(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        $plano = $this->planoAtivo($func);
        $acao = $plano->acoes->first();

        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)
            ->test(AcoesRelationManager::class, ['ownerRecord' => $plano, 'pageClass' => ViewPlanoDesenvolvimentoDocente::class])
            ->assertTableActionVisible('andamento', $acao)
            ->callTableAction('andamento', $acao, ['status' => 'em_andamento', 'resultado' => null])
            ->assertNotified('Andamento registrado');

        Livewire::actingAs($prof)
            ->test(AcoesRelationManager::class, ['ownerRecord' => $plano, 'pageClass' => ViewPlanoDesenvolvimentoDocente::class])
            ->callTableAction('andamento', $acao->fresh(), ['status' => 'concluida', 'resultado' => 'Fiz a oficina e apliquei o planejamento em duas turmas.'])
            ->assertNotified('Andamento registrado');

        $this->assertSame(StatusAcaoDesenvolvimento::Concluida, $acao->fresh()->status);
    }

    public function test_professor_nao_edita_nem_cria_acoes_e_a_coordenacao_sim(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        $coord = $this->coordenacao();
        $plano = $this->planoAtivo($func);
        $acao = $plano->acoes->first();

        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)
            ->test(AcoesRelationManager::class, ['ownerRecord' => $plano, 'pageClass' => ViewPlanoDesenvolvimentoDocente::class])
            ->assertTableActionHidden('edit', $acao)
            ->assertTableActionHidden('delete', $acao);

        session(['active_role' => 'coordenador']);
        Livewire::actingAs($coord)
            ->test(AcoesRelationManager::class, ['ownerRecord' => $plano, 'pageClass' => EditPlanoDesenvolvimentoDocente::class])
            ->assertTableActionVisible('edit', $acao)
            ->callTableAction('andamento', $acao, ['status' => 'cancelada', 'resultado' => 'Substituída por formação interna.'])
            ->assertNotified('Andamento registrado');

        $this->assertSame(StatusAcaoDesenvolvimento::Cancelada, $acao->fresh()->status);
    }

    public function test_coordenacao_cria_acao_nova_no_plano_em_rascunho(): void
    {
        ['func' => $func] = $this->professor();
        $coord = $this->coordenacao();
        $plano = app(DesenvolvimentoDocenteService::class)->criarPlano($this->avaliacaoFinalizada($func), $coord);

        Livewire::actingAs($coord)
            ->test(AcoesRelationManager::class, ['ownerRecord' => $plano, 'pageClass' => EditPlanoDesenvolvimentoDocente::class])
            ->callTableAction('create', data: ['descricao' => 'Observar uma aula de uma colega experiente', 'prazo' => '2026-12-10'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $plano->acoes()->count());
        $nova = $plano->acoes()->reorder()->latest('id')->first();
        $this->assertSame('Observar uma aula de uma colega experiente', $nova->descricao);
        $this->assertSame(2, $nova->ordem);
        $this->assertInstanceOf(AcaoDesenvolvimentoDocente::class, $nova);
    }

    public function test_avaliacao_finalizada_do_professor_oferece_abrir_o_plano_ativo(): void
    {
        ['user' => $prof, 'func' => $func] = $this->professor();
        $plano = $this->planoAtivo($func);

        session(['active_role' => 'professor']);
        Livewire::actingAs($prof)
            ->test(ViewAvaliacaoDocente::class, ['record' => $plano->avaliacao_docente_id])
            ->assertActionVisible('planoDesenvolvimento');
    }
}
