<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusAvaliacaoDocente;
use App\Exceptions\AvaliacaoDocenteException;
use App\Filament\Resources\AvaliacaoDocentes\Pages\CreateAvaliacaoDocente;
use App\Filament\Resources\AvaliacaoDocentes\Pages\EditAvaliacaoDocente;
use App\Filament\Resources\AvaliacaoDocentes\Pages\ListAvaliacaoDocentes;
use App\Filament\Resources\AvaliacaoDocentes\Pages\ViewAvaliacaoDocente;
use App\Filament\Resources\CicloAvaliacaoDocentes\Pages\CreateCicloAvaliacaoDocente;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\RelationManagers\AvaliacoesDocenteRelationManager;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages\CreateInstrumentoAvaliacaoDocente;
use App\Models\AvaliacaoDocente;
use App\Models\AvaliacaoDocenteCriterio;
use App\Models\CicloAvaliacaoDocente;
use App\Models\CriterioAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\InstrumentoAvaliacaoDocente;
use App\Models\Pessoa;
use App\Models\User;
use App\Services\AvaliacaoDocenteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Avaliação de Desempenho Docente (Onda 19): instrumento de critérios configurável, ciclos, observação em sala,
 * pontuação consolidada, imutabilidade depois de finalizada e visibilidade restrita (dado sensível).
 */
class AvaliacaoDocenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissoes  nomes completos (ex.: 'ViewAny:AvaliacaoDocente')
     */
    private function autenticarComo(string $papel, array $permissoes, ?Pessoa $pessoa = null): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);

        if ($pessoa) {
            $user->pessoas()->attach($pessoa->id);
        }

        $this->actingAs($user);
        session(['active_role' => $papel]);

        return $user;
    }

    private function coordenacao(?Pessoa $pessoa = null): User
    {
        return $this->autenticarComo('coordenador', [
            'ViewAny:AvaliacaoDocente', 'View:AvaliacaoDocente', 'Create:AvaliacaoDocente', 'Update:AvaliacaoDocente',
            'Finalizar:AvaliacaoDocente', 'ViewAny:CicloAvaliacaoDocente', 'View:CicloAvaliacaoDocente',
            'Create:CicloAvaliacaoDocente', 'Update:CicloAvaliacaoDocente', 'ViewAny:InstrumentoAvaliacaoDocente',
            'Create:InstrumentoAvaliacaoDocente', 'Update:InstrumentoAvaliacaoDocente', 'ViewAny:Funcionario',
        ], $pessoa);
    }

    private function administrador(): User
    {
        return $this->autenticarComo('admin', [
            'ViewAny:AvaliacaoDocente', 'View:AvaliacaoDocente', 'Create:AvaliacaoDocente', 'Update:AvaliacaoDocente',
            'Finalizar:AvaliacaoDocente', 'Reabrir:AvaliacaoDocente', 'Delete:AvaliacaoDocente', 'DeleteAny:AvaliacaoDocente',
        ]);
    }

    private function professorAvaliado(Funcionario $funcionario): User
    {
        return $this->autenticarComo('professor', ['ViewAny:AvaliacaoDocente', 'View:AvaliacaoDocente'], $funcionario->pessoa);
    }

    private function servico(): AvaliacaoDocenteService
    {
        return app(AvaliacaoDocenteService::class);
    }

    private function ciclo(array $extra = []): CicloAvaliacaoDocente
    {
        return CicloAvaliacaoDocente::factory()->create($extra);
    }

    private function dados(CicloAvaliacaoDocente $ciclo, Funcionario $funcionario, array $extra = []): array
    {
        return array_merge([
            'ciclo_id' => $ciclo->id,
            'funcionario_id' => $funcionario->id,
            'data_observacao' => now()->toDateString(),
        ], $extra);
    }

    /**
     * Preenche todas as notas de uma avaliação com o mesmo valor.
     */
    private function darNotas(AvaliacaoDocente $avaliacao, int $nota): void
    {
        $avaliacao->notas()->update(['nota' => $nota]);
    }

    // ─── Iniciar ─────────────────────────────────────────────────────

    public function test_iniciar_cria_rascunho_com_uma_linha_por_criterio_do_instrumento(): void
    {
        $coordenador = $this->coordenacao();
        $funcionario = Funcionario::factory()->create();
        $ciclo = $this->ciclo();

        $avaliacao = $this->servico()->iniciar($this->dados($ciclo, $funcionario), $coordenador);

        $this->assertSame(StatusAvaliacaoDocente::Rascunho, $avaliacao->status);
        $this->assertSame($coordenador->id, $avaliacao->avaliador_user_id);
        $this->assertCount(2, $avaliacao->notas);
        $this->assertTrue($avaliacao->notas->every(fn (AvaliacaoDocenteCriterio $n): bool => $n->nota === null));
    }

    public function test_ciclo_encerrado_ou_data_fora_do_ciclo_ou_futura_sao_recusados(): void
    {
        $coordenador = $this->coordenacao();
        $funcionario = Funcionario::factory()->create();

        $casos = [
            'ciclo encerrado' => [$this->ciclo(['ativo' => false]), now()->toDateString(), 'encerrado'],
            'antes do ciclo' => [$this->ciclo(['data_inicio' => now()->subDays(10)->toDateString(), 'data_fim' => now()->addDays(10)->toDateString()]), now()->subDays(20)->toDateString(), 'dentro do ciclo'],
            'data futura' => [$this->ciclo(), now()->addDays(2)->toDateString(), 'futura'],
        ];

        foreach ($casos as $nome => [$ciclo, $data, $trecho]) {
            try {
                $this->servico()->iniciar($this->dados($ciclo, $funcionario, ['data_observacao' => $data]), $coordenador);
                $this->fail("Deveria recusar: {$nome}.");
            } catch (AvaliacaoDocenteException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }

        $this->assertSame(0, AvaliacaoDocente::count());
    }

    public function test_funcionario_desligado_antes_da_observacao_e_instrumento_sem_criterios_sao_recusados(): void
    {
        $coordenador = $this->coordenacao();

        $desligado = Funcionario::factory()->create(['data_desligamento' => now()->subDays(5)->toDateString()]);
        try {
            $this->servico()->iniciar($this->dados($this->ciclo(), $desligado), $coordenador);
            $this->fail('Funcionário desligado deveria ser recusado.');
        } catch (AvaliacaoDocenteException $e) {
            $this->assertStringContainsString('desligado', $e->getMessage());
        }

        $semCriterios = $this->ciclo(['instrumento_id' => InstrumentoAvaliacaoDocente::factory()->create()->id]);
        $this->expectException(AvaliacaoDocenteException::class);
        $this->expectExceptionMessage('não tem critérios');
        $this->servico()->iniciar($this->dados($semCriterios, Funcionario::factory()->create()), $coordenador);
    }

    public function test_ninguem_avalia_o_proprio_desempenho(): void
    {
        $funcionario = Funcionario::factory()->create();
        $coordenador = $this->coordenacao($funcionario->pessoa);

        $this->expectException(AvaliacaoDocenteException::class);
        $this->expectExceptionMessage('próprio desempenho');

        $this->servico()->iniciar($this->dados($this->ciclo(), $funcionario), $coordenador);
    }

    public function test_mesma_observacao_no_mesmo_dia_e_recusada_mas_outro_dia_vale(): void
    {
        $coordenador = $this->coordenacao();
        $funcionario = Funcionario::factory()->create();
        $ciclo = $this->ciclo();

        $this->servico()->iniciar($this->dados($ciclo, $funcionario), $coordenador);
        $outroDia = $this->servico()->iniciar($this->dados($ciclo, $funcionario, ['data_observacao' => now()->subDay()->toDateString()]), $coordenador);

        $this->assertNotNull($outroDia->id);

        $this->expectException(AvaliacaoDocenteException::class);
        $this->expectExceptionMessage('já registrou');
        $this->servico()->iniciar($this->dados($ciclo, $funcionario), $coordenador);
    }

    // ─── Finalizar / reabrir ─────────────────────────────────────────

    public function test_finalizar_exige_todas_as_notas_e_dentro_da_escala(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);

        try {
            $this->servico()->finalizar($avaliacao, $coordenador);
            $this->fail('Sem notas não pode finalizar.');
        } catch (AvaliacaoDocenteException $e) {
            $this->assertStringContainsString('Faltam notas', $e->getMessage());
            $this->assertStringContainsString('Planejamento da aula', $e->getMessage());
        }

        $this->darNotas($avaliacao, 9); // escala 1 a 5
        $this->expectException(AvaliacaoDocenteException::class);
        $this->expectExceptionMessage('entre 1 e 5');
        $this->servico()->finalizar($avaliacao, $coordenador);
    }

    public function test_pontuacao_e_a_media_ponderada_em_percentual_da_escala(): void
    {
        $coordenador = $this->coordenacao();
        $instrumento = InstrumentoAvaliacaoDocente::factory()->comCriterios([
            ['nome' => 'Planejamento', 'peso' => 2],
            ['nome' => 'Mediação', 'peso' => 1],
        ])->create(['escala_maxima' => 5]);
        $ciclo = $this->ciclo(['instrumento_id' => $instrumento->id]);

        $avaliacao = $this->servico()->iniciar($this->dados($ciclo, Funcionario::factory()->create()), $coordenador);
        foreach ($avaliacao->notas()->with('criterio')->get() as $nota) {
            $nota->update(['nota' => $nota->criterio->nome === 'Planejamento' ? 5 : 3]);
        }

        $finalizada = $this->servico()->finalizar($avaliacao, $coordenador);

        // (5×2 + 3×1) / (5×2 + 5×1) = 13/15
        $this->assertEquals(86.67, (float) $finalizada->pontuacao_percentual);
        $this->assertSame(StatusAvaliacaoDocente::Finalizada, $finalizada->status);
        $this->assertNotNull($finalizada->finalizada_em);
    }

    public function test_finalizar_duas_vezes_e_recusado_e_registra_quem_finalizou(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);
        $this->darNotas($avaliacao, 4);

        $this->servico()->finalizar($avaliacao, $coordenador);

        $log = Activity::where('log_name', 'avaliacao_docente')->where('description', 'Avaliação docente finalizada.')->first();
        $this->assertNotNull($log);
        $this->assertSame($coordenador->id, $log->causer_id);

        $this->expectException(AvaliacaoDocenteException::class);
        $this->servico()->finalizar($avaliacao->fresh(), $coordenador);
    }

    public function test_reabrir_devolve_a_rascunho_e_zera_a_pontuacao(): void
    {
        $admin = $this->administrador();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $admin);
        $this->darNotas($avaliacao, 5);
        $this->servico()->finalizar($avaliacao, $admin);

        $reaberta = $this->servico()->reabrir($avaliacao->fresh(), $admin);

        $this->assertSame(StatusAvaliacaoDocente::Rascunho, $reaberta->status);
        $this->assertNull($reaberta->pontuacao_percentual);
        $this->assertNull($reaberta->finalizada_em);

        $this->expectException(AvaliacaoDocenteException::class);
        $this->servico()->reabrir($reaberta, $admin);
    }

    // ─── Dado sensível cifrado ───────────────────────────────────────

    public function test_parecer_e_observacoes_ficam_cifrados_no_banco(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);
        $avaliacao->update(['parecer' => 'Precisa melhorar a gestão do tempo em sala.']);
        $avaliacao->notas()->first()->update(['observacao' => 'Chegou 10 minutos atrasado à aula.']);

        $brutoParecer = DB::table('avaliacoes_docente')->where('id', $avaliacao->id)->value('parecer');
        $brutoObservacao = DB::table('avaliacao_docente_criterios')->where('avaliacao_docente_id', $avaliacao->id)->whereNotNull('observacao')->value('observacao');

        $this->assertStringNotContainsString('gestão do tempo', (string) $brutoParecer);
        $this->assertStringNotContainsString('atrasado', (string) $brutoObservacao);
        $this->assertSame('Precisa melhorar a gestão do tempo em sala.', $avaliacao->fresh()->parecer);
    }

    public function test_log_de_atividade_nao_guarda_o_parecer(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);
        $avaliacao->update(['parecer' => 'Texto confidencial do parecer.']);

        $this->assertStringNotContainsString('confidencial', Activity::where('log_name', 'avaliacao_docente')->get()->toJson());
    }

    // ─── Instrumento, ciclo e histórico congelado ────────────────────

    public function test_instrumento_em_uso_fica_congelado_e_nao_pode_ser_apagado(): void
    {
        $coordenador = $this->coordenacao();
        $ciclo = $this->ciclo();
        $instrumento = $ciclo->instrumento;

        $this->assertFalse($instrumento->emUso());

        $this->servico()->iniciar($this->dados($ciclo, Funcionario::factory()->create()), $coordenador);

        $this->assertTrue($instrumento->fresh()->emUso());
        $this->assertFalse($this->administrador()->can('delete', $instrumento), 'Instrumento com ciclos não é apagado.');
    }

    public function test_ciclo_com_avaliacoes_nao_pode_ser_apagado(): void
    {
        $coordenador = $this->coordenacao();
        $ciclo = $this->ciclo();

        $admin = $this->autenticarComo('admin', ['Delete:CicloAvaliacaoDocente']);
        $this->assertTrue($admin->can('delete', $ciclo));

        $this->servico()->iniciar($this->dados($ciclo, Funcionario::factory()->create()), $coordenador);

        $this->assertFalse($admin->can('delete', $ciclo->fresh()));
    }

    public function test_criterio_usado_em_avaliacao_nao_e_removido_do_banco(): void
    {
        $coordenador = $this->coordenacao();
        $ciclo = $this->ciclo();
        $this->servico()->iniciar($this->dados($ciclo, Funcionario::factory()->create()), $coordenador);

        $this->expectException(QueryException::class);

        CriterioAvaliacaoDocente::query()->where('instrumento_id', $ciclo->instrumento_id)->first()->delete();
    }

    // ─── Visibilidade e policies ─────────────────────────────────────

    public function test_professor_ve_so_as_proprias_avaliacoes_finalizadas(): void
    {
        $funcionario = Funcionario::factory()->create();
        $colega = Funcionario::factory()->create();

        $propriaFinalizada = AvaliacaoDocente::factory()->finalizada()->create(['funcionario_id' => $funcionario->id]);
        $propriaRascunho = AvaliacaoDocente::factory()->create(['funcionario_id' => $funcionario->id, 'data_observacao' => now()->subDays(3)->toDateString()]);
        $doColega = AvaliacaoDocente::factory()->finalizada()->create(['funcionario_id' => $colega->id]);

        $professor = $this->professorAvaliado($funcionario);

        $visiveis = AvaliacaoDocente::query()->visiveisPara($professor)->pluck('id')->all();
        $this->assertSame([$propriaFinalizada->id], $visiveis);

        $this->assertTrue($professor->can('view', $propriaFinalizada));
        $this->assertFalse($professor->can('view', $propriaRascunho), 'Rascunho não é visível ao avaliado.');
        $this->assertFalse($professor->can('view', $doColega), 'Avaliação de colega nunca é visível.');
        $this->assertFalse($professor->can('update', $propriaFinalizada));
        $this->assertFalse($professor->can('create', AvaliacaoDocente::class));
    }

    public function test_coordenacao_ve_rascunhos_e_avaliacoes_de_todos(): void
    {
        $coordenador = $this->coordenacao();
        AvaliacaoDocente::factory()->count(2)->create();
        AvaliacaoDocente::factory()->finalizada()->create();

        $this->assertSame(3, AvaliacaoDocente::query()->visiveisPara($coordenador)->count());
    }

    public function test_so_o_avaliador_edita_o_proprio_rascunho_e_finalizada_e_imutavel(): void
    {
        $avaliador = $this->coordenacao();
        $rascunho = AvaliacaoDocente::factory()->create(['avaliador_user_id' => $avaliador->id]);
        $deOutro = AvaliacaoDocente::factory()->create();
        $finalizada = AvaliacaoDocente::factory()->finalizada()->create(['avaliador_user_id' => $avaliador->id]);

        $this->assertTrue($avaliador->can('update', $rascunho));
        $this->assertTrue($avaliador->can('finalizar', $rascunho));
        $this->assertFalse($avaliador->can('update', $deOutro));
        $this->assertFalse($avaliador->can('finalizar', $deOutro));
        $this->assertFalse($avaliador->can('update', $finalizada), 'Finalizada não se altera.');
        $this->assertFalse($avaliador->can('reabrir', $finalizada), 'Reabrir é só de quem tem a permissão.');

        $admin = $this->administrador();
        $this->assertTrue($admin->can('update', $deOutro), 'Administração corrige rascunhos de qualquer avaliador.');
        $this->assertTrue($admin->can('reabrir', $finalizada));
        $this->assertFalse($admin->can('reabrir', $rascunho));
    }

    // ─── Telas (Filament) ────────────────────────────────────────────

    public function test_listagem_do_professor_mostra_so_as_proprias_finalizadas(): void
    {
        $funcionario = Funcionario::factory()->create();
        $minha = AvaliacaoDocente::factory()->finalizada()->create(['funcionario_id' => $funcionario->id]);
        $rascunho = AvaliacaoDocente::factory()->create(['funcionario_id' => $funcionario->id, 'data_observacao' => now()->subDays(2)->toDateString()]);
        $alheia = AvaliacaoDocente::factory()->finalizada()->create();

        $this->professorAvaliado($funcionario);

        Livewire::test(ListAvaliacaoDocentes::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$minha])
            ->assertCanNotSeeTableRecords([$rascunho, $alheia]);
    }

    public function test_professor_nao_abre_avaliacao_de_colega_nem_a_propria_em_rascunho(): void
    {
        $funcionario = Funcionario::factory()->create();
        $rascunho = AvaliacaoDocente::factory()->create(['funcionario_id' => $funcionario->id]);
        $alheia = AvaliacaoDocente::factory()->finalizada()->create();

        $this->professorAvaliado($funcionario);

        Livewire::test(ViewAvaliacaoDocente::class, ['record' => $alheia->id])->assertNotFound();
        Livewire::test(ViewAvaliacaoDocente::class, ['record' => $rascunho->id])->assertNotFound();
    }

    public function test_professor_abre_a_propria_avaliacao_finalizada(): void
    {
        $funcionario = Funcionario::factory()->create();
        $minha = AvaliacaoDocente::factory()->finalizada(72.5)->create(['funcionario_id' => $funcionario->id, 'parecer' => 'Bom trabalho.']);

        $this->professorAvaliado($funcionario);

        Livewire::test(ViewAvaliacaoDocente::class, ['record' => $minha->id])
            ->assertSuccessful()
            ->assertSee('72,5%');
    }

    public function test_criar_avaliacao_pelo_formulario_cria_os_criterios_e_vai_para_as_notas(): void
    {
        $this->coordenacao();
        $funcionario = Funcionario::factory()->create();
        $ciclo = $this->ciclo();

        Livewire::test(CreateAvaliacaoDocente::class)
            ->fillForm([
                'ciclo_id' => $ciclo->id,
                'funcionario_id' => $funcionario->id,
                'data_observacao' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $avaliacao = AvaliacaoDocente::first();
        $this->assertNotNull($avaliacao);
        $this->assertCount(2, $avaliacao->notas);
    }

    public function test_formulario_recusa_a_autoavaliacao_e_nao_grava(): void
    {
        $funcionario = Funcionario::factory()->create();
        $this->coordenacao($funcionario->pessoa);

        Livewire::test(CreateAvaliacaoDocente::class)
            ->fillForm([
                'ciclo_id' => $this->ciclo()->id,
                'funcionario_id' => $funcionario->id,
                'data_observacao' => now()->toDateString(),
            ])
            ->call('create')
            ->assertNotified('Não foi possível iniciar a avaliação');

        $this->assertSame(0, AvaliacaoDocente::count());
    }

    public function test_dar_notas_pela_tela_e_salvar(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);

        $pagina = Livewire::test(EditAvaliacaoDocente::class, ['record' => $avaliacao->id]);

        foreach (array_keys($pagina->get('data.notas')) as $chave) {
            $pagina->set("data.notas.{$chave}.nota", 4);
        }

        $pagina->set('data.parecer', 'Boa mediação com a turma.')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $avaliacao->notas()->whereNull('nota')->count());
        $this->assertSame('Boa mediação com a turma.', $avaliacao->fresh()->parecer);
    }

    public function test_acao_finalizar_grava_as_notas_da_tela_e_calcula_a_pontuacao(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);

        $pagina = Livewire::test(EditAvaliacaoDocente::class, ['record' => $avaliacao->id]);

        foreach (array_keys($pagina->get('data.notas')) as $chave) {
            $pagina->set("data.notas.{$chave}.nota", 5);
        }

        $pagina->callAction('finalizar')->assertNotified('Avaliação finalizada');

        $avaliacao->refresh();
        $this->assertSame(StatusAvaliacaoDocente::Finalizada, $avaliacao->status);
        $this->assertEquals(100.0, (float) $avaliacao->pontuacao_percentual);
    }

    public function test_acao_finalizar_sem_notas_avisa_e_mantem_o_rascunho(): void
    {
        $coordenador = $this->coordenacao();
        $avaliacao = $this->servico()->iniciar($this->dados($this->ciclo(), Funcionario::factory()->create()), $coordenador);

        Livewire::test(EditAvaliacaoDocente::class, ['record' => $avaliacao->id])
            ->callAction('finalizar')
            ->assertNotified('Não foi possível finalizar');

        $this->assertSame(StatusAvaliacaoDocente::Rascunho, $avaliacao->fresh()->status);
    }

    public function test_avaliacao_finalizada_nao_abre_na_edicao(): void
    {
        $coordenador = $this->coordenacao();
        $finalizada = AvaliacaoDocente::factory()->finalizada()->create(['avaliador_user_id' => $coordenador->id]);

        Livewire::test(EditAvaliacaoDocente::class, ['record' => $finalizada->id])->assertForbidden();
    }

    public function test_acao_reabrir_na_tabela_so_para_quem_tem_a_permissao(): void
    {
        $finalizada = AvaliacaoDocente::factory()->finalizada()->create();

        $this->coordenacao();
        Livewire::test(ListAvaliacaoDocentes::class)->assertTableActionHidden('reabrir', $finalizada);

        $this->administrador();
        Livewire::test(ListAvaliacaoDocentes::class)
            ->assertTableActionVisible('reabrir', $finalizada)
            ->callTableAction('reabrir', $finalizada)
            ->assertNotified('Avaliação reaberta para correção');

        $this->assertSame(StatusAvaliacaoDocente::Rascunho, $finalizada->fresh()->status);
    }

    public function test_criar_instrumento_com_criterios_pelo_formulario(): void
    {
        $this->coordenacao();

        Livewire::test(CreateInstrumentoAvaliacaoDocente::class)
            ->fillForm([
                'nome' => 'Observação de aula',
                'escala_maxima' => 5,
                'ativo' => true,
                'criterios' => [
                    ['nome' => 'Planejamento', 'peso' => 2],
                    ['nome' => 'Mediação', 'peso' => 1],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $instrumento = InstrumentoAvaliacaoDocente::where('nome', 'Observação de aula')->first();
        $this->assertNotNull($instrumento);
        $this->assertSame(['Planejamento', 'Mediação'], $instrumento->criterios->pluck('nome')->all());
    }

    public function test_ciclo_exige_fim_depois_do_inicio(): void
    {
        $this->coordenacao();
        $instrumento = InstrumentoAvaliacaoDocente::factory()->comCriterios()->create();

        Livewire::test(CreateCicloAvaliacaoDocente::class)
            ->fillForm([
                'nome' => 'Ciclo invertido',
                'instrumento_id' => $instrumento->id,
                'data_inicio' => '2026-06-30',
                'data_fim' => '2026-02-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['data_fim']);

        $this->assertSame(0, CicloAvaliacaoDocente::count());
    }

    public function test_historico_aparece_na_ficha_do_funcionario_para_a_gestao(): void
    {
        $this->coordenacao();
        $funcionario = Funcionario::factory()->create();
        $finalizada = AvaliacaoDocente::factory()->finalizada()->create(['funcionario_id' => $funcionario->id]);
        $outroFuncionario = AvaliacaoDocente::factory()->finalizada()->create();

        Livewire::test(AvaliacoesDocenteRelationManager::class, ['ownerRecord' => $funcionario, 'pageClass' => EditFuncionario::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$finalizada])
            ->assertCanNotSeeTableRecords([$outroFuncionario]);
    }

    public function test_aba_de_avaliacoes_nao_aparece_para_quem_atua_como_professor(): void
    {
        $funcionario = Funcionario::factory()->create();
        $this->professorAvaliado($funcionario);

        $this->assertFalse(AvaliacoesDocenteRelationManager::canViewForRecord($funcionario, EditFuncionario::class));

        $this->coordenacao();
        $this->assertTrue(AvaliacoesDocenteRelationManager::canViewForRecord($funcionario, EditFuncionario::class));
    }
}
