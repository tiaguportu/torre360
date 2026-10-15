<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusOmrCorrecao;
use App\Models\OmrCorrecao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A foto da folha carrega a letra de menores: some depois da retenção, qualquer que tenha sido o desfecho da
 * leitura, e o resultado (respostas, nota, auditoria) permanece.
 */
class OmrPurgaArquivosTest extends TestCase
{
    use RefreshDatabase;

    public function test_purga_remove_fotos_antigas_de_qualquer_status_e_preserva_o_resultado(): void
    {
        Storage::fake('local');
        config(['omr.retencao_dias' => 30]);

        $antigas = [];
        foreach ([StatusOmrCorrecao::Confirmada, StatusOmrCorrecao::Descartada, StatusOmrCorrecao::Falha, StatusOmrCorrecao::AguardandoConferencia] as $i => $status) {
            $caminho = "omr/2026/01/antiga-{$i}.jpg";
            Storage::disk('local')->put($caminho, 'jpeg');

            $antigas[] = OmrCorrecao::factory()->create([
                'status' => $status,
                'arquivo_path' => $caminho,
                'nota_calculada' => 7.50,
                'created_at' => now()->subDays(45),
            ]);
        }

        Storage::disk('local')->put('omr/2026/10/recente.jpg', 'jpeg');
        $recente = OmrCorrecao::factory()->create([
            'status' => StatusOmrCorrecao::AguardandoConferencia,
            'arquivo_path' => 'omr/2026/10/recente.jpg',
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('omr:purgar-arquivos-antigos')->assertSuccessful();

        foreach ($antigas as $i => $correcao) {
            Storage::disk('local')->assertMissing("omr/2026/01/antiga-{$i}.jpg");
            $this->assertSame('purgado_lgpd', $correcao->fresh()->arquivo_path);
            $this->assertEquals(7.50, (float) $correcao->fresh()->nota_calculada, 'O resultado da leitura permanece.');
        }

        Storage::disk('local')->assertExists('omr/2026/10/recente.jpg');
        $this->assertSame('omr/2026/10/recente.jpg', $recente->fresh()->arquivo_path);
    }

    public function test_purga_remove_uploads_temporarios_abandonados(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('omr/uploads/abandonado.jpg', 'jpeg');
        touch(Storage::disk('local')->path('omr/uploads/abandonado.jpg'), now()->subDays(3)->timestamp);

        Storage::disk('local')->put('omr/uploads/em-andamento.jpg', 'jpeg');

        $this->artisan('omr:purgar-arquivos-antigos')->assertSuccessful();

        Storage::disk('local')->assertMissing('omr/uploads/abandonado.jpg');
        Storage::disk('local')->assertExists('omr/uploads/em-andamento.jpg');
    }
}
