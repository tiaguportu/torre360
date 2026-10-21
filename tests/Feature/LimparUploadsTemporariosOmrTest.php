<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusOmrCorrecao;
use App\Models\OmrCorrecao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LimparUploadsTemporariosOmrTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_expurga_uploads_temporarios_com_mais_de_sete_dias(): void
    {
        Storage::fake('local');

        // Cria arquivo temporário antigo (> 7 dias)
        Storage::disk('local')->put('omr/uploads/antigo.jpg', 'conteudo_binario');
        touch(Storage::disk('local')->path('omr/uploads/antigo.jpg'), now()->subDays(10)->timestamp);

        // Cria arquivo temporário recente (< 7 dias)
        Storage::disk('local')->put('omr/uploads/recente.jpg', 'conteudo_binario');
        touch(Storage::disk('local')->path('omr/uploads/recente.jpg'), now()->subDays(2)->timestamp);

        $this->artisan('omr:limpar-temporarios --dias=7')
            ->expectsOutputToContain('Limpeza de arquivos temporários OMR concluída')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('omr/uploads/antigo.jpg');
        Storage::disk('local')->assertExists('omr/uploads/recente.jpg');
    }

    public function test_comando_expurga_fotos_de_correcoes_descartadas_ou_com_falha_com_mais_de_sete_dias(): void
    {
        Storage::fake('local');

        // Correção descartada antiga
        $caminhoDescartada = 'omr/2026/10/descartada.jpg';
        Storage::disk('local')->put($caminhoDescartada, 'binario_descartado');
        $correcaoDescartada = OmrCorrecao::factory()->create([
            'status' => StatusOmrCorrecao::Descartada,
            'arquivo_path' => $caminhoDescartada,
            'created_at' => now()->subDays(10),
        ]);

        // Correção com falha antiga
        $caminhoFalha = 'omr/2026/10/falha.jpg';
        Storage::disk('local')->put($caminhoFalha, 'binario_falha');
        $correcaoFalha = OmrCorrecao::factory()->create([
            'status' => StatusOmrCorrecao::Falha,
            'arquivo_path' => $caminhoFalha,
            'created_at' => now()->subDays(8),
        ]);

        // Correção confirmada antiga (NÃO deve ser removida por este comando temporário de 7 dias)
        $caminhoConfirmada = 'omr/2026/10/confirmada.jpg';
        Storage::disk('local')->put($caminhoConfirmada, 'binario_confirmada');
        $correcaoConfirmada = OmrCorrecao::factory()->create([
            'status' => StatusOmrCorrecao::Confirmada,
            'arquivo_path' => $caminhoConfirmada,
            'created_at' => now()->subDays(10),
        ]);

        // Correção descartada recente (< 7 dias, deve ser preservada)
        $caminhoRecente = 'omr/2026/10/descartada_recente.jpg';
        Storage::disk('local')->put($caminhoRecente, 'binario_recente');
        $correcaoRecente = OmrCorrecao::factory()->create([
            'status' => StatusOmrCorrecao::Descartada,
            'arquivo_path' => $caminhoRecente,
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('omr:limpar-temporarios --dias=7')->assertSuccessful();

        // As fotos temporárias obsoletas devem sumir do disco e ter seu path atualizado
        Storage::disk('local')->assertMissing($caminhoDescartada);
        Storage::disk('local')->assertMissing($caminhoFalha);
        $this->assertSame('expurgado_temp', $correcaoDescartada->fresh()->arquivo_path);
        $this->assertSame('expurgado_temp', $correcaoFalha->fresh()->arquivo_path);

        // A confirmada e a recente continuam íntegras
        Storage::disk('local')->assertExists($caminhoConfirmada);
        Storage::disk('local')->assertExists($caminhoRecente);
        $this->assertSame($caminhoConfirmada, $correcaoConfirmada->fresh()->arquivo_path);
        $this->assertSame($caminhoRecente, $correcaoRecente->fresh()->arquivo_path);
    }
}
