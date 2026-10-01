<?php

namespace Tests\Feature;

use App\Filament\Resources\CampanhaMarketings\Pages\ListCampanhaMarketings;
use App\Filament\Resources\ComunicacaoEmMassas\Pages\ListComunicacaoEmMassas;
use App\Filament\Resources\Cursos\Pages\ListCursos;
use App\Filament\Resources\LandingLeads\Pages\ListLandingLeads;
use App\Filament\Resources\MensagemWhatsappTemplates\Pages\ListMensagemWhatsappTemplates;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Garante que cada tela da sidebar coberta pelo plano de Ajuda tem o botão
 * "Ajuda" e que o modal monta sem erro. Cresce a cada fase (docs/ajuda_botoes_sidebar.md).
 */
class AjudaCoberturaSidebarTest extends TestCase
{
    use RefreshDatabase;

    public static function telas(): array
    {
        return [
            'Cursos' => [ListCursos::class],
            'Campanhas de Marketing' => [ListCampanhaMarketings::class],
            'Comunicação em Massa' => [ListComunicacaoEmMassas::class],
            'Leads da Landing Page' => [ListLandingLeads::class],
            'Modelos de WhatsApp' => [ListMensagemWhatsappTemplates::class],
        ];
    }

    #[DataProvider('telas')]
    public function test_tela_possui_botao_de_ajuda(string $pagina): void
    {
        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test($pagina)
            ->assertActionExists('ajuda')
            ->mountAction('ajuda')
            ->assertHasNoActionErrors();
    }
}
