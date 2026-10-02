<?php

namespace Tests\Feature;

use App\Filament\Resources\OrdemServicoResource\Pages\ListOrdemServicos;
use App\Models\OrdemServico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdemServicoListagemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
        session(['active_role' => 'super_admin']);

        $this->actingAs($admin);
    }

    private function criarOrdem(string $status, ?string $prazo = null, ?float $custo = null): OrdemServico
    {
        return OrdemServico::create([
            'titulo' => "OS {$status} ".uniqid(),
            'status' => $status,
            'prazo_conclusao' => $prazo,
            'custo_estimado' => $custo,
        ]);
    }

    #[Test]
    public function cartoes_de_resumo_ficam_na_propria_pagina_e_acompanham_os_filtros(): void
    {
        $this->criarOrdem('Aberta', now()->subDays(3)->toDateString(), 100);
        $this->criarOrdem('Em Andamento', now()->addDays(5)->toDateString(), 250.5);
        $this->criarOrdem('Concluída', now()->subDays(10)->toDateString(), 50);

        $lista = Livewire::test(ListOrdemServicos::class)
            ->assertSee('OS por Status')
            ->assertSee('OS Atrasadas')
            ->assertSee('Custo Estimado Total');

        // Sem componente Livewire filho com props reativas (origem de TypeError em produção)
        $this->assertStringNotContainsString('OrdemServicoStats', $lista->html());

        $stats = $lista->instance()->getResumoStats();
        $this->assertSame('Abertas: 1 | Em Andamento: 1 | Concluídas: 1', $stats[0]->getValue());
        $this->assertEquals(1, $stats[1]->getValue()); // só a aberta está atrasada; a concluída não conta
        $this->assertSame('R$ 400,50', $stats[2]->getValue());

        // Acompanha o filtro de status
        $lista->filterTable('status', 'Aberta');
        $filtrados = $lista->instance()->getResumoStats();
        $this->assertSame('Abertas: 1 | Em Andamento: 0 | Concluídas: 0', $filtrados[0]->getValue());
        $this->assertSame('R$ 100,00', $filtrados[2]->getValue());
    }
}
