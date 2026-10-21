<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConceitoHabilidade;
use App\Enums\Sexo;
use App\Enums\StatusParecerDescritivo;
use App\Exceptions\ParecerDescritivoException;
use App\Filament\Portal\Pages\PortfolioAluno;
use App\Filament\Resources\ParecerDescritivos\Pages\EditParecerDescritivo;
use App\Filament\Resources\ParecerDescritivos\Pages\ListParecerDescritivos;
use App\Filament\Resources\PortfolioItems\Pages\CreatePortfolioItem;
use App\Filament\Resources\PortfolioItems\Pages\ListPortfolioItems;
use App\Models\AvaliacaoHabilidade;
use App\Models\EtapaAvaliativa;
use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\NotaHabilidade;
use App\Models\ParecerDescritivo;
use App\Models\Pessoa;
use App\Models\PortfolioItem;
use App\Models\Turma;
use App\Models\User;
use App\Services\CopilotoPedagogico\Exceptions\CopilotoIndisponivelException;
use App\Services\CopilotoPedagogico\Exceptions\ParecerIaDesabilitadoException;
use App\Services\CopilotoPedagogico\Exceptions\RespostaIaInvalidaException;
use App\Services\GeminiAgentService;
use App\Services\ParecerDescritivoIaService;
use App\Services\ParecerDescritivoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Portfólio do aluno e parecer descritivo (Onda 30): evidências com arquivos privados, parecer em três etapas
 * (rascunho, revisão, publicação), rascunho por IA com minimização de dados e Portal da Família.
 */
class PortfolioPareceresTest extends TestCase
{
    use RefreshDatabase;

    private const TEXTO_VALIDO = 'Durante o trimestre, [ALUNO] participou das atividades com curiosidade e envolvimento, registrando avanços importantes na leitura.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        RateLimiter::clear('copiloto_pedagogico_parecer_user_1');
    }

    /**
     * @param  list<string>  $recursos
     * @param  list<string>  $acoes
     */
    private function autenticarComo(string $papel, array $acoes, array $recursos = ['PortfolioItem', 'ParecerDescritivo']): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($recursos as $recurso) {
            foreach ($acoes as $acao) {
                $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:{$recurso}", 'guard_name' => 'web']));
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        $this->actingAs($user);
        session(['active_role' => $papel]);

        return $user;
    }

    private function coordenacao(): User
    {
        return $this->autenticarComo('coordenador', ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny']);
    }

    /**
     * Professor que é conselheiro da turma informada (ou de uma turma nova).
     *
     * @return array{user: User, turma: Turma}
     */
    private function professorDaTurma(?Turma $turma = null): array
    {
        $user = $this->autenticarComo('professor', ['ViewAny', 'View', 'Create', 'Update', 'Delete']);
        $pessoa = Pessoa::factory()->create();
        $user->pessoas()->attach($pessoa->id);

        $turma ??= Turma::factory()->create();
        $turma->update(['professor_conselheiro_id' => $pessoa->id]);

        return ['user' => $user, 'turma' => $turma];
    }

    private function aluno(?Turma $turma = null, string $nome = 'Lia Souza Pereira', array $pessoa = []): Matricula
    {
        $turma ??= Turma::factory()->create();

        return Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(array_merge(['nome' => $nome], $pessoa))->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
    }

    private function etapa(Turma $turma, array $atributos = []): EtapaAvaliativa
    {
        return EtapaAvaliativa::create(array_merge([
            'turma_id' => $turma->id,
            'nome' => '1º Trimestre',
            'periodo_letivo_id' => $turma->periodo_letivo_id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-05-31',
        ], $atributos));
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    // ─── Fluxo do parecer ────────────────────────────────────────────

    public function test_iniciar_cria_rascunho_e_nao_duplica_para_o_mesmo_aluno_e_etapa(): void
    {
        $autor = User::factory()->create();
        $matricula = $this->aluno();
        $etapa = $this->etapa($matricula->turma);

        $primeiro = app(ParecerDescritivoService::class)->iniciar($matricula, $etapa, $autor);
        $segundo = app(ParecerDescritivoService::class)->iniciar($matricula, $etapa, $autor);

        $this->assertSame(StatusParecerDescritivo::Rascunho, $primeiro->status);
        $this->assertSame($primeiro->id, $segundo->id);
        $this->assertSame(1, ParecerDescritivo::count());
    }

    public function test_iniciar_recusa_matricula_inativa_e_etapa_de_outra_turma(): void
    {
        $autor = User::factory()->create();
        $matricula = $this->aluno();

        $matricula->update(['situacao' => 'trancada']);
        try {
            app(ParecerDescritivoService::class)->iniciar($matricula->fresh(), $this->etapa($matricula->turma), $autor);
            $this->fail('Matrícula inativa não pode receber parecer.');
        } catch (ParecerDescritivoException $e) {
            $this->assertStringContainsString('matrícula ativa', $e->getMessage());
        }

        $matricula->update(['situacao' => 'ativa']);
        $etapaDeOutraTurma = $this->etapa(Turma::factory()->create());
        $this->expectException(ParecerDescritivoException::class);
        app(ParecerDescritivoService::class)->iniciar($matricula->fresh(), $etapaDeOutraTurma, $autor);
    }

    public function test_enviar_para_revisao_exige_texto_com_tamanho_minimo(): void
    {
        $parecer = ParecerDescritivo::factory()->create(['texto' => 'Curto demais.']);

        $this->expectException(ParecerDescritivoException::class);
        $this->expectExceptionMessage('pelo menos '.ParecerDescritivoService::TAMANHO_MINIMO);

        app(ParecerDescritivoService::class)->enviarParaRevisao($parecer, User::factory()->create());
    }

    public function test_publicar_bloqueia_marcador_de_aluno_esquecido(): void
    {
        $parecer = ParecerDescritivo::factory()->emRevisao()->create(['texto' => self::TEXTO_VALIDO]);

        try {
            app(ParecerDescritivoService::class)->publicar($parecer, User::factory()->create());
            $this->fail('Não pode publicar com o marcador [ALUNO].');
        } catch (ParecerDescritivoException $e) {
            $this->assertStringContainsString('[ALUNO]', $e->getMessage());
        }

        $this->assertSame(StatusParecerDescritivo::EmRevisao, $parecer->fresh()->status);
    }

    public function test_fluxo_completo_publica_registra_quem_e_quando_e_permite_reabrir(): void
    {
        $this->travelTo('2026-06-10 10:00:00');
        $coord = User::factory()->create();
        $service = app(ParecerDescritivoService::class);
        $parecer = ParecerDescritivo::factory()->create([
            'texto' => 'Durante o trimestre, Lia participou das atividades com curiosidade e envolvimento, registrando avanços importantes na leitura.',
        ]);

        $service->enviarParaRevisao($parecer, $coord);
        $this->assertSame(StatusParecerDescritivo::EmRevisao, $parecer->fresh()->status);

        $publicado = $service->publicar($parecer->fresh(), $coord);
        $this->assertTrue($publicado->estaPublicado());
        $this->assertSame($coord->id, $publicado->publicado_por_user_id);
        $this->assertSame('2026-06-10 10:00:00', $publicado->publicado_em->format('Y-m-d H:i:s'));

        $reaberto = $service->reabrir($publicado, $coord);
        $this->assertSame(StatusParecerDescritivo::Rascunho, $reaberto->status);
        $this->assertNull($reaberto->publicado_em);
        $this->assertNull($reaberto->publicado_por_user_id);
    }

    public function test_so_um_parecer_publicado_pode_ser_reaberto_e_so_um_rascunho_vai_para_revisao(): void
    {
        $service = app(ParecerDescritivoService::class);
        $user = User::factory()->create();
        $publicado = ParecerDescritivo::factory()->publicado()->create();

        try {
            $service->enviarParaRevisao($publicado, $user);
            $this->fail('Parecer publicado não volta para revisão.');
        } catch (ParecerDescritivoException) {
            // esperado
        }

        $this->expectException(ParecerDescritivoException::class);
        $service->reabrir(ParecerDescritivo::factory()->create(), $user);
    }

    public function test_log_de_atividade_nao_guarda_o_texto_do_parecer(): void
    {
        $parecer = ParecerDescritivo::factory()->create(['texto' => 'Texto muito pessoal do parecer que não pode ir para o log de atividades do sistema.']);
        $parecer->update(['status' => StatusParecerDescritivo::EmRevisao, 'texto' => 'Outro texto sensível alterado depois.']);

        $registros = Activity::where('log_name', 'parecer_descritivo')->get();

        $this->assertNotEmpty($registros);
        $this->assertStringNotContainsString('muito pessoal', $registros->toJson());
        $this->assertStringNotContainsString('sensível', $registros->toJson());
    }

    // ─── Permissões e escopo do professor ────────────────────────────

    public function test_professor_edita_so_parecer_de_aluno_da_propria_turma(): void
    {
        ['user' => $professor, 'turma' => $turma] = $this->professorDaTurma();
        $meu = ParecerDescritivo::factory()->create(['matricula_id' => $this->aluno($turma)->id]);
        $alheio = ParecerDescritivo::factory()->create(['matricula_id' => $this->aluno()->id]);

        $this->assertTrue($professor->can('update', $meu));
        $this->assertFalse($professor->can('update', $alheio));
        $this->assertFalse($professor->can('view', $alheio));

        Livewire::actingAs($professor)
            ->test(ListParecerDescritivos::class)
            ->assertCanSeeTableRecords([$meu])
            ->assertCanNotSeeTableRecords([$alheio]);
    }

    public function test_professor_nao_publica_e_parecer_publicado_nao_e_editado(): void
    {
        ['user' => $professor, 'turma' => $turma] = $this->professorDaTurma();
        $rascunho = ParecerDescritivo::factory()->create(['matricula_id' => $this->aluno($turma)->id]);
        $publicado = ParecerDescritivo::factory()->publicado()->create(['matricula_id' => $this->aluno($turma)->id]);

        $this->assertFalse($professor->can('publicar', $rascunho));
        $this->assertTrue($professor->can('enviarParaRevisao', $rascunho));
        $this->assertFalse($professor->can('update', $publicado));
        $this->assertFalse($professor->can('delete', $publicado));

        $coord = $this->autenticarComo('coordenador', ['ViewAny', 'View', 'Update', 'Publicar'], ['ParecerDescritivo']);
        $this->assertTrue($coord->can('publicar', $rascunho));
        $this->assertTrue($coord->can('reabrir', $publicado));
        $this->assertFalse($coord->can('update', $publicado), 'Publicado só é editado depois de reaberto.');
    }

    public function test_tela_de_edicao_publica_pelo_botao_e_a_familia_passa_a_ver(): void
    {
        $coord = $this->autenticarComo('coordenador', ['ViewAny', 'View', 'Update', 'Publicar'], ['ParecerDescritivo']);
        $parecer = ParecerDescritivo::factory()->emRevisao()->create([
            'texto' => 'Durante o trimestre, Lia participou das atividades com curiosidade e envolvimento, registrando avanços importantes na leitura.',
        ]);

        Livewire::actingAs($coord)
            ->test(EditParecerDescritivo::class, ['record' => $parecer->getRouteKey()])
            ->callAction('publicar')
            ->assertNotified('Parecer publicado');

        $this->assertTrue($parecer->fresh()->estaPublicado());
    }

    public function test_professor_nao_cadastra_evidencia_de_aluno_de_outra_turma(): void
    {
        ['user' => $professor, 'turma' => $turma] = $this->professorDaTurma();
        $meu = $this->aluno($turma);
        $alheio = $this->aluno();

        Livewire::actingAs($professor)
            ->test(CreatePortfolioItem::class)
            ->fillForm(['matricula_id' => $alheio->id, 'titulo' => 'Maquete', 'data_registro' => today()->toDateString()])
            ->call('create')
            ->assertHasFormErrors(['matricula_id']);

        $this->assertSame(0, PortfolioItem::count());

        Livewire::actingAs($professor)
            ->test(CreatePortfolioItem::class)
            ->fillForm(['matricula_id' => $meu->id, 'titulo' => 'Maquete', 'data_registro' => today()->toDateString()])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = PortfolioItem::first();
        $this->assertSame($meu->id, $item->matricula_id);
        $this->assertSame($professor->id, $item->registrado_por_user_id);
    }

    public function test_lista_do_portfolio_respeita_o_recorte_do_professor(): void
    {
        ['user' => $professor, 'turma' => $turma] = $this->professorDaTurma();
        $meu = PortfolioItem::factory()->create(['matricula_id' => $this->aluno($turma)->id]);
        $alheio = PortfolioItem::factory()->create(['matricula_id' => $this->aluno()->id]);

        $this->assertTrue($professor->can('update', $meu));
        $this->assertFalse($professor->can('update', $alheio));
        $this->assertFalse($professor->can('view', $alheio));

        Livewire::actingAs($professor)
            ->test(ListPortfolioItems::class)
            ->assertCanSeeTableRecords([$meu])
            ->assertCanNotSeeTableRecords([$alheio]);
    }

    // ─── Modelo ──────────────────────────────────────────────────────

    public function test_item_com_outros_alunos_nunca_fica_visivel_para_a_familia(): void
    {
        $item = PortfolioItem::factory()->compartilhado()->create(['mostra_outros_alunos' => true]);

        $this->assertFalse($item->fresh()->visivel_familia);
        $this->assertSame(0, PortfolioItem::query()->visiveisParaFamilia()->count());

        $item->update(['mostra_outros_alunos' => false, 'visivel_familia' => true]);
        $this->assertSame(1, PortfolioItem::query()->visiveisParaFamilia()->count());
    }

    public function test_excluir_o_item_apaga_os_arquivos_do_disco(): void
    {
        Storage::disk('local')->put('portfolio/foto.png', $this->png());
        $item = PortfolioItem::factory()->create(['anexos' => ['portfolio/foto.png']]);

        $item->delete();

        Storage::disk('local')->assertMissing('portfolio/foto.png');
    }

    // ─── Rascunho por IA ─────────────────────────────────────────────

    /**
     * @return array{parecer: ParecerDescritivo, matricula: Matricula, etapa: EtapaAvaliativa}
     */
    private function parecerComRegistros(): array
    {
        $matricula = $this->aluno(null, 'Carlos Drummond de Andrade', [
            'cpf' => '123.456.789-00',
            'telefone' => '(11) 97777-6666',
            'sexo' => Sexo::MASCULINO,
        ]);
        $etapa = $this->etapa($matricula->turma);

        $habilidade = Habilidade::factory()->create(['codigo' => 'EF03LP01', 'nome' => 'Ler e compreender textos curtos']);
        $avaliacao = AvaliacaoHabilidade::create(['turma_id' => $matricula->turma_id, 'etapa_avaliativa_id' => $etapa->id, 'professor_id' => null]);
        NotaHabilidade::create([
            'avaliacao_habilidade_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'habilidade_id' => $habilidade->id,
            'conceito' => ConceitoHabilidade::REALIZA_BEM,
            'observacao' => 'Ligar para a mãe no (11) 96666-5555 ou escrever para mae@exemplo.com.',
        ]);

        $evidencia = PortfolioItem::factory()->create([
            'matricula_id' => $matricula->id,
            'titulo' => 'Roda de leitura',
            'descricao' => 'Leu o conto em voz alta. CPF do responsável 987.654.321-00.',
            'data_registro' => '2026-03-15',
        ]);
        $evidencia->habilidades()->attach($habilidade->id);

        PortfolioItem::factory()->create([
            'matricula_id' => $matricula->id,
            'titulo' => 'Foto da turma toda',
            'mostra_outros_alunos' => true,
            'data_registro' => '2026-03-20',
        ]);
        PortfolioItem::factory()->create([
            'matricula_id' => $matricula->id,
            'titulo' => 'Trabalho fora da etapa',
            'data_registro' => '2026-09-01',
        ]);

        return ['parecer' => ParecerDescritivo::factory()->create(['matricula_id' => $matricula->id, 'etapa_avaliativa_id' => $etapa->id]), 'matricula' => $matricula, 'etapa' => $etapa];
    }

    private function respostaGemini(string $texto): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode(['texto' => $texto])]]]]]];
    }

    private function textoDaIa(): string
    {
        return str_repeat('[ALUNO] demonstrou envolvimento nas atividades de leitura e já compreende textos curtos com autonomia. ', 3)
            .'Seguimos acompanhando a escrita.';
    }

    public function test_ia_vem_desligada_por_padrao(): void
    {
        $this->assertFalse((bool) config('copiloto_pedagogico.parecer_ia_habilitado'));

        ['parecer' => $parecer] = $this->parecerComRegistros();

        $this->expectException(ParecerIaDesabilitadoException::class);
        app(ParecerDescritivoIaService::class)->gerarRascunho($parecer, User::factory()->create());
    }

    public function test_ia_nao_recebe_nome_cpf_telefone_email_nem_evidencia_com_outros_alunos(): void
    {
        config(['copiloto_pedagogico.parecer_ia_habilitado' => true]);
        ['parecer' => $parecer] = $this->parecerComRegistros();

        $payloadCapturado = null;
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->once()->andReturnUsing(function (array $payload) use (&$payloadCapturado): array {
            $payloadCapturado = $payload;

            return $this->respostaGemini($this->textoDaIa());
        });
        $this->app->instance(GeminiAgentService::class, $mock);

        $rascunho = app(ParecerDescritivoIaService::class)->gerarRascunho($parecer, User::factory()->create());
        $prompt = $payloadCapturado['contents'][0]['parts'][0]['text'];

        foreach (['Carlos', 'Drummond', 'Andrade', '123.456.789-00', '97777', '96666', 'mae@exemplo.com', '987.654.321-00', 'Foto da turma toda', 'Trabalho fora da etapa'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $prompt, "Dado indevido vazou para a IA: {$proibido}");
        }

        $this->assertStringContainsString('[ALUNO]', $prompt);
        $this->assertStringContainsString('masculino', $prompt);
        $this->assertStringContainsString('EF03LP01', $prompt);
        $this->assertStringContainsString('Roda de leitura', $prompt);
        $this->assertStringContainsString('Realiza bem', $prompt);

        // O nome (primeiro) só volta depois da resposta, no servidor.
        $this->assertStringContainsString('Carlos demonstrou envolvimento', $rascunho);
        $this->assertStringNotContainsString('[ALUNO]', $rascunho);
    }

    public function test_ia_recusa_quando_nao_ha_registros_para_embasar(): void
    {
        config(['copiloto_pedagogico.parecer_ia_habilitado' => true]);
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldNotReceive('callGeminiApi');
        $this->app->instance(GeminiAgentService::class, $mock);

        $parecer = ParecerDescritivo::factory()->create();

        $this->expectException(CopilotoIndisponivelException::class);
        app(ParecerDescritivoIaService::class)->gerarRascunho($parecer, User::factory()->create());
    }

    public function test_ia_descarta_resposta_curta_demais_apos_uma_nova_tentativa(): void
    {
        config(['copiloto_pedagogico.parecer_ia_habilitado' => true]);
        ['parecer' => $parecer] = $this->parecerComRegistros();

        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->twice()->andReturn($this->respostaGemini('Curto.'));
        $this->app->instance(GeminiAgentService::class, $mock);

        $this->expectException(RespostaIaInvalidaException::class);
        app(ParecerDescritivoIaService::class)->gerarRascunho($parecer, User::factory()->create());
    }

    public function test_rascunho_de_ia_nunca_e_publicado_sozinho(): void
    {
        config(['copiloto_pedagogico.parecer_ia_habilitado' => true]);
        ['parecer' => $parecer] = $this->parecerComRegistros();

        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->once()->andReturn($this->respostaGemini($this->textoDaIa()));
        $this->app->instance(GeminiAgentService::class, $mock);

        app(ParecerDescritivoIaService::class)->gerarRascunho($parecer, User::factory()->create());

        $this->assertSame(StatusParecerDescritivo::Rascunho, $parecer->fresh()->status);
        $this->assertNull($parecer->fresh()->publicado_em);
    }

    // ─── Portal da Família ───────────────────────────────────────────

    /**
     * @return array{user: User, matricula: Matricula}
     */
    private function familia(Turma $turma, string $nomeAluno): array
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $responsavel = Pessoa::factory()->create();
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');

        $aluno = Pessoa::factory()->create(['nome' => $nomeAluno]);
        $user->pessoas()->first()->alunos()->attach($aluno->id);

        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        return ['user' => $user, 'matricula' => $matricula];
    }

    public function test_portal_mostra_so_parecer_publicado_do_proprio_filho(): void
    {
        $turma = Turma::factory()->create();
        $familia = $this->familia($turma, 'Lia Souza');
        $colega = $this->aluno($turma, 'Colega Reservado');

        ParecerDescritivo::factory()->publicado()->create(['matricula_id' => $familia['matricula']->id, 'texto' => 'Parecer publicado da Lia com avanços na leitura.']);
        ParecerDescritivo::factory()->create(['matricula_id' => $familia['matricula']->id, 'texto' => 'Rascunho que a família não pode ver.']);
        ParecerDescritivo::factory()->emRevisao()->create(['matricula_id' => $familia['matricula']->id, 'texto' => 'Texto em revisão que a família não pode ver.']);
        ParecerDescritivo::factory()->publicado()->create(['matricula_id' => $colega->id, 'texto' => 'Parecer publicado de outro aluno.']);

        Livewire::actingAs($familia['user'])
            ->test(PortfolioAluno::class)
            ->assertSee('Parecer publicado da Lia')
            ->assertDontSee('Rascunho que a família')
            ->assertDontSee('Texto em revisão')
            ->assertDontSee('Parecer publicado de outro aluno')
            ->assertDontSee('Colega Reservado');
    }

    public function test_portal_mostra_so_evidencia_compartilhada_sem_outros_alunos(): void
    {
        $turma = Turma::factory()->create();
        $familia = $this->familia($turma, 'Lia Souza');
        $habilidade = Habilidade::factory()->create(['codigo' => 'EF03LP01', 'nome' => 'Ler textos curtos']);

        $visivel = PortfolioItem::factory()->compartilhado()->create(['matricula_id' => $familia['matricula']->id, 'titulo' => 'Maquete do vulcão']);
        $visivel->habilidades()->attach($habilidade->id);
        PortfolioItem::factory()->create(['matricula_id' => $familia['matricula']->id, 'titulo' => 'Registro interno do professor']);
        PortfolioItem::factory()->compartilhado()->create(['matricula_id' => $familia['matricula']->id, 'titulo' => 'Foto coletiva da turma', 'mostra_outros_alunos' => true]);
        PortfolioItem::factory()->compartilhado()->create(['matricula_id' => $this->aluno($turma)->id, 'titulo' => 'Trabalho de outro aluno']);

        Livewire::actingAs($familia['user'])
            ->test(PortfolioAluno::class)
            ->assertSee('Maquete do vulcão')
            ->assertSee('EF03LP01')
            ->assertDontSee('Registro interno do professor')
            ->assertDontSee('Foto coletiva da turma')
            ->assertDontSee('Trabalho de outro aluno');
    }

    // ─── Entrega dos arquivos ────────────────────────────────────────

    public function test_familia_baixa_anexo_compartilhado_do_proprio_filho_com_cabecalhos_seguros(): void
    {
        $turma = Turma::factory()->create();
        $familia = $this->familia($turma, 'Lia Souza');
        Storage::disk('local')->put('portfolio/vulcao.png', $this->png());
        $item = PortfolioItem::factory()->compartilhado()->create(['matricula_id' => $familia['matricula']->id, 'anexos' => ['portfolio/vulcao.png']]);

        $resposta = $this->actingAs($familia['user'])->get(route('portfolio.anexo', ['item' => $item->id, 'indice' => 0]));

        $resposta->assertOk();
        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('image/png', (string) $resposta->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $resposta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $resposta->headers->get('Cache-Control'));
    }

    public function test_familia_nao_alcanca_anexo_nao_compartilhado_coletivo_ou_de_outro_aluno(): void
    {
        $turma = Turma::factory()->create();
        $familia = $this->familia($turma, 'Lia Souza');
        Storage::disk('local')->put('portfolio/a.png', $this->png());

        $naoCompartilhado = PortfolioItem::factory()->create(['matricula_id' => $familia['matricula']->id, 'anexos' => ['portfolio/a.png']]);
        $coletivo = PortfolioItem::factory()->create(['matricula_id' => $familia['matricula']->id, 'anexos' => ['portfolio/a.png'], 'mostra_outros_alunos' => true, 'visivel_familia' => true]);
        $deOutro = PortfolioItem::factory()->compartilhado()->create(['matricula_id' => $this->aluno($turma)->id, 'anexos' => ['portfolio/a.png']]);

        foreach ([$naoCompartilhado, $coletivo, $deOutro] as $item) {
            $this->actingAs($familia['user'])
                ->get(route('portfolio.anexo', ['item' => $item->id, 'indice' => 0]))
                ->assertForbidden();
        }
    }

    public function test_anexo_inexistente_retorna_404_e_visitante_nao_autenticado_vai_para_o_login(): void
    {
        $this->coordenacao();
        $item = PortfolioItem::factory()->create(['anexos' => ['portfolio/nao-existe.png']]);

        $this->get(route('portfolio.anexo', ['item' => $item->id, 'indice' => 0]))->assertNotFound();
        $this->get(route('portfolio.anexo', ['item' => $item->id, 'indice' => 7]))->assertNotFound();

        auth()->logout();
        $this->get(route('portfolio.anexo', ['item' => $item->id, 'indice' => 0]))->assertRedirect();
    }

    public function test_professor_so_baixa_anexo_de_aluno_da_propria_turma(): void
    {
        ['user' => $professor, 'turma' => $turma] = $this->professorDaTurma();
        Storage::disk('local')->put('portfolio/a.png', $this->png());

        $meu = PortfolioItem::factory()->create(['matricula_id' => $this->aluno($turma)->id, 'anexos' => ['portfolio/a.png']]);
        $alheio = PortfolioItem::factory()->create(['matricula_id' => $this->aluno()->id, 'anexos' => ['portfolio/a.png']]);

        $this->actingAs($professor)->get(route('portfolio.anexo', ['item' => $meu->id, 'indice' => 0]))->assertOk();
        $this->actingAs($professor)->get(route('portfolio.anexo', ['item' => $alheio->id, 'indice' => 0]))->assertForbidden();
    }
}
