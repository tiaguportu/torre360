<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusOmrCorrecao;
use App\Filament\Resources\Avaliacaos\Pages\CorrigirGabarito;
use App\Models\Avaliacao;
use App\Models\AvaliacaoGabarito;
use App\Models\GabaritoFolha;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\OmrCorrecao;
use App\Models\Pessoa;
use App\Models\User;
use App\Services\Omr\OmrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CriaAvaliacaoTestTrait;
use Tests\TestCase;

/**
 * A tela de conferência chama métodos públicos do Livewire com ids vindos do navegador, e as rotas de PDF resolvem
 * a avaliação direto pelo id: o escopo e a confirmação de sobrescrita precisam valer aqui, não só na interface.
 */
class OmrCorrigirGabaritoPaginaTest extends TestCase
{
    use CriaAvaliacaoTestTrait;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'coordenador', 'professor'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['CorrigirGabarito:Avaliacao', 'GerenciarGabarito:Avaliacao', 'ViewAny:Avaliacao', 'View:Avaliacao'] as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        }
    }

    private function corretor(string $papel = 'coordenador', ?Pessoa $pessoa = null): User
    {
        // As permissões do sistema resolvem pelo papel ativo (User::hasPermissionTo), não por permissão direta.
        Role::findByName($papel, 'web')->givePermissionTo([
            'CorrigirGabarito:Avaliacao', 'GerenciarGabarito:Avaliacao', 'ViewAny:Avaliacao', 'View:Avaliacao',
        ]);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($papel);

        if ($pessoa) {
            $user->pessoas()->attach($pessoa->id);
        }

        session(['active_role' => $papel]);

        return $user;
    }

    private function avaliacaoComGabarito(array $atributos = []): Avaliacao
    {
        $avaliacao = $this->criarAvaliacao(array_merge(['nota_maxima' => 10.00], $atributos));
        AvaliacaoGabarito::factory()->create(['avaliacao_id' => $avaliacao->id]);

        return $avaliacao;
    }

    private function correcao(Avaliacao $avaliacao, array $atributos = []): OmrCorrecao
    {
        return OmrCorrecao::factory()->create(array_merge([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => Matricula::factory()->create(['turma_id' => $avaliacao->turma_id])->id,
            'nota_calculada' => 8.00,
            'status' => StatusOmrCorrecao::AguardandoConferencia,
        ], $atributos));
    }

    // ─── IDOR: o id da correção vem do navegador ───────────────────────

    public function test_pagina_nao_lanca_correcao_de_outra_avaliacao(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $outra = $this->avaliacaoComGabarito();
        $correcaoAlheia = $this->correcao($outra);

        Livewire::actingAs($this->corretor())
            ->test(CorrigirGabarito::class, ['record' => $avaliacao->id])
            ->call('lancarNotaIndividual', $correcaoAlheia->id);

        $this->assertSame(0, Nota::count());
        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcaoAlheia->fresh()->status);
    }

    public function test_pagina_nao_associa_nem_descarta_correcao_de_outra_avaliacao(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $outra = $this->avaliacaoComGabarito();
        $correcaoAlheia = $this->correcao($outra, ['matricula_id' => null]);
        $alunoDaOutra = Matricula::factory()->create(['turma_id' => $outra->turma_id]);

        $pagina = Livewire::actingAs($this->corretor())
            ->test(CorrigirGabarito::class, ['record' => $avaliacao->id]);

        $pagina->call('associarMatricula', $correcaoAlheia->id, $alunoDaOutra->id)
            ->call('descartarCorrecao', $correcaoAlheia->id);

        $correcaoAlheia->refresh();
        $this->assertNull($correcaoAlheia->matricula_id);
        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcaoAlheia->status);
    }

    // ─── Sobrescrita só com confirmação explícita ──────────────────────

    public function test_nota_ja_lancada_e_diferente_nao_e_sobrescrita_sem_confirmacao(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $correcao = $this->correcao($avaliacao, ['nota_calculada' => 9.00]);

        Nota::create(['avaliacao_id' => $avaliacao->id, 'matricula_id' => $correcao->matricula_id, 'valor' => 5.00]);

        $pagina = Livewire::actingAs($this->corretor())
            ->test(CorrigirGabarito::class, ['record' => $avaliacao->id])
            ->call('lancarNotaIndividual', $correcao->id);

        $this->assertEquals(5.00, (float) Nota::where('avaliacao_id', $avaliacao->id)->value('valor'));
        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $correcao->fresh()->status);
        $pagina->assertSet("conflitos.{$correcao->id}.atual", 5.0)
            ->assertSet("conflitos.{$correcao->id}.nova", 9.0);

        // Só o botão de confirmação (sobrescrever = true) substitui a nota.
        $pagina->call('lancarNotaIndividual', $correcao->id, true);

        $this->assertEquals(9.00, (float) Nota::where('avaliacao_id', $avaliacao->id)->value('valor'));
        $this->assertSame(StatusOmrCorrecao::Confirmada, $correcao->fresh()->status);
    }

    public function test_lote_lanca_so_o_que_esta_pronto(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();

        $pronta = $this->correcao($avaliacao, ['nota_calculada' => 7.00]);

        $duplaMarcacao = $this->correcao($avaliacao, [
            'nota_calculada' => 6.00,
            'respostas' => [1 => ['numero' => 1, 'alternativa' => null, 'estado' => 'dupla']],
        ]);

        $comConflito = $this->correcao($avaliacao, ['nota_calculada' => 9.00]);
        Nota::create(['avaliacao_id' => $avaliacao->id, 'matricula_id' => $comConflito->matricula_id, 'valor' => 4.00]);

        $semAluno = $this->correcao($avaliacao, ['matricula_id' => null, 'nota_calculada' => 5.00]);

        Livewire::actingAs($this->corretor())
            ->test(CorrigirGabarito::class, ['record' => $avaliacao->id])
            ->call('lancarTodasConfirmadas');

        $this->assertSame(StatusOmrCorrecao::Confirmada, $pronta->fresh()->status);
        $this->assertEquals(7.00, (float) Nota::where('matricula_id', $pronta->matricula_id)->value('valor'));

        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $duplaMarcacao->fresh()->status, 'Dupla marcação exige conferência individual.');
        $this->assertDatabaseMissing('nota', ['matricula_id' => $duplaMarcacao->matricula_id]);

        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $comConflito->fresh()->status);
        $this->assertEquals(4.00, (float) Nota::where('matricula_id', $comConflito->matricula_id)->value('valor'), 'O lote não sobrescreve nota existente.');

        $this->assertSame(StatusOmrCorrecao::AguardandoConferencia, $semAluno->fresh()->status);
    }

    public function test_correcao_ja_lancada_nao_pode_ser_descartada(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $correcao = $this->correcao($avaliacao, ['status' => StatusOmrCorrecao::Confirmada]);

        Livewire::actingAs($this->corretor())
            ->test(CorrigirGabarito::class, ['record' => $avaliacao->id])
            ->call('descartarCorrecao', $correcao->id);

        $this->assertSame(StatusOmrCorrecao::Confirmada, $correcao->fresh()->status);
    }

    // ─── Guardas do serviço ────────────────────────────────────────────

    public function test_correcao_com_falha_de_leitura_nao_vira_nota_zero(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $correcao = $this->correcao($avaliacao, [
            'status' => StatusOmrCorrecao::Falha,
            'nota_calculada' => null,
            'mensagem_erro' => 'Marcadores não encontrados.',
        ]);

        try {
            app(OmrService::class)->lancarNota($correcao, User::factory()->create(), true);
            $this->fail('Correção com falha de leitura não pode ser lançada.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('aguardando conferência', $e->getMessage());
        }

        $this->assertSame(0, Nota::count());
    }

    public function test_lancar_duas_vezes_a_mesma_correcao_e_idempotente(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $correcao = $this->correcao($avaliacao, ['nota_calculada' => 8.50]);
        $user = User::factory()->create();
        $service = app(OmrService::class);

        $primeira = $service->lancarNota($correcao, $user, false);
        $segunda = $service->lancarNota($correcao->fresh(), $user, false);

        $this->assertTrue($primeira['sucesso']);
        $this->assertTrue($segunda['sucesso']);
        $this->assertTrue($segunda['ja_lancada']);
        $this->assertSame(1, Nota::count());
        $this->assertSame(
            1,
            Activity::where('description', 'like', '%via correção de gabarito OMR%')->count(),
            'O segundo lançamento não pode duplicar o registro de auditoria.'
        );
    }

    public function test_correcao_descartada_nao_e_lancada(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $correcao = $this->correcao($avaliacao, ['status' => StatusOmrCorrecao::Descartada]);

        $this->expectException(\InvalidArgumentException::class);

        app(OmrService::class)->lancarNota($correcao, User::factory()->create(), true);
    }

    public function test_folha_de_outra_avaliacao_nao_e_marcada_como_corrigida(): void
    {
        $avaliacao = $this->avaliacaoComGabarito();
        $outra = $this->avaliacaoComGabarito();
        $folhaDaOutra = GabaritoFolha::factory()->create(['avaliacao_id' => $outra->id]);

        // Correção legítima desta avaliação: a folha de outra avaliação não é vinculada pelo processamento.
        $correcao = $this->correcao($avaliacao, ['gabarito_folha_id' => null]);
        app(OmrService::class)->lancarNota($correcao, User::factory()->create(), true);

        $this->assertNull($folhaDaOutra->fresh()->corrigida_em);
    }

    // ─── Escopo do professor (policy, usada também pelas rotas de PDF) ─

    public function test_professor_so_gerencia_e_corrige_as_proprias_avaliacoes(): void
    {
        $pessoaProfessor = Pessoa::factory()->create();
        $professor = $this->corretor('professor', $pessoaProfessor);

        $propria = $this->avaliacaoComGabarito(['professor_id' => $pessoaProfessor->id]);
        $alheia = $this->avaliacaoComGabarito(['professor_id' => Pessoa::factory()->create()->id]);

        $this->assertTrue($professor->can('corrigirGabarito', $propria));
        $this->assertTrue($professor->can('gerenciarGabarito', $propria));
        $this->assertFalse($professor->can('corrigirGabarito', $alheia));
        $this->assertFalse($professor->can('gerenciarGabarito', $alheia));
    }

    public function test_professor_nao_baixa_folhas_de_avaliacao_de_outro_professor(): void
    {
        $pessoaProfessor = Pessoa::factory()->create();
        $professor = $this->corretor('professor', $pessoaProfessor);

        $alheia = $this->avaliacaoComGabarito(['professor_id' => Pessoa::factory()->create()->id]);
        $folhaAlheia = GabaritoFolha::factory()->create(['avaliacao_id' => $alheia->id]);

        $this->actingAs($professor)
            ->get(route('omr.folha.pdf', ['folha' => $folhaAlheia->id]))
            ->assertForbidden();

        $this->actingAs($professor)
            ->get(route('omr.folha.em_branco.pdf', ['avaliacao' => $alheia->id]))
            ->assertForbidden();

        $this->actingAs($professor)
            ->get(route('omr.folhas.turma.pdf', ['avaliacao' => $alheia->id]))
            ->assertForbidden();
    }

    public function test_coordenacao_sem_papel_de_professor_enxerga_qualquer_avaliacao(): void
    {
        $coordenador = $this->corretor('coordenador');
        $avaliacao = $this->avaliacaoComGabarito(['professor_id' => Pessoa::factory()->create()->id]);

        $this->assertTrue($coordenador->can('corrigirGabarito', $avaliacao));
        $this->assertTrue($coordenador->can('gerenciarGabarito', $avaliacao));
    }
}
