<?php

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\LandingLead;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O reCAPTCHA v3 dos formulários públicos precisa barrar requisições sem token: antes, a regra
 * (não implícita) era ignorada quando `recaptcha_token` era omitido ou vazio.
 */
class CaptacaoRecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        StatusInteressado::factory()->create(['nome' => 'Novo', 'is_final' => false, 'is_ganho' => false]);
        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        Mail::fake();
        Http::preventStrayRequests();
    }

    private function configurarRecaptcha(): void
    {
        config(['services.recaptcha.site_key' => 'site-key-teste', 'services.recaptcha.secret' => 'secret-teste']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadCaptacao(array $extra = []): array
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'flag_ativo' => true]);

        return array_merge([
            'tipo_preenchimento' => 'proprio',
            'responsavel_telefone' => '11999998888',
            'responsavel_email' => 'familia.recaptcha@example.com',
            'consentimento' => '1',
            'alunos' => [['nome' => 'Aluno Recaptcha', 'unidade_id' => $unidade->id]],
        ], $extra);
    }

    private function fakeGoogle(float $score, bool $success = true): void
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => $success, 'score' => $score], 200),
        ]);
    }

    public function test_captacao_rejeita_requisicao_sem_o_campo_do_token(): void
    {
        $this->configurarRecaptcha();

        $this->post('/quero-matricular', $this->payloadCaptacao())
            ->assertSessionHasErrors('recaptcha_token');

        $this->assertSame(0, Interessado::count());
        Http::assertNothingSent();
    }

    public function test_captacao_rejeita_token_vazio(): void
    {
        $this->configurarRecaptcha();

        $this->post('/quero-matricular', $this->payloadCaptacao(['recaptcha_token' => '']))
            ->assertSessionHasErrors('recaptcha_token');

        $this->assertSame(0, Interessado::count());
    }

    public function test_captacao_rejeita_score_baixo(): void
    {
        $this->configurarRecaptcha();
        $this->fakeGoogle(0.1);

        $this->post('/quero-matricular', $this->payloadCaptacao(['recaptcha_token' => 'token-de-bot']))
            ->assertSessionHasErrors('recaptcha_token');

        $this->assertSame(0, Interessado::count());
    }

    public function test_captacao_aceita_token_valido(): void
    {
        $this->configurarRecaptcha();
        $this->fakeGoogle(0.9);

        $this->post('/quero-matricular', $this->payloadCaptacao(['recaptcha_token' => 'token-humano']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('captacao.interessado.sucesso'));

        $this->assertSame(1, Interessado::count());
    }

    public function test_captacao_sem_chaves_configuradas_nao_exige_token(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        $this->post('/quero-matricular', $this->payloadCaptacao())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Interessado::count());
    }

    public function test_landing_page_rejeita_requisicao_sem_token(): void
    {
        $this->configurarRecaptcha();

        $this->post(route('solicitar-acesso'), [
            'nome' => 'Escola Teste',
            'email' => 'contato@escola.example.com',
        ])->assertSessionHasErrors('recaptcha_token');

        $this->assertSame(0, LandingLead::count());
    }

    public function test_landing_page_aceita_token_valido_sem_gravar_o_token(): void
    {
        $this->configurarRecaptcha();
        $this->fakeGoogle(0.8);

        $this->post(route('solicitar-acesso'), [
            'nome' => 'Escola Teste',
            'email' => 'contato@escola.example.com',
            'recaptcha_token' => 'token-humano',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('landing_leads', ['email' => 'contato@escola.example.com']);
    }
}
