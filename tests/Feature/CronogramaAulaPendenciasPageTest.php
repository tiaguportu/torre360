<?php

namespace Tests\Feature;

use App\Filament\Resources\CronogramaAulas\Pages\PendenciasFrequencia;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class CronogramaAulaPendenciasPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_de_pendencias_renderiza_com_sucesso(): void
    {
        Gate::before(fn () => true);

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create();
        Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
        ]);
        $disciplina = Disciplina::factory()->create();

        CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => now()->subDays(1)->format('Y-m-d'),
        ]);

        $testable = Livewire::test(PendenciasFrequencia::class);
        $testable->assertSuccessful();

        $pendencias = $testable->instance()->getPendenciasAgrupadas();
        $this->assertTrue($pendencias->has(now()->subDays(1)->format('Y-m-d')));
    }
}
