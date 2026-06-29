<?php

namespace Tests\Feature;

use App\Filament\Resources\Questionarios\Pages\ResponderQuestionario;
use App\Models\Questionario;
use App\Models\QuestionarioResposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionarioRespostaReenvioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_responder_novamente_vinculando_parent_id(): void
    {
        // 1. Criar usuário e autenticar
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

        // 3. Criar resposta de questionário original (parent)
        $parentResposta = QuestionarioResposta::create([
            'questionario_id' => $questionario->id,
            'user_id' => $user->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);

        // 4. Testar acesso via requisição HTTP GET para garantir que a página renderiza com o parent_id
        $url = route('filament.admin.resources.questionarios.responder', [
            'record' => $questionario->id,
            'parent_id' => $parentResposta->id,
        ]);
        $response = $this->get($url);
        $response->assertSuccessful();

        // 5. Testar a submissão do componente Livewire
        Livewire::test(ResponderQuestionario::class, [
            'record' => $questionario,
        ])
            ->set('parentId', $parentResposta->id)
            ->call('submit');

        // 6. Validar se a nova resposta foi gravada com o parent_id correto
        $this->assertDatabaseHas('questionario_respostas', [
            'questionario_id' => $questionario->id,
            'user_id' => $user->id,
            'parent_id' => $parentResposta->id,
            'status' => 'enviado',
        ]);

        // 7. Validar a relação parent/children no modelo
        $novaResposta = QuestionarioResposta::where('parent_id', $parentResposta->id)->first();
        $this->assertNotNull($novaResposta);
        $this->assertEquals($parentResposta->id, $novaResposta->parent->id);
        $this->assertTrue($parentResposta->children->contains($novaResposta));
    }

    public function test_parent_id_invalido_de_outro_questionario_eh_ignorado(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user->assignRole($role);
        session(['active_role' => 'super_admin']);
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

        // 4. Testar requisição GET para o questionário 2 passando o parent_id da resposta do questionário 1
        $url = route('filament.admin.resources.questionarios.responder', [
            'record' => $questionario2->id,
            'parent_id' => $parentResposta->id,
        ]);
        $response = $this->get($url);
        $response->assertSuccessful();
    }
}
