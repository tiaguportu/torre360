<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Alergeno;
use App\Enums\TipoRefeicaoCardapio;
use App\Filament\Portal\Pages\CardapioEscolar;
use App\Filament\Resources\Cardapios\Pages\CreateCardapio;
use App\Filament\Resources\Cardapios\Pages\ListCardapios;
use App\Filament\Resources\Cardapios\Pages\RestricoesAlimentares;
use App\Models\Cardapio;
use App\Models\Curso;
use App\Models\FichaMedica;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CardapioAlergiaService;
use App\Services\CardapioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cardápio e alergias (Onda 40): cardápio por data com alérgenos declarados, cruzamento com as restrições
 * alimentares da ficha médica (em três níveis de certeza), publicação e Portal da Família.
 */
class CardapioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00')); // quarta-feira
    }

    /**
     * @param  list<string>  $acoes
     */
    private function autenticarComo(string $papel, array $acoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($acoes as $acao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:Cardapio", 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        $this->actingAs($user);
        session(['active_role' => $papel]);

        return $user;
    }

    private function secretaria(): User
    {
        return $this->autenticarComo('secretaria', ['ViewAny', 'View', 'Create', 'Update', 'Publicar', 'Delete', 'DeleteAny']);
    }

    private function cozinha(): User
    {
        return $this->autenticarComo('admin', ['ViewAny', 'View', 'VerAlergicos']);
    }

    private function servico(): CardapioAlergiaService
    {
        return app(CardapioAlergiaService::class);
    }

    /**
     * @return array{unidade: Unidade, turma: Turma}
     */
    private function turmaDaUnidade(?Unidade $unidade = null): array
    {
        $unidade ??= Unidade::create(['nome' => 'Unidade '.uniqid(), 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '3º Ano', 'sistema_avaliacao' => 'Nota']);

        return ['unidade' => $unidade, 'turma' => Turma::factory()->create(['serie_id' => $serie->id])];
    }

    /**
     * Aluno matriculado, com ficha médica e restrição alimentar.
     *
     * @param  array<string, mixed>  $ficha
     */
    private function aluno(Turma $turma, string $nome, array $ficha = [], string $situacao = 'ativa'): Matricula
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nome]);
        FichaMedica::create(array_merge(['pessoa_id' => $pessoa->id], $ficha));

        return Matricula::factory()->create(['pessoa_id' => $pessoa->id, 'turma_id' => $turma->id, 'situacao' => $situacao]);
    }

    // ─── Cruzamento ficha × alérgenos ────────────────────────────────

    public function test_campo_estruturado_da_ficha_e_confirmado(): void
    {
        $pessoa = Pessoa::factory()->create();
        $ficha = FichaMedica::create(['pessoa_id' => $pessoa->id, 'has_alergia_lactose' => true, 'has_alergia_amendoim' => true]);

        $analise = $this->servico()->analisarFicha($ficha);

        $this->assertEqualsCanonicalizing([Alergeno::Lactose, Alergeno::Amendoim], $analise['confirmados']);
    }

    public function test_texto_livre_com_acento_e_maiuscula_cita_o_alergeno(): void
    {
        $pessoa = Pessoa::factory()->create();
        $ficha = FichaMedica::create(['pessoa_id' => $pessoa->id, 'outras_alergias_alimentares' => 'Intolerância a LEITE, não pode comer OVOS nem camarão.']);

        $analise = $this->servico()->analisarFicha($ficha);

        $this->assertEqualsCanonicalizing([Alergeno::Lactose, Alergeno::Ovo, Alergeno::FrutosDoMar], $analise['textuais']);
        $this->assertTrue($analise['tem_texto']);
    }

    public function test_palavra_parecida_nao_casa_por_engano(): void
    {
        $pessoa = Pessoa::factory()->create();
        $ficha = FichaMedica::create(['pessoa_id' => $pessoa->id, 'observacoes_alimentares' => 'Prefere comida do povo, nada de novo no prato; gosta de soja? não sei.']);

        $textuais = $this->servico()->analisarFicha($ficha)['textuais'];

        $this->assertNotContains(Alergeno::Ovo, $textuais, '"povo" e "novo" não são "ovo".');
        $this->assertContains(Alergeno::Soja, $textuais);
    }

    public function test_conflito_confirmado_prevalece_sobre_o_possivel_do_mesmo_alergeno(): void
    {
        $pessoa = Pessoa::factory()->create();
        $ficha = FichaMedica::create(['pessoa_id' => $pessoa->id, 'has_alergia_lactose' => true, 'outras_alergias_alimentares' => 'leite e ovo']);

        $conflitos = $this->servico()->conflitosDaFicha($ficha, [Alergeno::Lactose, Alergeno::Ovo, Alergeno::Peixe]);

        $this->assertSame([Alergeno::Lactose], $conflitos['confirmados']);
        $this->assertSame([Alergeno::Ovo], $conflitos['possiveis']);
    }

    public function test_sem_ficha_ou_sem_alergeno_declarado_nao_ha_conflito(): void
    {
        $this->assertSame(['confirmados' => [], 'possiveis' => []], $this->servico()->conflitosDaFicha(null, [Alergeno::Lactose]));

        $pessoa = Pessoa::factory()->create();
        $ficha = FichaMedica::create(['pessoa_id' => $pessoa->id, 'has_alergia_gluten' => true]);

        $this->assertSame(['confirmados' => [], 'possiveis' => []], $this->servico()->conflitosDaFicha($ficha, []));
    }

    // ─── Alunos afetados por refeição ────────────────────────────────

    public function test_alunos_afetados_separa_confirmados_possiveis_e_a_conferir(): void
    {
        ['turma' => $turma] = $this->turmaDaUnidade();
        $this->aluno($turma, 'Ana Confirmada', ['has_alergia_lactose' => true]);
        $this->aluno($turma, 'Bruno Possivel', ['outras_alergias_alimentares' => 'alergia a ovo']);
        $this->aluno($turma, 'Carla Conferir', ['outras_alergias_alimentares' => 'intolerância a banana']);
        $this->aluno($turma, 'Davi Sem Restricao', ['tipo_sanguineo' => 'O+']);
        $this->aluno($turma, 'Eva Inativa', ['has_alergia_lactose' => true], 'cancelada');

        $cardapio = Cardapio::factory()->comAlergenos([Alergeno::Lactose, Alergeno::Ovo])->create();

        $afetados = $this->servico()->alunosAfetados($cardapio);

        $this->assertSame(['Ana Confirmada'], array_column($afetados['confirmados'], 'aluno'));
        $this->assertSame(['Lactose / leite'], $afetados['confirmados'][0]['alergenos']);
        $this->assertSame(['Bruno Possivel'], array_column($afetados['possiveis'], 'aluno'));
        $this->assertSame(['Carla Conferir'], array_column($afetados['conferir'], 'aluno'));
    }

    public function test_refeicao_sem_alergeno_lista_so_quem_tem_texto_a_conferir(): void
    {
        ['turma' => $turma] = $this->turmaDaUnidade();
        $this->aluno($turma, 'Ana Confirmada', ['has_alergia_lactose' => true]);
        $this->aluno($turma, 'Carla Conferir', ['observacoes_alimentares' => 'não come tomate']);

        $afetados = $this->servico()->alunosAfetados(Cardapio::factory()->create());

        $this->assertSame([], $afetados['confirmados']);
        $this->assertSame(['Carla Conferir'], array_column($afetados['conferir'], 'aluno'));
    }

    public function test_cardapio_de_uma_unidade_so_alcanca_alunos_dela(): void
    {
        ['unidade' => $unidadeA, 'turma' => $turmaA] = $this->turmaDaUnidade();
        ['turma' => $turmaB] = $this->turmaDaUnidade();
        $this->aluno($turmaA, 'Aluno da A', ['has_alergia_lactose' => true]);
        $this->aluno($turmaB, 'Aluno da B', ['has_alergia_lactose' => true]);

        $cardapioA = Cardapio::factory()->comAlergenos([Alergeno::Lactose])->create(['unidade_id' => $unidadeA->id]);
        $cardapioGeral = Cardapio::factory()->comAlergenos([Alergeno::Lactose])->create(['tipo_refeicao' => TipoRefeicaoCardapio::Jantar]);

        $this->assertSame(['Aluno da A'], array_column($this->servico()->alunosAfetados($cardapioA)['confirmados'], 'aluno'));
        $this->assertCount(2, $this->servico()->alunosAfetados($cardapioGeral)['confirmados']);
    }

    // ─── Duplicar semana ─────────────────────────────────────────────

    public function test_duplicar_semana_copia_como_rascunho_e_preserva_o_que_ja_existe(): void
    {
        $secretaria = $this->secretaria();
        $segunda = Carbon::parse('2026-10-19');
        $proxima = Carbon::parse('2026-10-26');

        Cardapio::factory()->comAlergenos([Alergeno::Ovo])->create(['data' => $segunda->toDateString(), 'tipo_refeicao' => TipoRefeicaoCardapio::Almoco, 'descricao' => 'Omelete']);
        Cardapio::factory()->create(['data' => $segunda->copy()->addDays(2)->toDateString(), 'tipo_refeicao' => TipoRefeicaoCardapio::Almoco, 'descricao' => 'Macarronada']);
        Cardapio::factory()->create(['data' => $proxima->toDateString(), 'tipo_refeicao' => TipoRefeicaoCardapio::Almoco, 'descricao' => 'Já planejado']);

        $criadas = app(CardapioService::class)->duplicarSemana($segunda, $proxima->copy()->addDays(3), null, $secretaria);

        $this->assertSame(1, $criadas, 'Só a quarta-feira é nova; a segunda já existia no destino.');
        $copia = Cardapio::whereDate('data', '2026-10-28')->first();
        $this->assertSame('Macarronada', $copia->descricao);
        $this->assertFalse($copia->publicado);
        $this->assertSame('Já planejado', Cardapio::whereDate('data', '2026-10-26')->value('descricao'));
    }

    public function test_duplicar_para_a_mesma_semana_e_recusado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CardapioService::class)->duplicarSemana(Carbon::parse('2026-10-19'), Carbon::parse('2026-10-23'), null, $this->secretaria());
    }

    // ─── Policy ──────────────────────────────────────────────────────

    public function test_ver_alergicos_e_permissao_a_parte_da_gestao_do_cardapio(): void
    {
        $secretaria = $this->secretaria();
        $this->assertFalse($secretaria->can('verAlergicos', Cardapio::class));
        $this->assertTrue($secretaria->can('publicar', Cardapio::factory()->create()));

        $cozinha = $this->cozinha();
        $this->assertTrue($cozinha->can('verAlergicos', Cardapio::class));
        $this->assertFalse($cozinha->can('create', Cardapio::class));
    }

    // ─── Telas do painel ─────────────────────────────────────────────

    public function test_criar_refeicao_registra_quem_criou_e_recusa_duplicata(): void
    {
        $secretaria = $this->secretaria();

        Livewire::test(CreateCardapio::class)
            ->fillForm([
                'data' => '2026-10-22',
                'tipo_refeicao' => TipoRefeicaoCardapio::Almoco->value,
                'descricao' => 'Arroz, feijão e frango',
                'alergenos' => [Alergeno::Ovo->value],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $cardapio = Cardapio::first();
        $this->assertSame($secretaria->id, $cardapio->criado_por_user_id);
        $this->assertSame([Alergeno::Ovo], $cardapio->alergenosEnum());

        Livewire::test(CreateCardapio::class)
            ->fillForm([
                'data' => '2026-10-22',
                'tipo_refeicao' => TipoRefeicaoCardapio::Almoco->value,
                'descricao' => 'Outro almoço',
            ])
            ->call('create')
            ->assertHasFormErrors(['tipo_refeicao']);

        $this->assertSame(1, Cardapio::count());
    }

    public function test_quem_nao_pode_publicar_nao_consegue_publicar_pelo_formulario(): void
    {
        $this->autenticarComo('professor', ['ViewAny', 'View', 'Create']);

        Livewire::test(CreateCardapio::class)
            ->fillForm([
                'data' => '2026-10-22',
                'tipo_refeicao' => TipoRefeicaoCardapio::Almoco->value,
                'descricao' => 'Sopa',
                'publicado' => true,
            ])
            ->call('create');

        $this->assertFalse(Cardapio::firstOrFail()->publicado);
    }

    public function test_acao_de_publicar_so_aparece_com_permissao_e_alterna(): void
    {
        $cardapio = Cardapio::factory()->rascunho()->create(['data' => '2026-10-22']);

        $this->autenticarComo('professor', ['ViewAny', 'View']);
        Livewire::test(ListCardapios::class)->assertTableActionHidden('alternarPublicacao', $cardapio);

        $this->secretaria();
        Livewire::test(ListCardapios::class)
            ->assertTableActionVisible('alternarPublicacao', $cardapio)
            ->callTableAction('alternarPublicacao', $cardapio);

        $this->assertTrue($cardapio->fresh()->publicado);
    }

    public function test_listagem_mostra_a_semana_atual_e_as_proximas_por_padrao(): void
    {
        $this->secretaria();
        $daSemana = Cardapio::factory()->create(['data' => '2026-10-22']);
        $antiga = Cardapio::factory()->create(['data' => '2026-09-01']);

        Livewire::test(ListCardapios::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$daSemana])
            ->assertCanNotSeeTableRecords([$antiga]);
    }

    public function test_restricoes_alimentares_exige_a_permissao_e_registra_a_consulta(): void
    {
        ['turma' => $turma] = $this->turmaDaUnidade();
        $this->aluno($turma, 'Ana Confirmada', ['has_alergia_lactose' => true]);
        Cardapio::factory()->comAlergenos([Alergeno::Lactose])->create(['data' => '2026-10-21']);

        $this->secretaria();
        Livewire::test(RestricoesAlimentares::class)->assertForbidden();

        $this->cozinha();
        Livewire::test(RestricoesAlimentares::class)
            ->assertSuccessful()
            ->assertSee('Ana Confirmada')
            ->assertSee('Lactose / leite');

        $log = Activity::where('log_name', 'cardapio')->where('description', 'Consulta às restrições alimentares dos alunos.')->first();
        $this->assertNotNull($log, 'A consulta a dado de saúde precisa ficar registrada.');
        $this->assertStringNotContainsString('Ana', $log->toJson(), 'O log não guarda o nome de quem tem restrição.');
    }

    // ─── Portal da Família ───────────────────────────────────────────

    /**
     * @return array{user: User, aluno: Pessoa, matricula: Matricula}
     */
    private function familia(Turma $turma, string $nomeAluno, array $ficha = []): array
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $responsavel = Pessoa::factory()->create();
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');

        $aluno = Pessoa::factory()->create(['nome' => $nomeAluno]);
        $user->pessoas()->first()->alunos()->attach($aluno->id);
        FichaMedica::create(array_merge(['pessoa_id' => $aluno->id], $ficha));

        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        return compact('user', 'aluno', 'matricula');
    }

    public function test_portal_mostra_so_o_que_esta_publicado(): void
    {
        ['turma' => $turma] = $this->turmaDaUnidade();
        $familia = $this->familia($turma, 'Lia Souza');

        Cardapio::factory()->create(['data' => '2026-10-21', 'descricao' => 'Strogonoff de frango']);
        Cardapio::factory()->rascunho()->create(['data' => '2026-10-22', 'descricao' => 'Prato ainda em rascunho']);

        Livewire::actingAs($familia['user'])
            ->test(CardapioEscolar::class)
            ->assertSee('Strogonoff de frango')
            ->assertDontSee('Prato ainda em rascunho');
    }

    public function test_portal_alerta_a_familia_so_para_a_restricao_do_proprio_filho(): void
    {
        ['turma' => $turma] = $this->turmaDaUnidade();
        $alergico = $this->familia($turma, 'Lia Souza', ['has_alergia_lactose' => true]);
        $semRestricao = $this->familia($turma, 'Davi Lima');

        Cardapio::factory()->comAlergenos([Alergeno::Lactose])->create(['data' => '2026-10-21', 'descricao' => 'Macarrão ao molho branco']);

        Livewire::actingAs($alergico['user'])
            ->test(CardapioEscolar::class)
            ->assertSee('Macarrão ao molho branco')
            ->assertSee('restrição registrada na ficha de Lia');

        Livewire::actingAs($semRestricao['user'])
            ->test(CardapioEscolar::class)
            ->assertSee('Macarrão ao molho branco')
            ->assertSee('Contém: Lactose / leite')
            ->assertDontSee('restrição registrada na ficha')
            ->assertDontSee('Lia');
    }

    public function test_portal_respeita_a_unidade_do_aluno_e_navega_entre_semanas(): void
    {
        ['unidade' => $unidadeA, 'turma' => $turmaA] = $this->turmaDaUnidade();
        ['unidade' => $unidadeB] = $this->turmaDaUnidade();
        $familia = $this->familia($turmaA, 'Lia Souza');

        Cardapio::factory()->create(['data' => '2026-10-21', 'unidade_id' => $unidadeA->id, 'descricao' => 'Cardápio da unidade A']);
        Cardapio::factory()->create(['data' => '2026-10-21', 'tipo_refeicao' => TipoRefeicaoCardapio::Jantar, 'unidade_id' => $unidadeB->id, 'descricao' => 'Cardápio da unidade B']);
        Cardapio::factory()->create(['data' => '2026-10-28', 'descricao' => 'Cardápio da semana seguinte']);

        Livewire::actingAs($familia['user'])
            ->test(CardapioEscolar::class)
            ->assertSee('Cardápio da unidade A')
            ->assertDontSee('Cardápio da unidade B')
            ->assertDontSee('Cardápio da semana seguinte')
            ->call('proximaSemana')
            ->assertSee('Cardápio da semana seguinte')
            ->assertDontSee('Cardápio da unidade A');
    }

    public function test_portal_nao_vaza_restricao_de_aluno_que_nao_e_da_familia(): void
    {
        ['turma' => $turma] = $this->turmaDaUnidade();
        $this->aluno($turma, 'Colega Alergico', ['has_alergia_lactose' => true, 'outras_alergias_alimentares' => 'segredo da família']);
        $familia = $this->familia($turma, 'Lia Souza');

        Cardapio::factory()->comAlergenos([Alergeno::Lactose])->create(['data' => '2026-10-21']);

        Livewire::actingAs($familia['user'])
            ->test(CardapioEscolar::class)
            ->assertDontSee('Colega Alergico')
            ->assertDontSee('segredo da família');
    }
}
