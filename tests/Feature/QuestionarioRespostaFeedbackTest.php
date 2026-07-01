<?php

namespace Tests\Feature;

use App\Filament\Resources\QuestionarioRespostas\Pages\ViewQuestionarioResposta;
use App\Models\Questionario;
use App\Models\QuestionarioResposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionarioRespostaFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_adicionar_feedback_na_resposta_do_questionario(): void
    {
        // 1. Criar usuário administrador e autenticar
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user->assignRole($role);
        session(['active_role' => 'super_admin']);
        $this->actingAs($user);

        // 2. Criar questionário
        $questionario = Questionario::create([
            'titulo' => 'Questionário de Feedback',
            'is_ativo' => true,
        ]);

        // 3. Criar resposta de questionário
        $resposta = QuestionarioResposta::create([
            'questionario_id' => $questionario->id,
            'user_id' => $user->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);

        // 4. Testar o componente Livewire ViewQuestionarioResposta e chamar a action 'adicionar_feedback'
        Livewire::test(ViewQuestionarioResposta::class, [
            'record' => $resposta->id,
        ])
            ->assertActionExists('adicionar_feedback')
            ->callAction('adicionar_feedback', [
                'texto' => 'Excelente desempenho nesta avaliação.',
            ])
            ->assertHasNoActionErrors();

        // 5. Validar se o feedback foi gravado no banco de dados com a relação correta
        $this->assertDatabaseHas('questionario_resposta_feedbacks', [
            'questionario_resposta_id' => $resposta->id,
            'user_id' => $user->id,
            'texto' => 'Excelente desempenho nesta avaliação.',
        ]);

        // 6. Validar a relação no modelo
        $this->assertCount(1, $resposta->fresh()->feedbacks);
        $this->assertEquals('Excelente desempenho nesta avaliação.', $resposta->fresh()->feedbacks->first()->texto);
    }

    public function test_usuario_sem_permissao_nao_ve_botao_de_adicionar_feedback(): void
    {
        // 1. Criar usuário comum sem permissão
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);

        // Atribui uma role com a permissão ViewAny e View, mas sem Create
        $role = Role::firstOrCreate(['name' => 'usuario_comum']);
        $permission1 = Permission::firstOrCreate(['name' => 'ViewAny:QuestionarioResposta']);
        $permission2 = Permission::firstOrCreate(['name' => 'View:QuestionarioResposta']);
        $role->givePermissionTo($permission1);
        $role->givePermissionTo($permission2);
        $user->assignRole($role);
        session(['active_role' => 'usuario_comum']);
        $this->actingAs($user);

        // 2. Criar questionário e resposta
        $questionario = Questionario::create([
            'titulo' => 'Questionário de Teste',
            'is_ativo' => true,
        ]);

        $resposta = QuestionarioResposta::create([
            'questionario_id' => $questionario->id,
            'user_id' => $user->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);

        // 3. Testar que o botão 'adicionar_feedback' não está visível para o usuário sem permissão
        Livewire::test(ViewQuestionarioResposta::class, [
            'record' => $resposta->id,
        ])
            ->assertActionHidden('adicionar_feedback');
    }
}
