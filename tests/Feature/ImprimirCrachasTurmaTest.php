<?php

namespace Tests\Feature;

use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Jobs\GerarCrachasTurmaPdfJob;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateCracha;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImprimirCrachasTurmaTest extends TestCase
{
    use RefreshDatabase;

    public function test_acao_em_lote_imprimir_crachas_despacha_job_com_turmas_e_template_selecionados(): void
    {
        Bus::fake();

        $adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
        session(['active_role' => 'super_admin']);

        $template = TemplateCracha::factory()->create();
        $turma1 = Turma::factory()->create();
        $turma2 = Turma::factory()->create();
        Matricula::factory()->create([
            'turma_id' => $turma1->id,
            'situacao' => 'ativa',
            'pessoa_id' => Pessoa::factory()->create()->id,
        ]);

        Livewire::actingAs($adminUser)
            ->test(ListTurmas::class)
            ->callTableBulkAction('imprimirCrachasLote', [$turma1, $turma2], data: ['template_cracha_id' => $template->id]);

        Bus::assertDispatched(GerarCrachasTurmaPdfJob::class, function (GerarCrachasTurmaPdfJob $job) use ($turma1, $turma2, $template, $adminUser) {
            return $job->turmaIds === [$turma1->id, $turma2->id]
                && $job->templateCrachaId === $template->id
                && $job->userId === $adminUser->id;
        });
    }
}
