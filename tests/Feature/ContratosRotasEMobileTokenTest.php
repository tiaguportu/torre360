<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * PDF, download e geração de assinatura do contrato mostram CPF, endereço e valores: só a equipe administrativa, quem tem
 * `View:Contrato` ou o aluno/responsável do contrato passam. O registro de token do aplicativo exige conta ativa.
 */
class ContratosRotasEMobileTokenTest extends TestCase
{
    use RefreshDatabase;

    private Contrato $contrato;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matricula = Matricula::factory()->create();
        $this->contrato = Contrato::create(['matricula_id' => $this->matricula->id, 'valor_total' => 1000, 'data_aceite' => '2026-01-05']);
    }

    private function usuario(string $papel, bool $ativo = true): User
    {
        $user = User::factory()->create([
            'activated_at' => $ativo ? now()->subDay() : now()->subWeek(),
            'deactivated_at' => $ativo ? null : now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']));
        session(['active_role' => $papel]);

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rotas(): array
    {
        return [
            'pdf' => ['get', 'contratos.pdf'],
            'download' => ['get', 'contratos.download'],
            'gerar assinatura' => ['post', 'contratos.gerar-assinatura'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotas')]
    public function test_visitante_e_mandado_para_o_login(string $metodo, string $rota): void
    {
        $this->{$metodo}(route($rota, $this->contrato))->assertRedirectContains('/login');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotas')]
    public function test_usuario_sem_vinculo_com_o_contrato_leva_403(string $metodo, string $rota): void
    {
        $this->actingAs($this->usuario('aluno'))
            ->{$metodo}(route($rota, $this->contrato))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotas')]
    public function test_professor_nao_acessa_contrato_so_por_ser_da_equipe(string $metodo, string $rota): void
    {
        $this->actingAs($this->usuario('professor'))
            ->{$metodo}(route($rota, $this->contrato))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotas')]
    public function test_conta_inativa_leva_403_e_perde_a_sessao(string $metodo, string $rota): void
    {
        $inativo = $this->usuario('secretaria', ativo: false);

        $this->actingAs($inativo)
            ->{$metodo}(route($rota, $this->contrato))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_pdf_passa_a_trava_para_a_secretaria_e_para_o_aluno_do_contrato(): void
    {
        // sem template de contrato cadastrado a resposta é 404: prova que a autorização já passou
        $this->actingAs($this->usuario('secretaria'))
            ->get(route('contratos.pdf', $this->contrato))
            ->assertNotFound();

        $aluno = $this->usuario('aluno');
        $aluno->pessoas()->attach($this->matricula->pessoa_id);

        $this->actingAs($aluno)
            ->get(route('contratos.pdf', $this->contrato))
            ->assertNotFound();
    }

    public function test_download_sem_documento_no_assinafy_volta_com_aviso_em_vez_de_baixar(): void
    {
        $this->actingAs($this->usuario('secretaria'))
            ->from(url('/painel'))
            ->get(route('contratos.download', $this->contrato))
            ->assertRedirect(url('/painel'));
    }

    public function test_gerar_assinatura_sem_credenciais_do_assinafy_volta_com_o_motivo(): void
    {
        $this->actingAs($this->usuario('secretaria'))
            ->from(url('/painel'))
            ->post(route('contratos.gerar-assinatura', $this->contrato))
            ->assertRedirect(url('/painel'))
            ->assertSessionHas('error', fn (string $mensagem) => str_contains($mensagem, 'Configuração do Assinafy pendente'));
    }

    // ---------- Token do aplicativo ----------

    public function test_app_registra_o_token_de_notificacao_do_usuario_logado(): void
    {
        $user = $this->usuario('aluno');

        $this->actingAs($user)
            ->postJson(route('mobile.register-token'), ['token' => 'fcm-abc', 'platform' => 'android'])
            ->assertOk()
            ->assertJson(['message' => 'Token registrado com sucesso']);

        $user->refresh();
        $this->assertSame('fcm-abc', $user->fcm_token);
        $this->assertSame('android', $user->device_type);
    }

    public function test_token_e_obrigatorio_e_a_plataforma_e_opcional(): void
    {
        $user = $this->usuario('aluno');

        $this->actingAs($user)->postJson(route('mobile.register-token'), ['platform' => 'ios'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->actingAs($user)->postJson(route('mobile.register-token'), ['token' => 'so-token'])->assertOk();

        $this->assertSame('so-token', $user->fresh()->fcm_token);
    }

    public function test_visitante_nao_registra_token(): void
    {
        $this->postJson(route('mobile.register-token'), ['token' => 'fcm-abc'])->assertUnauthorized();

        $this->assertSame(0, User::whereNotNull('fcm_token')->count());
    }

    public function test_conta_inativa_nao_registra_token(): void
    {
        $inativo = $this->usuario('aluno', ativo: false);

        $this->actingAs($inativo)
            ->postJson(route('mobile.register-token'), ['token' => 'fcm-abc'])
            ->assertForbidden()
            ->assertJson(['message' => 'Sua conta de acesso está inativa ou foi desativada pelo administrador.']);

        $this->assertNull($inativo->fresh()->fcm_token);
    }

    public function test_registro_de_token_tem_limite_de_dez_por_minuto(): void
    {
        $user = $this->usuario('aluno');

        foreach (range(1, 10) as $i) {
            $this->actingAs($user)->postJson(route('mobile.register-token'), ['token' => "t{$i}"])->assertOk();
        }

        $this->actingAs($user)->postJson(route('mobile.register-token'), ['token' => 't11'])->assertTooManyRequests();
    }
}
