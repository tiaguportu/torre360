<?php

namespace Tests\Feature;

use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CaptacaoThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        StatusInteressado::factory()->create(['nome' => 'Novo', 'is_final' => false, 'is_ganho' => false]);
        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        Mail::fake();
    }

    public function test_rota_post_quero_matricular_bloqueia_apos_limite_de_requisicoes(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'flag_ativo' => true]);

        $payload = [
            'tipo_preenchimento' => 'proprio',
            'responsavel_telefone' => '11999998888',
            'responsavel_email' => 'teste.throttle@example.com',
            'consentimento' => '1',
            'alunos' => [
                [
                    'nome' => 'Aluno Throttle',
                    'unidade_id' => $unidade->id,
                ],
            ],
        ];

        // Dispara 15 requisições válidas dentro do limite
        for ($i = 0; $i < 15; $i++) {
            $response = $this->post('/quero-matricular', $payload);
            $this->assertNotEquals(429, $response->getStatusCode(), "A requisição {$i} não deveria ser bloqueada.");
        }

        // A 16ª requisição deve retornar 429 Too Many Requests
        $response16 = $this->post('/quero-matricular', $payload);
        $response16->assertStatus(429);
    }
}
