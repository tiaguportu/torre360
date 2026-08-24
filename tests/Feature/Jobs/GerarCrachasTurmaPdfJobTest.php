<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GerarCrachasTurmaPdfJob;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateCracha;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GerarCrachasTurmaPdfJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_pdf_e_notifica_usuario_quando_ha_alunos_ativos(): void
    {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $template = TemplateCracha::factory()->create();
        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
            'pessoa_id' => Pessoa::factory()->create()->id,
        ]);

        (new GerarCrachasTurmaPdfJob(
            turmaIds: [$turma->id],
            templateCrachaId: $template->id,
            userId: $user->id,
        ))->handle();

        Storage::disk('local')->assertExists(
            collect(Storage::disk('local')->allFiles('crachas/'.$user->id))->first()
        );

        Notification::assertSentTo($user, SystemNotification::class, function (SystemNotification $notification) {
            return $notification->type === 'success' && ! empty($notification->actionUrl);
        });
    }

    public function test_notifica_alerta_quando_nao_ha_alunos_ativos(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $template = TemplateCracha::factory()->create();
        $turma = Turma::factory()->create();

        (new GerarCrachasTurmaPdfJob(
            turmaIds: [$turma->id],
            templateCrachaId: $template->id,
            userId: $user->id,
        ))->handle();

        Notification::assertSentTo($user, SystemNotification::class, function (SystemNotification $notification) {
            return $notification->type === 'warning';
        });
    }
}
