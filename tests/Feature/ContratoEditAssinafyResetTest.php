<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoEditAssinafyResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_metodo_ja_enviado_assinafy_e_reset_assinafy_state(): void
    {
        $aluno = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $contrato = Contrato::create([
            'valor_total' => 2000.00,
            'matricula_id' => $matricula->id,
            'assinafy_id' => '103d44caa00ec7964ba417930607',
            'assinafy_status' => 'enviado',
            'assinafy_request_log' => ['environment_url' => 'https://api.assinafy.com.br/v1'],
            'data_aceite' => now(),
        ]);

        $this->assertTrue($contrato->jaEnviadoAssinafy());

        $contrato->resetAssinafyState();

        $contrato->refresh();

        $this->assertNull($contrato->assinafy_id);
        $this->assertEquals('pending', $contrato->assinafy_status);
        $this->assertNull($contrato->assinafy_request_log);
        $this->assertNull($contrato->data_aceite);
        $this->assertFalse($contrato->jaEnviadoAssinafy());
    }

    public function test_contrato_nao_enviado_retorna_falso_em_ja_enviado_assinafy(): void
    {
        $aluno = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $contrato = Contrato::create([
            'valor_total' => 3000.00,
            'matricula_id' => $matricula->id,
            'assinafy_id' => null,
            'assinafy_status' => 'pending',
            'assinafy_request_log' => null,
        ]);

        $this->assertFalse($contrato->jaEnviadoAssinafy());
    }
}
