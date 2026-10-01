<?php

namespace Tests\Feature;

use App\Filament\Pages\FechamentoCicloLetivo;
use App\Filament\Resources\CampanhaMarketings\Pages\ListCampanhaMarketings;
use App\Filament\Resources\ComunicacaoEmMassas\Pages\ListComunicacaoEmMassas;
use App\Filament\Resources\Coordenadors\Pages\ListCoordenadors;
use App\Filament\Resources\CicloPreceptorias\Pages\ListCicloPreceptorias;
use App\Filament\Resources\Cursos\Pages\ListCursos;
use App\Filament\Resources\DiaNaoLetivos\Pages\ListDiaNaoLetivos;
use App\Filament\Resources\Fornecedores\Pages\ListFornecedores;
use App\Filament\Resources\FrequenciaEscolars\Pages\ListFrequenciaEscolars;
use App\Filament\Resources\Habilidades\Pages\ListHabilidades;
use App\Filament\Resources\LandingLeads\Pages\ListLandingLeads;
use App\Filament\Resources\MensagemWhatsappTemplates\Pages\ListMensagemWhatsappTemplates;
use App\Filament\Resources\Notas\Pages\ListNotas;
use App\Filament\Resources\OrdemServicoResource\Pages\ListOrdemServicos;
use App\Filament\Resources\PlanoAulas\Pages\ListPlanoAulas;
use App\Filament\Resources\RelatorioPreceptorias\Pages\ListRelatorioPreceptorias;
use App\Filament\Resources\Salas\Pages\ListSalas;
use App\Filament\Resources\TemplateRelatorioPreceptorias\Pages\ListTemplateRelatorioPreceptorias;
use App\Filament\Resources\TransacaoBancarias\Pages\ListTransacaoBancarias;
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
            'Coordenadores' => [ListCoordenadors::class],
            'Frequências Escolares' => [ListFrequenciaEscolars::class],
            'Planos de Aula' => [ListPlanoAulas::class],
            'Salas' => [ListSalas::class],
            'Fechamento do Ciclo Letivo' => [FechamentoCicloLetivo::class],
            'Notas' => [ListNotas::class],
            'Habilidades' => [ListHabilidades::class],
            'Ciclos de Preceptoria' => [ListCicloPreceptorias::class],
            'Relatórios de Preceptoria' => [ListRelatorioPreceptorias::class],
            'Templates de Relatório' => [ListTemplateRelatorioPreceptorias::class],
            'Dias Não Letivos' => [ListDiaNaoLetivos::class],
            'Fornecedores' => [ListFornecedores::class],
            'Transações Bancárias' => [ListTransacaoBancarias::class],
            'Ordens de Serviço' => [ListOrdemServicos::class],
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
