<?php

namespace Tests\Feature;

use App\Filament\Resources\TemplateContratos\Pages\ListTemplateContratos;
use App\Models\TemplateContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TemplateContratoCloneTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $this->adminUser->assignRole($role);
        session(['active_role' => 'super_admin']);
    }

    public function test_bulk_action_de_clonagem_de_templates_de_contrato(): void
    {
        $this->actingAs($this->adminUser);

        $template1 = TemplateContrato::create([
            'nome' => 'Template A',
            'conteudo' => 'Conteúdo do template A',
            'is_padrao' => true,
        ]);

        $template2 = TemplateContrato::create([
            'nome' => 'Template B',
            'conteudo' => 'Conteúdo do template B',
            'is_padrao' => false,
        ]);

        Livewire::test(ListTemplateContratos::class)
            ->assertStatus(200)
            ->callTableBulkAction('clonar', [$template1, $template2])
            ->assertHasNoTableBulkActionErrors();

        // Verifica que os clones foram criados
        $this->assertDatabaseHas('template_contratos', [
            'nome' => 'Template A (Cópia)',
            'conteudo' => 'Conteúdo do template A',
            'is_padrao' => false, // O clone do padrão não deve ser padrão
        ]);

        $this->assertDatabaseHas('template_contratos', [
            'nome' => 'Template B (Cópia)',
            'conteudo' => 'Conteúdo do template B',
            'is_padrao' => false,
        ]);

        // O original que era padrão ainda deve ser padrão
        $template1->refresh();
        $this->assertTrue($template1->is_padrao);
    }
}
