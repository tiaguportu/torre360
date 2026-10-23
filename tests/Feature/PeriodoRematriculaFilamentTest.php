<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusTurma;
use App\Filament\Resources\PeriodoRematriculas\Pages\CreatePeriodoRematricula;
use App\Filament\Resources\PeriodoRematriculas\Pages\EditPeriodoRematricula;
use App\Filament\Resources\PeriodoRematriculas\Pages\ListPeriodoRematriculas;
use App\Models\Curso;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Serie;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PeriodoRematriculaFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

    private PeriodoLetivo $origem;

    private PeriodoLetivo $destino;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleSuper = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $roleSemAcesso = Role::firstOrCreate(['name' => 'sem_acesso', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole($roleSuper);

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole($roleSemAcesso);

        $this->origem = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $this->destino = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2027',
            'data_inicio' => '2027-02-01',
            'data_fim' => '2027-12-15',
        ]);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_periodo_rematriculas(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/periodo-rematriculas')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_periodos_de_rematricula(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $campanha = PeriodoRematricula::create([
            'nome' => 'Campanha Rematrícula 2027 Oficial',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'data_inicio' => now()->subDays(2)->toDateString(),
            'data_fim' => now()->addDays(20)->toDateString(),
            'is_ativo' => true,
        ]);

        Livewire::test(ListPeriodoRematriculas::class)
            ->assertCanSeeTableRecords([$campanha])
            ->assertSee('Campanha Rematrícula 2027 Oficial')
            ->assertSee('Aberto / No Prazo');
    }

    public function test_validacao_rejeita_periodo_destino_igual_ao_de_origem(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreatePeriodoRematricula::class)
            ->fillForm([
                'nome' => 'Campanha com Períodos Iguais',
                'periodo_letivo_origem_id' => $this->origem->id,
                'periodo_letivo_destino_id' => $this->origem->id,
                'data_inicio' => '2026-11-01',
                'data_fim' => '2026-12-01',
                'is_ativo' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['periodo_letivo_destino_id' => 'different']);
    }

    public function test_validacao_impede_ativar_campanha_sem_turmas_abertas_no_destino(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        // Nenhuma turma cadastrada no destino
        Livewire::test(CreatePeriodoRematricula::class)
            ->fillForm([
                'nome' => 'Campanha Ativa Sem Turma Destino',
                'periodo_letivo_origem_id' => $this->origem->id,
                'periodo_letivo_destino_id' => $this->destino->id,
                'data_inicio' => '2026-11-01',
                'data_fim' => '2026-12-01',
                'is_ativo' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['is_ativo']);
    }

    public function test_admin_pode_criar_campanha_quando_periodo_destino_tem_turma_aberta(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $curso = Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '6º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Matutino', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);

        Turma::create([
            'nome' => '6º Ano A 2027',
            'periodo_letivo_id' => $this->destino->id,
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'status' => StatusTurma::Ativa,
        ]);

        $template = TemplateContrato::create([
            'nome' => 'Contrato Rematrícula 2027',
            'conteudo' => '<p>Contrato escolar.</p>',
            'is_padrao' => true,
        ]);

        Livewire::test(CreatePeriodoRematricula::class)
            ->fillForm([
                'nome' => 'Campanha 2027 Válida',
                'periodo_letivo_origem_id' => $this->origem->id,
                'periodo_letivo_destino_id' => $this->destino->id,
                'template_contrato_id' => $template->id,
                'data_inicio' => '2026-11-01',
                'data_fim' => '2026-12-01',
                'valor_taxa' => 500.00,
                'quantidade_parcelas_padrao' => 12,
                'valor_entrada_padrao' => 0,
                'is_ativo' => true,
                'mensagem_orientacao' => 'Orientações aos pais.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('periodo_rematriculas', [
            'nome' => 'Campanha 2027 Válida',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'is_ativo' => true,
        ]);
    }

    public function test_admin_pode_editar_campanha_de_rematricula(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $campanha = PeriodoRematricula::create([
            'nome' => 'Campanha Antiga',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'data_inicio' => '2026-10-01',
            'data_fim' => '2026-11-01',
            'is_ativo' => false,
        ]);

        Livewire::test(EditPeriodoRematricula::class, ['record' => $campanha->id])
            ->fillForm([
                'nome' => 'Campanha Atualizada 2027',
                'mensagem_orientacao' => 'Nova mensagem aos responsáveis.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $campanha->refresh();
        $this->assertEquals('Campanha Atualizada 2027', $campanha->nome);
        $this->assertEquals('Nova mensagem aos responsáveis.', $campanha->mensagem_orientacao);
    }
}
