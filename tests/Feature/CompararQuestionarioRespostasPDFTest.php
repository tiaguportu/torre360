<?php

namespace Tests\Feature;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use App\Models\Questionario;
use App\Models\QuestionarioResposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompararQuestionarioRespostasPDFTest extends TestCase
{
    use RefreshDatabase;

    private function criarAdmin(): User
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user->assignRole($role);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    private function criarUsuarioComum(): User
    {
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('professor');
        session(['active_role' => 'professor']);

        return $user;
    }

    private function criarResposta(?User $user, string $questionarioTitulo): QuestionarioResposta
    {
        $questionario = Questionario::create([
            'titulo' => $questionarioTitulo,
            'is_ativo' => true,
        ]);

        return QuestionarioResposta::create([
            'questionario_id' => $questionario->id,
            'user_id' => $user?->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);
    }

    public function test_usuario_nao_autenticado_nao_pode_baixar_pdf_de_comparacao(): void
    {
        $resposta = $this->criarResposta(null, 'Questionário Anônimo');

        $response = $this->get(route('questionario-respostas.comparar.pdf', ['ids' => [$resposta->id]]));

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_retorna_404_quando_nenhum_id_e_informado(): void
    {
        $admin = $this->criarAdmin();

        $response = $this->actingAs($admin)
            ->get(route('questionario-respostas.comparar.pdf'));

        $response->assertStatus(404);
    }

    public function test_retorna_404_quando_ids_for_array_vazio_ou_apenas_nao_numericos(): void
    {
        $admin = $this->criarAdmin();

        $this->actingAs($admin)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => []]))
            ->assertStatus(404);

        $this->actingAs($admin)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => ['abc']]))
            ->assertStatus(404);
    }

    public function test_admin_baixa_pdf_de_comparacao_com_duas_respostas(): void
    {
        $admin = $this->criarAdmin();

        $resposta1 = $this->criarResposta($admin, 'Questionário Um');
        $resposta2 = $this->criarResposta($admin, 'Questionário Dois');

        $response = $this->actingAs($admin)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$resposta1->id, $resposta2->id]]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_baixa_pdf_de_respostas_de_outros_usuarios(): void
    {
        $admin = $this->criarAdmin();
        $outro1 = User::factory()->create(['activated_at' => now()]);
        $outro2 = User::factory()->create(['activated_at' => now()]);

        $resposta1 = $this->criarResposta($outro1, 'Questionário Outro Um');
        $resposta2 = $this->criarResposta($outro2, 'Questionário Outro Dois');

        $response = $this->actingAs($admin)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$resposta1->id, $resposta2->id]]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_usuario_comum_baixa_pdf_com_sucesso_quando_informa_apenas_respostas_proprias(): void
    {
        $usuario = $this->criarUsuarioComum();

        $resposta1 = $this->criarResposta($usuario, 'Minha Resposta Um');
        $resposta2 = $this->criarResposta($usuario, 'Minha Resposta Dois');

        $response = $this->actingAs($usuario)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$resposta1->id, $resposta2->id]]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_usuario_comum_recebe_403_ao_tentar_baixar_pdf_de_resposta_de_outro_usuario(): void
    {
        $outroUsuario = $this->criarUsuarioComum();
        $dono = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);

        $respostaAlheia = $this->criarResposta($dono, 'Questionário Alheio');

        $response = $this->actingAs($outroUsuario)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$respostaAlheia->id]]));

        $response->assertStatus(403);
    }

    public function test_usuario_comum_recebe_403_ao_tentar_misturar_resposta_propria_e_alheia(): void
    {
        $usuario = $this->criarUsuarioComum();
        $outroUsuario = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);

        $minhaResposta = $this->criarResposta($usuario, 'Minha Resposta');
        $respostaAlheia = $this->criarResposta($outroUsuario, 'Resposta Alheia');

        $response = $this->actingAs($usuario)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$minhaResposta->id, $respostaAlheia->id]]));

        // Contrato anti-IDOR (dd98243d): exige que todos os IDs solicitados sejam visíveis (tudo ou nada).
        $response->assertStatus(403);
    }

    public function test_retorna_403_quando_informa_id_inexistente_isolado_ou_misturado(): void
    {
        $usuario = $this->criarUsuarioComum();
        $minhaResposta = $this->criarResposta($usuario, 'Minha Resposta');

        // ID inexistente isolado: não pode virar oráculo de existência, responde 403
        $this->actingAs($usuario)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [999999]]))
            ->assertStatus(403);

        // ID inexistente misturado com próprio: responde 403
        $this->actingAs($usuario)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$minhaResposta->id, 999999]]))
            ->assertStatus(403);
    }

    public function test_baixa_pdf_com_sucesso_quando_ids_sao_repetidos(): void
    {
        $usuario = $this->criarUsuarioComum();
        $minhaResposta = $this->criarResposta($usuario, 'Minha Resposta Repetida');

        $response = $this->actingAs($usuario)
            ->get(route('questionario-respostas.comparar.pdf', ['ids' => [$minhaResposta->id, $minhaResposta->id]]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pagina_de_comparacao_retorna_404_sem_ids_selecionados(): void
    {
        $admin = $this->criarAdmin();

        $response = $this->actingAs($admin)
            ->get(QuestionarioRespostaResource::getUrl('comparar'));

        $response->assertStatus(404);
    }

    public function test_pagina_de_comparacao_carrega_com_ids_validos(): void
    {
        $admin = $this->criarAdmin();

        $resposta1 = $this->criarResposta($admin, 'Questionário Um');
        $resposta2 = $this->criarResposta($admin, 'Questionário Dois');

        $response = $this->actingAs($admin)
            ->get(QuestionarioRespostaResource::getUrl('comparar', ['ids' => [$resposta1->id, $resposta2->id]]));

        $response->assertOk();
    }
}
