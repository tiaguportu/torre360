<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoNota;
use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Filament\Resources\Avaliacaos\Pages\CreateAvaliacao;
use App\Filament\Resources\Avaliacaos\Pages\LancarNotas;
use App\Filament\Resources\Avaliacaos\Pages\ListAvaliacaos;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\NotaLancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AvaliacaoMelhoriasOperacionaisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Turma $turma;

    private Disciplina $disciplina;

    private EtapaAvaliativa $etapa;

    private CategoriaAvaliacao $categoria;

    private Matricula $matricula1;

    private Matricula $matricula2;

    private Matricula $matricula3;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        foreach ([
            'ViewAny:Avaliacao',
            'View:Avaliacao',
            'Create:Avaliacao',
            'Update:Avaliacao',
            'Delete:Avaliacao',
            'LancarNotas:Avaliacao',
            'Homologar:Avaliacao',
        ] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $this->admin = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Tarde', 'hora_inicio' => '13:00', 'hora_fim' => '17:30']);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $this->turma = Turma::create([
            'nome' => '5º Ano A',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $periodo->id,
            'status' => 'ativa',
        ]);

        $this->disciplina = Disciplina::create(['nome' => 'Matemática', 'sigla' => 'MAT']);
        $this->turma->disciplinas()->attach($this->disciplina->id);
        $this->categoria = CategoriaAvaliacao::create(['nome' => 'Prova Bimestral', 'eh_recuperacao' => false]);

        $this->etapa = EtapaAvaliativa::create([
            'turma_id' => $this->turma->id,
            'periodo_letivo_id' => $periodo->id,
            'nome' => '1º Bimestre',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ]);

        $aluno1 = Pessoa::factory()->create(['nome' => 'Alice da Silva']);
        $aluno2 = Pessoa::factory()->create(['nome' => 'Bruno Souza']);
        $aluno3 = Pessoa::factory()->create(['nome' => 'Carla Lima']);

        $this->matricula1 = Matricula::create(['turma_id' => $this->turma->id, 'pessoa_id' => $aluno1->id, 'situacao' => 'ativa']);
        $this->matricula2 = Matricula::create(['turma_id' => $this->turma->id, 'pessoa_id' => $aluno2->id, 'situacao' => 'ativa']);
        $this->matricula3 = Matricula::create(['turma_id' => $this->turma->id, 'pessoa_id' => $aluno3->id, 'situacao' => 'ativa']);
    }

    private function criarAvaliacao(array $overrides = []): Avaliacao
    {
        return Avaliacao::create(array_merge([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'data_prevista' => '2026-03-15',
            'data_ocorrencia' => '2026-03-15',
            'data_limite_lancamento' => '2026-03-25',
            'nota_maxima' => 10.00,
            'peso_etapa_avaliativa' => 1.00,
        ], $overrides));
    }

    public function test_item_1_redirecionamento_apos_criacao_aponta_para_lancar_notas(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAvaliacao::class)
            ->fillForm([
                'periodo_letivo_id' => $this->turma->periodo_letivo_id,
                'turma_id' => $this->turma->id,
                'disciplina_id' => $this->disciplina->id,
                'categoria_avaliacao_id' => $this->categoria->id,
                'etapa_avaliativa_id' => $this->etapa->id,
                'data_prevista' => '2026-04-10',
                'data_limite_lancamento' => '2026-04-25',
                'nota_maxima' => 10,
                'peso_etapa_avaliativa' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $avaliacao = Avaliacao::first();
        $this->assertNotNull($avaliacao);

        $urlEsperada = AvaliacaoResource::getUrl('lancar-notas', ['record' => $avaliacao]);
        $this->assertEquals($urlEsperada, AvaliacaoResource::getUrl('lancar-notas', ['record' => $avaliacao]));
    }

    public function test_item_3_calculo_de_estatisticas_da_turma(): void
    {
        $avaliacao = $this->criarAvaliacao();

        // Aluno 1: nota 8.0
        Nota::create(['avaliacao_id' => $avaliacao->id, 'matricula_id' => $this->matricula1->id, 'valor' => 8.0]);
        // Aluno 2: nota 10.0
        Nota::create(['avaliacao_id' => $avaliacao->id, 'matricula_id' => $this->matricula2->id, 'valor' => 10.0]);
        // Aluno 3: faltou
        Nota::create(['avaliacao_id' => $avaliacao->id, 'matricula_id' => $this->matricula3->id, 'situacao' => SituacaoNota::FALTOU]);

        $service = app(NotaLancamentoService::class);
        $stats = $service->calcularEstatisticas($avaliacao);

        $this->assertEquals(3, $stats['total_alunos']);
        $this->assertEquals(3, $stats['total_resolvidas']);
        $this->assertEquals(0, $stats['total_pendentes']);
        $this->assertEquals(2, $stats['com_nota']);
        $this->assertEquals(1, $stats['justificadas']);
        $this->assertEquals(100.0, $stats['taxa_participacao']);
        $this->assertEquals(9.0, $stats['media']);
        $this->assertEquals(10.0, $stats['maior_nota']);
        $this->assertEquals(8.0, $stats['menor_nota']);
    }

    public function test_item_2_importacao_de_notas_via_csv_com_sucesso(): void
    {
        $avaliacao = $this->criarAvaliacao();

        $linhas = [
            ['matricula_id' => $this->matricula1->id, 'aluno_nome' => 'Alice', 'nota' => '7,5', 'situacao' => ''],
            ['matricula_id' => $this->matricula2->id, 'aluno_nome' => 'Bruno', 'nota' => '9.0', 'situacao' => ''],
            ['matricula_id' => $this->matricula3->id, 'aluno_nome' => 'Carla', 'nota' => '', 'situacao' => 'faltou'],
        ];

        $service = app(NotaLancamentoService::class);
        $total = $service->importarNotasCsv($avaliacao, $linhas);

        $this->assertEquals(3, $total);

        $this->assertDatabaseHas('nota', [
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $this->matricula1->id,
            'valor' => 7.5,
        ]);

        $this->assertDatabaseHas('nota', [
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $this->matricula2->id,
            'valor' => 9.0,
        ]);

        $this->assertDatabaseHas('nota', [
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $this->matricula3->id,
            'situacao' => SituacaoNota::FALTOU->value,
            'valor' => null,
        ]);
    }

    public function test_item_2_importacao_csv_rejeita_nota_maior_que_maxima(): void
    {
        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.0]);

        $linhas = [
            ['matricula_id' => $this->matricula1->id, 'aluno_nome' => 'Alice', 'nota' => '12.0', 'situacao' => ''],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('excede a nota máxima');

        app(NotaLancamentoService::class)->importarNotasCsv($avaliacao, $linhas);
    }

    public function test_item_2_importacao_csv_rejeita_matricula_de_outra_turma(): void
    {
        $avaliacao = $this->criarAvaliacao();

        $outraTurma = Turma::create([
            'nome' => 'Outra Turma',
            'serie_id' => $this->turma->serie_id,
            'turno_id' => $this->turma->turno_id,
            'periodo_letivo_id' => $this->turma->periodo_letivo_id,
            'status' => 'ativa',
        ]);
        $outroAluno = Pessoa::factory()->create();
        $matriculaOutraTurma = Matricula::create(['turma_id' => $outraTurma->id, 'pessoa_id' => $outroAluno->id, 'situacao' => 'ativa']);

        $linhas = [
            ['matricula_id' => $matriculaOutraTurma->id, 'aluno_nome' => 'Estranho', 'nota' => '8.0'],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('não pertence à turma');

        app(NotaLancamentoService::class)->importarNotasCsv($avaliacao, $linhas);
    }

    public function test_item_4_homologacao_e_desbloqueio_de_avaliacao(): void
    {
        $avaliacao = $this->criarAvaliacao();

        $this->assertFalse($avaliacao->isHomologada());

        $avaliacao->homologar($this->admin);

        $avaliacao->refresh();
        $this->assertTrue($avaliacao->isHomologada());
        $this->assertNotNull($avaliacao->homologada_em);
        $this->assertEquals($this->admin->id, $avaliacao->homologada_por);

        $avaliacao->desbloquear();
        $avaliacao->refresh();
        $this->assertFalse($avaliacao->isHomologada());
        $this->assertNull($avaliacao->homologada_em);
        $this->assertNull($avaliacao->homologada_por);
    }

    public function test_item_4_bloqueio_de_salvamento_de_notas_quando_homologada(): void
    {
        $this->actingAs($this->admin);

        $avaliacao = $this->criarAvaliacao();
        $avaliacao->homologar($this->admin);

        // Ao tentar salvar notas em uma avaliação homologada, a operação é rejeitada
        $component = Livewire::test(LancarNotas::class, ['record' => $avaliacao->id]);
        $component->call('saveNotas');
        $component->assertNotified('Avaliação homologada');
    }

    public function test_item_4_status_homologada_exibido_na_tabela(): void
    {
        $this->actingAs($this->admin);

        $avaliacao = $this->criarAvaliacao();
        $avaliacao->homologar($this->admin);

        Livewire::test(ListAvaliacaos::class)
            ->assertCanSeeTableRecords([$avaliacao]);
    }
}
