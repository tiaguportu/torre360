<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Pages\RelatorioInadimplencia;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RelatorioInadimplenciaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSuperAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('super_admin');
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    private function criarFaturaAtrasada(string $turmaNome, float $valor, string $vencimento): Fatura
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026 '.uniqid(), 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $turma = Turma::create(['nome' => $turmaNome, 'periodo_letivo_id' => $periodo->id]);
        $aluno = Pessoa::create(['nome' => 'Aluno Inadimplente '.uniqid()]);
        $responsavel = Pessoa::create(['nome' => 'Responsável Inadimplente '.uniqid()]);

        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id, 'periodo_letivo_id' => $periodo->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => $valor, 'data_aceite' => '2026-01-05']);

        ResponsavelFinanceiro::create(['contrato_id' => $contrato->id, 'pessoa_id' => $responsavel->id]);

        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => $vencimento, 'status' => StatusFatura::Atrasado]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade',
            'quantidade' => 1,
            'valor_unitario' => $valor,
            'desconto' => 0,
        ]);

        return $fatura->fresh();
    }

    public function test_pagina_carrega_e_mostra_resumo_correto(): void
    {
        $this->autenticarSuperAdmin();

        $this->criarFaturaAtrasada('Turma A', 500.0, now()->subDays(10)->toDateString());
        $this->criarFaturaAtrasada('Turma B', 300.0, now()->subDays(20)->toDateString());

        $component = Livewire::test(RelatorioInadimplencia::class)->assertSuccessful();

        $resumo = $component->instance()->getResumo();

        $this->assertSame(2, $resumo['total_faturas']);
        $this->assertEquals(800.0, $resumo['total_devido']);
        $this->assertSame(2, $resumo['total_responsaveis']);
    }

    public function test_nao_lista_faturas_pendentes_ou_pagas(): void
    {
        $this->autenticarSuperAdmin();

        $this->criarFaturaAtrasada('Turma A', 500.0, now()->subDays(10)->toDateString());

        $periodo = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-01-01', 'data_fim' => '2027-12-31']);
        $turma = Turma::create(['nome' => 'Turma C', 'periodo_letivo_id' => $periodo->id]);
        $aluno = Pessoa::create(['nome' => 'Aluno Em Dia']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id, 'periodo_letivo_id' => $periodo->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 500, 'data_aceite' => '2027-01-05']);
        $faturaPendente = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => now()->addDays(10), 'status' => StatusFatura::Pendente]);
        ItemFatura::create(['fatura_id' => $faturaPendente->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => 500, 'desconto' => 0]);

        $resumo = Livewire::test(RelatorioInadimplencia::class)->instance()->getResumo();

        $this->assertSame(1, $resumo['total_faturas']);
    }

    public function test_filtro_por_faixa_de_atraso(): void
    {
        $this->autenticarSuperAdmin();

        $recente = $this->criarFaturaAtrasada('Turma A', 500.0, now()->subDays(3)->toDateString());
        $antiga = $this->criarFaturaAtrasada('Turma B', 300.0, now()->subDays(40)->toDateString());

        Livewire::test(RelatorioInadimplencia::class)
            ->filterTable('faixa_atraso', ['faixa' => '31+'])
            ->assertCanSeeTableRecords([$antiga])
            ->assertCanNotSeeTableRecords([$recente]);
    }
}
