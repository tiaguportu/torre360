<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Schemas\InteressadoForm;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InteressadoAlertaAcompanhamentoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function lead(?User $consultor, array $atributos = []): Interessado
    {
        $status = StatusInteressado::firstOrCreate(
            ['nome' => 'Em Atendimento'],
            ['cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false],
        );

        return Interessado::create(array_merge([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Maria Responsável'])->id,
            'usuario_id' => $consultor?->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'status_interessado_id' => $status->id,
            'data_proximo_contato' => now()->subDays(2),
        ], $atributos));
    }

    public function test_descricao_do_alerta_mostra_o_email_do_consultor(): void
    {
        $consultor = User::factory()->create(['name' => 'Carla Souza', 'email' => 'carla@escola.com.br']);

        $descricao = (string) InteressadoForm::descricaoDoAlerta($this->lead($consultor)->fresh());

        $this->assertStringContainsString('Uma notificação será enviada ao sistema e ao e-mail do consultor responsável.', $descricao);
        $this->assertStringContainsString('carla@escola.com.br', $descricao);
        $this->assertStringContainsString('Carla Souza', $descricao);
    }

    public function test_descricao_do_alerta_escapa_html_do_nome_e_do_email(): void
    {
        $consultor = User::factory()->create(['name' => '<b>Carla</b>', 'email' => 'carla@escola.com.br']);

        $descricao = (string) InteressadoForm::descricaoDoAlerta($this->lead($consultor)->fresh());

        $this->assertStringNotContainsString('<b>Carla</b>', $descricao);
        $this->assertStringContainsString('&lt;b&gt;Carla&lt;/b&gt;', $descricao);
    }

    public function test_descricao_do_alerta_avisa_quando_o_consultor_nao_tem_email(): void
    {
        $consultor = User::factory()->create(['name' => 'Carla Souza']);
        $consultor->forceFill(['email' => ''])->saveQuietly();

        $descricao = (string) InteressadoForm::descricaoDoAlerta($this->lead($consultor)->fresh());

        $this->assertStringContainsString('não tem e-mail cadastrado', $descricao);
        $this->assertStringContainsString('só a notificação no sistema será enviada', $descricao);
    }

    public function test_descricao_do_alerta_avisa_quando_nao_ha_consultor(): void
    {
        $descricao = (string) InteressadoForm::descricaoDoAlerta($this->lead(null)->fresh());

        $this->assertStringContainsString('não tem consultor responsável', $descricao);
        $this->assertStringNotContainsString('@', $descricao);
    }

    public function test_modal_de_confirmacao_na_edicao_exibe_o_email_que_recebera_o_alerta(): void
    {
        $consultor = User::factory()->create(['name' => 'Carla Souza', 'email' => 'carla@escola.com.br']);
        $lead = $this->lead($consultor);

        Livewire::actingAs($this->admin())
            ->test(EditInteressado::class, ['record' => $lead->getKey()])
            ->mountAction(TestAction::make('alerta_contato')->schemaComponent('alertaContato'))
            ->assertMountedActionModalSee([
                'Enviar Alerta de Acompanhamento?',
                'Uma notificação será enviada ao sistema e ao e-mail do consultor responsável.',
                'carla@escola.com.br',
            ], escape: false);
    }
}
