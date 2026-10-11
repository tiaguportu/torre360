<?php

namespace Tests\Feature;

use App\Enums\ConceitoHabilidade;
use App\Filament\Resources\NotaHabilidades\Pages\EditNotaHabilidade;
use App\Filament\Resources\NotaHabilidades\Pages\ListNotaHabilidades;
use App\Models\AvaliacaoHabilidade;
use App\Models\EtapaAvaliativa;
use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\NotaHabilidade;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotaHabilidadeFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

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
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_notas_de_habilidades(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/nota-habilidades')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_notas_de_habilidades(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $turma = Turma::factory()->create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
        ]);
        $aluno = Pessoa::create([
            'nome' => 'Aluno Nota Habilidade Teste',
            'data_nascimento' => '2015-05-10',
        ]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
        $etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'turma_id' => $turma->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
        ]);

        $avaliacao = AvaliacaoHabilidade::factory()->create([
            'turma_id' => $turma->id,
            'etapa_avaliativa_id' => $etapa->id,
        ]);
        $habilidade = Habilidade::create([
            'codigo' => 'EF01LP01',
            'nome' => 'Leitura Espacial',
            'descricao' => 'Reconhecer que textos são lidos e escritos da esquerda para a direita.',
        ]);

        $nota = NotaHabilidade::create([
            'avaliacao_habilidade_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'habilidade_id' => $habilidade->id,
            'conceito' => ConceitoHabilidade::REALIZA_BEM,
            'observacao' => 'Excelente domínio da leitura espacial.',
        ]);

        Livewire::test(ListNotaHabilidades::class)
            ->assertCanSeeTableRecords([$nota])
            ->assertSee('Aluno Nota Habilidade Teste');
    }

    public function test_admin_pode_editar_nota_de_habilidade(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $turma = Turma::factory()->create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
        ]);
        $aluno = Pessoa::create([
            'nome' => 'Aluno Edicao Teste',
            'data_nascimento' => '2015-05-10',
        ]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
        $etapa = EtapaAvaliativa::create([
            'nome' => '2º Bimestre',
            'turma_id' => $turma->id,
            'data_inicio' => '2026-05-01',
            'data_fim' => '2026-07-15',
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
        ]);
        $avaliacao = AvaliacaoHabilidade::factory()->create([
            'turma_id' => $turma->id,
            'etapa_avaliativa_id' => $etapa->id,
        ]);
        $habilidade = Habilidade::create([
            'codigo' => 'EF01MA01',
            'nome' => 'Números Naturais',
            'descricao' => 'Utilizar números naturais como indicador de quantidade ou de ordem.',
        ]);
        $turma->habilidades()->attach($habilidade->id);

        $nota = NotaHabilidade::create([
            'avaliacao_habilidade_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'habilidade_id' => $habilidade->id,
            'conceito' => ConceitoHabilidade::EM_DESENVOLVIMENTO,
        ]);

        Livewire::test(EditNotaHabilidade::class, ['record' => $nota->id])
            ->fillForm([
                'conceito' => ConceitoHabilidade::REALIZA_BEM->value,
                'observacao' => 'Evoluiu muito no período.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $nota->refresh();

        $this->assertEquals(ConceitoHabilidade::REALIZA_BEM, $nota->conceito);
        $this->assertEquals('Evoluiu muito no período.', $nota->observacao);
    }
}
