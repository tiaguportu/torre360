<?php

namespace Tests\Feature;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Widgets\ContratosPendentesWidget;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContratosPendentesWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_nao_exibe_se_usuario_nao_tiver_contrato_pendente(): void
    {
        $user = User::factory()->create(['email' => 'usuario.sem.pendencia@example.com']);
        $this->actingAs($user);

        $this->assertFalse(ContratosPendentesWidget::canView());
        $this->assertNull(ContratoResource::getNavigationBadge());
    }

    public function test_widget_exibe_e_conta_no_badge_quando_usuario_possui_contrato_pendente(): void
    {
        $email = 'responsavel.pendente@example.com';
        $user = User::factory()->create(['email' => $email]);
        $pessoa = Pessoa::factory()->create(['nome' => 'Responsável Teste', 'email' => $email]);
        $pessoa->users()->save($user);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $contrato = Contrato::create([
            'valor_total' => 5000.00,
            'matricula_id' => $matricula->id,
            'assinafy_status' => 'pending',
        ]);

        $contrato->responsaveisFinanceiros()->create([
            'pessoa_id' => $pessoa->id,
            'percentual' => 100,
        ]);

        $this->actingAs($user);

        $this->assertTrue(ContratosPendentesWidget::canView());
        $this->assertEquals('1', ContratoResource::getNavigationBadge());
        $this->assertEquals('warning', ContratoResource::getNavigationBadgeColor());

        Livewire::test(ContratosPendentesWidget::class)
            ->assertSee('Contratos Pendentes de Assinatura Digital')
            ->assertSee('Aluno Teste');
    }

    public function test_widget_nao_exibe_para_usuario_sem_pessoa_vinculada_ao_contrato(): void
    {
        // Criar contrato pendente para Pessoa A
        $pessoaA = Pessoa::factory()->create(['nome' => 'Responsável A', 'email' => 'resp.a@example.com']);
        $userA = User::factory()->create(['email' => 'resp.a@example.com']);
        $pessoaA->users()->save($userA);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $contrato = Contrato::create([
            'valor_total' => 5000.00,
            'matricula_id' => $matricula->id,
            'assinafy_status' => 'pending',
        ]);
        $contrato->responsaveisFinanceiros()->create([
            'pessoa_id' => $pessoaA->id,
            'percentual' => 100,
        ]);

        // Criar Usuário B com Pessoa B (que não está no contrato)
        $pessoaB = Pessoa::factory()->create(['nome' => 'Outra Pessoa', 'email' => 'outra@example.com']);
        $userB = User::factory()->create(['email' => 'outra@example.com']);
        $pessoaB->users()->save($userB);

        $this->actingAs($userB);

        $this->assertFalse(ContratosPendentesWidget::canView());
    }
}
