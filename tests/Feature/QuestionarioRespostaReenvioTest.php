<?php

namespace Tests\Feature;

use App\Filament\Resources\Questionarios\Pages\ResponderQuestionario;
use App\Models\Questionario;
use App\Models\QuestionarioResposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionarioRespostaReenvioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_responder_novamente_vinculando_parent_id(): void
    {
        // 1. Criar usuário e autenticar
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. Criar questionário
        $questionario = Questionario::create([
            'titulo' => 'Questionário de Feedback',
            'is_ativo' => true,
        ]);

        // 3. Criar resposta de questionário original (parent)
        $parentResposta = QuestionarioResposta::create([
            'questionario_id' => $questionario->id,
            'user_id' => $user->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);

        // 4. Testar o componente Livewire de ResponderQuestionario simulando query parameter parent_id
        $_GET['parent_id'] = $parentResposta->id;

        Livewire::test(ResponderQuestionario::class, [
            'record' => $questionario,
        ])
            ->assertSet('parentId', $parentResposta->id)
            ->call('submit');

        unset($_GET['parent_id']);

        // 5. Validar se a nova resposta foi gravada com o parent_id correto
        $this->assertDatabaseHas('questionario_respostas', [
            'questionario_id' => $questionario->id,
            'user_id' => $user->id,
            'parent_id' => $parentResposta->id,
            'status' => 'enviado',
        ]);

        // 6. Validar a relação parent/children no modelo
        $novaResposta = QuestionarioResposta::where('parent_id', $parentResposta->id)->first();
        $this->assertNotNull($novaResposta);
        $this->assertEquals($parentResposta->id, $novaResposta->parent->id);
        $this->assertTrue($parentResposta->children->contains($novaResposta));
    }

    public function test_parent_id_invalido_de_outro_questionario_eh_ignorado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $questionario1 = Questionario::create([
            'titulo' => 'Questionário 1',
            'is_ativo' => true,
        ]);

        $questionario2 = Questionario::create([
            'titulo' => 'Questionário 2',
            'is_ativo' => true,
        ]);

        // Resposta original pertence ao questionário 1
        $parentResposta = QuestionarioResposta::create([
            'questionario_id' => $questionario1->id,
            'user_id' => $user->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);

        // Tentamos responder o questionário 2 passando o parent_id da resposta do questionário 1
        $_GET['parent_id'] = $parentResposta->id;

        Livewire::test(ResponderQuestionario::class, [
            'record' => $questionario2,
        ])
            ->assertSet('parentId', null);

        unset($_GET['parent_id']);
    }
}
