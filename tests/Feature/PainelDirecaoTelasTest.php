<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\PainelDirecao;
use App\Filament\Resources\IndicadorMetas\Pages\CreateIndicadorMeta;
use App\Filament\Resources\IndicadorMetas\Pages\EditIndicadorMeta;
use App\Filament\Resources\IndicadorMetas\Pages\ListIndicadorMetas;
use App\Models\Curso;
use App\Models\IndicadorMeta;
use App\Models\Matricula;
use App\Models\PainelSnapshot;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Painel da Direção (Onda 45), telas: a página com os cartões e o filtro de unidade, as ações do cabeçalho e o cadastro das metas.
 *
 * "Hoje" é segunda-feira, 26/10/2026. Duas unidades com 3 e 2 alunos ativos (5 no total).
 */
class PainelDirecaoTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $direcao;

    private User $coordenacao;

    private User $semAcesso;

    private Unidade $central;

    private Unidade $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['direcao', 'coordenacao', 'semacesso'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['View:PainelDirecao', 'Fotografar:PainelDirecao', 'ViewAny:Matricula', 'ViewAny:Turma', 'View:RelatorioInadimplencia', 'ViewAny:IndicadorMeta', 'View:IndicadorMeta', 'Create:IndicadorMeta', 'Update:IndicadorMeta', 'Delete:IndicadorMeta'] as $nome) {
            Role::findByName('direcao')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        foreach (['View:PainelDirecao', 'ViewAny:Matricula', 'ViewAny:Turma'] as $nome) {
            Role::findByName('coordenacao')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $this->direcao = $this->usuario('direcao');
        $this->coordenacao = $this->usuario('coordenacao');
        $this->semAcesso = $this->usuario('semacesso');

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->central = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $this->norte = Unidade::create(['nome' => 'Unidade Norte', 'situacao_funcionamento' => '1']);

        foreach ([[$this->central, 3], [$this->norte, 2]] as [$unidade, $alunos]) {
            $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => "Curso {$unidade->nome}", 'nome_interno' => $unidade->nome]);
            $turma = Turma::create(['nome' => "Turma {$unidade->nome}", 'periodo_letivo_id' => $periodo->id, 'vagas_maximas' => 10, 'serie_id' => Serie::create(['curso_id' => $curso->id, 'nome' => 'Série', 'sistema_avaliacao' => 'Nota'])->id]);

            for ($i = 1; $i <= $alunos; $i++) {
                Matricula::create(['pessoa_id' => Pessoa::create(['nome' => "Aluno {$unidade->nome} {$i}"])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
            }
        }
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    // --- Página ------------------------------------------------------------------------------------------------------

    public function test_a_pagina_exige_permissao(): void
    {
        $this->actingAs($this->como($this->semAcesso))->get(PainelDirecao::getUrl())->assertForbidden();
        $this->actingAs($this->como($this->direcao))->get(PainelDirecao::getUrl())->assertOk();
    }

    public function test_mostra_os_cartoes_das_secoes_que_a_pessoa_pode_ver(): void
    {
        Livewire::actingAs($this->como($this->direcao))
            ->test(PainelDirecao::class)
            ->assertSee('Alunos e vagas')
            ->assertSee('Alunos matriculados')
            ->assertSee('Ocupação das turmas')
            ->assertSee('Financeiro')
            ->assertSee('Inadimplência em aberto')
            ->assertSee('25,0%'); // 5 alunos em 20 vagas
    }

    public function test_financeiro_nao_aparece_para_quem_nao_o_ve(): void
    {
        Livewire::actingAs($this->como($this->coordenacao))
            ->test(PainelDirecao::class)
            ->assertSee('Alunos matriculados')
            ->assertDontSee('Inadimplência em aberto')
            ->assertDontSee('Taxa de inadimplência')
            ->assertDontSee('Caixa projetado');
    }

    public function test_filtro_de_unidade_recalcula_e_avisa_do_indicador_da_escola_toda(): void
    {
        Livewire::actingAs($this->como($this->direcao))
            ->test(PainelDirecao::class)
            ->set('unidadeId', $this->norte->id)
            ->assertSee('Indicador da escola toda (não existe por unidade).')
            ->assertSee('20,0%'); // 2 alunos em 10 vagas
    }

    public function test_cartao_mostra_a_meta_o_semaforo_e_a_variacao(): void
    {
        IndicadorMeta::create(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'unidade_id' => 0, 'meta' => 10, 'tolerancia_percentual' => 10]);
        PainelSnapshot::create(['data' => '2026-09-26', 'indicador' => 'matriculas_ativas', 'unidade_id' => 0, 'valor' => 4]);

        Livewire::actingAs($this->como($this->direcao))
            ->test(PainelDirecao::class)
            ->assertSee('Meta do ano: 10')
            ->assertSee('Fora da meta')
            ->assertSee('contra 26/09 (4)')
            ->assertSee('25,0%');
    }

    public function test_sem_fotografia_o_cartao_avisa_que_ainda_nao_ha_o_que_comparar(): void
    {
        Livewire::actingAs($this->como($this->direcao))
            ->test(PainelDirecao::class)
            ->assertSee('sem fotografia para comparar ainda');
    }

    public function test_pessoa_sem_nenhum_indicador_ve_o_aviso(): void
    {
        Role::firstOrCreate(['name' => 'somente', 'guard_name' => 'web'])->givePermissionTo(Permission::firstOrCreate(['name' => 'View:PainelDirecao', 'guard_name' => 'web']));
        $so = $this->usuario('somente');

        Livewire::actingAs($this->como($so))
            ->test(PainelDirecao::class)
            ->assertSee('Você não tem acesso a nenhum indicador.');
    }

    // --- Ações -------------------------------------------------------------------------------------------------------

    public function test_atualizar_esquece_o_cache_dos_valores(): void
    {
        $tela = Livewire::actingAs($this->como($this->direcao))->test(PainelDirecao::class);
        $tela->assertSee('25,0%');

        $periodo = PeriodoLetivo::query()->firstOrFail();
        $turma = Turma::query()->where('nome', 'Turma Unidade Norte')->firstOrFail();
        Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Novo'])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        $tela->call('$refresh')->assertSee('25,0%'); // ainda em cache
        $tela->callAction('atualizar')->assertSee('30,0%');
        $this->assertNotNull($periodo);
    }

    public function test_so_quem_tem_a_permissao_guarda_a_fotografia(): void
    {
        Livewire::actingAs($this->como($this->coordenacao))->test(PainelDirecao::class)->assertActionHidden('fotografar');

        $tela = Livewire::actingAs($this->como($this->direcao))->test(PainelDirecao::class)->assertActionVisible('fotografar');
        $tela->callAction('fotografar')->assertNotified();

        $this->assertGreaterThan(0, PainelSnapshot::query()->count());
        $this->assertSame(1, PainelSnapshot::query()->whereDate('data', '2026-10-26')->where('indicador', 'matriculas_ativas')->where('unidade_id', 0)->count());
    }

    public function test_link_das_metas_so_para_quem_pode_ve_las(): void
    {
        Livewire::actingAs($this->como($this->coordenacao))->test(PainelDirecao::class)->assertActionHidden('metas');
        Livewire::actingAs($this->como($this->direcao))->test(PainelDirecao::class)->assertActionVisible('metas');
    }

    // --- Metas -------------------------------------------------------------------------------------------------------

    public function test_cadastrar_meta_da_escola_toda_guarda_unidade_zero(): void
    {
        Livewire::actingAs($this->como($this->direcao))
            ->test(CreateIndicadorMeta::class)
            ->fillForm(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'meta' => 500, 'tolerancia_percentual' => 5])
            ->call('create')
            ->assertHasNoFormErrors();

        $meta = IndicadorMeta::query()->firstOrFail();
        $this->assertSame(0, $meta->unidade_id);
        $this->assertEquals(500.0, (float) $meta->meta);
        $this->assertEquals(5.0, (float) $meta->tolerancia_percentual);
    }

    public function test_meta_repetida_para_o_mesmo_indicador_ano_e_unidade_e_recusada(): void
    {
        IndicadorMeta::create(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'unidade_id' => 0, 'meta' => 500, 'tolerancia_percentual' => 10]);

        Livewire::actingAs($this->como($this->direcao))
            ->test(CreateIndicadorMeta::class)
            ->fillForm(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'meta' => 600, 'tolerancia_percentual' => 10])
            ->call('create')
            ->assertHasFormErrors(['indicador']);

        $this->assertSame(1, IndicadorMeta::query()->count());

        // Outra unidade, ou outro ano, pode.
        Livewire::actingAs($this->como($this->direcao))
            ->test(CreateIndicadorMeta::class)
            ->fillForm(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'unidade_id' => $this->norte->id, 'meta' => 200, 'tolerancia_percentual' => 10])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, IndicadorMeta::query()->count());
    }

    public function test_editar_a_meta_da_escola_toda_mostra_o_campo_de_unidade_vazio_e_salva(): void
    {
        $meta = IndicadorMeta::create(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'unidade_id' => 0, 'meta' => 500, 'tolerancia_percentual' => 10]);

        Livewire::actingAs($this->como($this->direcao))
            ->test(EditIndicadorMeta::class, ['record' => $meta->getKey()])
            ->assertFormSet(['unidade_id' => null, 'meta' => 500])
            ->fillForm(['meta' => 520])
            ->call('save')
            ->assertHasNoFormErrors();

        $meta->refresh();
        $this->assertEquals(520.0, (float) $meta->meta);
        $this->assertSame(0, $meta->unidade_id, 'continua sendo a meta da escola toda');
    }

    public function test_lista_de_metas_mostra_o_indicador_pelo_nome_e_a_unidade(): void
    {
        IndicadorMeta::create(['indicador' => 'matriculas_ativas', 'ano' => 2026, 'unidade_id' => 0, 'meta' => 500, 'tolerancia_percentual' => 10]);
        IndicadorMeta::create(['indicador' => 'ocupacao_das_turmas', 'ano' => 2026, 'unidade_id' => $this->norte->id, 'meta' => 80, 'tolerancia_percentual' => 10]);

        Livewire::actingAs($this->como($this->direcao))
            ->test(ListIndicadorMetas::class)
            ->assertSee('Alunos e vagas — Alunos matriculados')
            ->assertSee('Escola toda')
            ->assertSee('Unidade Norte');
    }
}
