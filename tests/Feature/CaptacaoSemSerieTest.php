<?php

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CaptacaoSemSerieTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'tipo_preenchimento' => 'responsavel',
            'responsavel_nome' => 'Maria Responsável',
            'responsavel_telefone' => '11999990000',
            'responsavel_email' => 'maria.sem.serie@example.com',
            // Nenhuma série informada — o formulário público sempre permitiu isso
            // (validação nullable), mas o banco exigia serie_id NOT NULL.
            'alunos' => [['nome' => 'João Aluno']],
        ], $extra);
    }

    public function test_envio_sem_serie_nao_retorna_erro_500(): void
    {
        $response = $this->post('/quero-matricular', $this->payload());

        $response->assertRedirect(route('captacao.interessado.sucesso'));
        $response->assertSessionHasNoErrors();
    }

    public function test_envio_sem_serie_cria_lead_e_dependente_com_serie_nula(): void
    {
        $this->post('/quero-matricular', $this->payload())
            ->assertRedirect(route('captacao.interessado.sucesso'));

        $this->assertSame(1, Interessado::count());

        $dependente = InteressadoDependente::first();

        $this->assertNotNull($dependente);
        $this->assertSame('João Aluno', $dependente->nome_crianca);
        $this->assertNull($dependente->serie_id);
    }

    public function test_envio_com_multiplos_alunos_alguns_sem_serie(): void
    {
        $this->post('/quero-matricular', $this->payload([
            'alunos' => [
                ['nome' => 'Aluno Com Série', 'serie_id' => null],
                ['nome' => 'Aluno Sem Série'],
            ],
        ]))->assertRedirect(route('captacao.interessado.sucesso'));

        $this->assertSame(2, InteressadoDependente::count());
        $this->assertSame(0, InteressadoDependente::whereNotNull('serie_id')->count());
    }
}
