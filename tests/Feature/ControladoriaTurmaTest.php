<?php

namespace Tests\Feature;

use App\Filament\Pages\ControladoriaTurmas;
use App\Models\Curso;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ControladoriaTurmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ControladoriaTurmaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $perms = [
            'View:ControladoriaTurmas',
            'Manage:ControladoriaTurmas',
        ];

        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function criarTurma(array $atributos = []): Turma
    {
        $unidade = Unidade::create([
            'nome' => 'Unidade Teste',
            'situacao_funcionamento' => '1',
        ]);

        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Médio',
            'nome_interno' => 'Ensino Médio',
        ]);

        $serie = Serie::create([
            'curso_id' => $curso->id,
            'nome' => '1º Ano',
            'sistema_avaliacao' => 'Nota',
        ]);

        $turno = Turno::firstOrCreate(
            ['nome' => 'Matutino'],
            ['hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']
        );
        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
        ]);

        return Turma::create(array_merge([
            'nome' => '1º Ano A',
            'codigo' => 'TURMA-1A',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $periodo->id,
            'vagas_maximas' => 30,
            'mensalidade_base' => 1000.00,
            'custo_docente_mensal' => 8000.00,
            'custo_operacional_rateado' => 2000.00,
            'meta_margem_lucro' => 20.00,
        ], $atributos));
    }

    public function test_calculo_metricas_turma_lucrativa(): void
    {
        $turma = $this->criarTurma([
            'vagas_maximas' => 30,
            'mensalidade_base' => 1000.00,
            'custo_docente_mensal' => 8000.00,
            'custo_operacional_rateado' => 2000.00,
        ]);

        // Simula 20 matrículas ativas
        for ($i = 0; $i < 20; $i++) {
            $pessoa = Pessoa::create(['nome' => "Aluno {$i}"]);
            $turma->matriculas()->create([
                'pessoa_id' => $pessoa->id,
                'situacao' => 'ativa',
                'data_ativacao' => now()->subDays(10),
            ]);
        }

        $service = app(ControladoriaTurmaService::class);
        $metricas = $service->calcularMetricas($turma);

        $this->assertEquals(20, $metricas['alunos_ativos']);
        $this->assertEquals(30, $metricas['vagas_maximas']);
        $this->assertEquals(66.7, $metricas['taxa_ocupacao']);
        $this->assertEquals(20000.00, $metricas['receita_bruta']);
        $this->assertEquals(20000.00, $metricas['receita_liquida']);
        $this->assertEquals(1000.00, $metricas['ticket_medio']);
        $this->assertEquals(10000.00, $metricas['custo_total']);
        $this->assertEquals(10000.00, $metricas['resultado_mensal']);
        $this->assertEquals(50.0, $metricas['margem_percentual']);
        $this->assertEquals(10, $metricas['ponto_equilibrio_alunos']); // 10.000 / 1000 = 10 alunos
        $this->assertEquals(10, $metricas['saldo_alunos_equilibrio']); // 20 - 10 = +10 alunos
        $this->assertEquals('lucrativa', $metricas['status']);
    }

    public function test_calculo_metricas_turma_deficitaria(): void
    {
        $turma = $this->criarTurma([
            'vagas_maximas' => 25,
            'mensalidade_base' => 800.00,
            'custo_docente_mensal' => 9000.00,
            'custo_operacional_rateado' => 1000.00,
        ]);

        // Apenas 5 alunos
        for ($i = 0; $i < 5; $i++) {
            $pessoa = Pessoa::create(['nome' => "Aluno D {$i}"]);
            $turma->matriculas()->create([
                'pessoa_id' => $pessoa->id,
                'situacao' => 'ativa',
                'data_ativacao' => now()->subDays(10),
            ]);
        }

        $service = app(ControladoriaTurmaService::class);
        $metricas = $service->calcularMetricas($turma);

        $this->assertEquals(5, $metricas['alunos_ativos']);
        $this->assertEquals(4000.00, $metricas['receita_liquida']);
        $this->assertEquals(10000.00, $metricas['custo_total']);
        $this->assertEquals(-6000.00, $metricas['resultado_mensal']);
        $this->assertEquals(13, $metricas['ponto_equilibrio_alunos']); // ceil(10000 / 800) = 13 alunos
        $this->assertEquals(-8, $metricas['saldo_alunos_equilibrio']); // 5 - 13 = -8 alunos
        $this->assertEquals('deficitaria', $metricas['status']);
    }

    public function test_simulador_de_cenarios_de_turma(): void
    {
        $turma = $this->criarTurma([
            'mensalidade_base' => 1000.00,
            'custo_docente_mensal' => 7000.00,
            'custo_operacional_rateado' => 1000.00,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $pessoa = Pessoa::create(['nome' => "Aluno S {$i}"]);
            $turma->matriculas()->create([
                'pessoa_id' => $pessoa->id,
                'situacao' => 'ativa',
                'data_ativacao' => now()->subDays(5),
            ]);
        }

        $service = app(ControladoriaTurmaService::class);
        $cenario = $service->simularCenario($turma, novosAlunos: 3, reajusteMensalidadePct: 10.0);

        // Atual: 10 alunos a 1000 = 10.000 receita. Custo = 8.000. Resultado = 2.000
        $this->assertEquals(2000.00, $cenario['atual']['resultado_mensal']);

        // Simulado: 13 alunos a 1100 = 14.300 receita. Custo = 8.000. Resultado = 6.300
        $this->assertEquals(13, $cenario['simulado']['alunos']);
        $this->assertEquals(1100.00, $cenario['simulado']['ticket_medio']);
        $this->assertEquals(14300.00, $cenario['simulado']['receita_liquida']);
        $this->assertEquals(6300.00, $cenario['simulado']['resultado_mensal']);
        $this->assertEquals(4300.00, $cenario['simulado']['variacao_resultado']);
        $this->assertEquals('lucrativa', $cenario['simulado']['status']);
    }

    public function test_pagina_controladoria_turmas_carrega(): void
    {
        $this->autenticarComoAdmin();
        $this->criarTurma();

        Livewire::test(ControladoriaTurmas::class)
            ->assertSuccessful()
            ->assertSee('Rentabilidade e Ponto de Equilíbrio');
    }
}
