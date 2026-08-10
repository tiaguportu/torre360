<?php

namespace Tests\Feature;

use App\Filament\Resources\CronogramaAulas\Pages\ListCronogramaAulas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class CronogramaAulaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtro_padrao_frequencia_pendente_esta_ativo(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        Gate::define('ViewAny:CronogramaAula', fn (User $user) => true);

        $testable = Livewire::test(ListCronogramaAulas::class);

        $testable->assertTableFilterExists('frequencias_pendentes');

        $table = $testable->instance()->getTable();
        $filter = $table->getFilter('frequencias_pendentes');

        $this->assertNotNull($filter);
        $this->assertTrue($filter->getState()['isActive'] ?? false || $filter->getDefaultState());
    }
}
