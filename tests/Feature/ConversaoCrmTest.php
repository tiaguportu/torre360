<?php

namespace Tests\Feature;

use App\Filament\Resources\CampanhaMarketings\Pages\CreateCampanhaMarketing;
use App\Filament\Resources\CampanhaMarketings\Pages\ListCampanhaMarketings;
use App\Filament\Widgets\ConversaoCampanhaWidget;
use App\Filament\Widgets\ConversaoOrigemWidget;
use App\Models\CampanhaMarketing;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\CrmConversaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConversaoCrmTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $status;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->status = StatusInteressado::factory()->create(['nome' => 'Novo']);
    }

    private function lead(OrigemInteressado $origem, ?CampanhaMarketing $campanha = null, bool $convertido = false): Interessado
    {
        return Interessado::factory()->create([
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $this->status->id,
            'campanha_marketing_id' => $campanha?->id,
            'data_conversao' => $convertido ? now() : null,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_conversao_por_campanha_calcula_leads_matriculas_taxa_e_custos(): void
    {
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $campanha = CampanhaMarketing::factory()->create(['nome' => 'Verão', 'custo' => 1000, 'canal' => 'meta_ads']);

        $this->lead($origem, $campanha, convertido: true);
        $this->lead($origem, $campanha, convertido: true);
        $this->lead($origem, $campanha);
        $this->lead($origem, $campanha);
        $this->lead($origem); // sem campanha: não entra na conta da campanha

        $linha = collect(CrmConversaoService::porCampanha())->firstWhere('id', $campanha->id);

        $this->assertSame(4, $linha['leads']);
        $this->assertSame(2, $linha['matriculados']);
        $this->assertSame(50.0, $linha['taxa']);
        $this->assertSame(250.0, $linha['custo_por_lead']);
        $this->assertSame(500.0, $linha['custo_por_matricula']);
        $this->assertSame('Meta Ads (Facebook/Instagram)', $linha['canal']);
    }

    public function test_campanha_sem_leads_tem_indicadores_zerados_sem_divisao_por_zero(): void
    {
        $campanha = CampanhaMarketing::factory()->create(['custo' => 500]);

        $linha = collect(CrmConversaoService::porCampanha())->firstWhere('id', $campanha->id);

        $this->assertSame(0, $linha['leads']);
        $this->assertSame(0.0, $linha['taxa']);
        $this->assertNull($linha['custo_por_lead']);
        $this->assertNull($linha['custo_por_matricula']);
    }

    public function test_campanha_sem_matriculas_nao_tem_custo_por_matricula(): void
    {
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $campanha = CampanhaMarketing::factory()->create(['custo' => 300]);
        $this->lead($origem, $campanha);
        $this->lead($origem, $campanha);

        $linha = collect(CrmConversaoService::porCampanha())->firstWhere('id', $campanha->id);

        $this->assertSame(150.0, $linha['custo_por_lead']);
        $this->assertNull($linha['custo_por_matricula']);
    }

    public function test_conversao_por_origem(): void
    {
        $site = OrigemInteressado::create(['nome' => 'Site']);
        $indicacao = OrigemInteressado::create(['nome' => 'Indicação']);

        $this->lead($site, convertido: true);
        $this->lead($site);
        $this->lead($site);
        $this->lead($site);
        $this->lead($indicacao, convertido: true);

        $linhas = collect(CrmConversaoService::porOrigem());

        $this->assertSame(4, $linhas->firstWhere('id', $site->id)['leads']);
        $this->assertSame(25.0, $linhas->firstWhere('id', $site->id)['taxa']);
        $this->assertSame(100.0, $linhas->firstWhere('id', $indicacao->id)['taxa']);
        $this->assertSame($site->id, $linhas->first()['id'], 'Ordenado por volume de leads.');
    }

    public function test_resumo_agrega_todas_as_campanhas(): void
    {
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $a = CampanhaMarketing::factory()->create(['custo' => 100]);
        $b = CampanhaMarketing::factory()->create(['custo' => 200]);

        $this->lead($origem, $a, convertido: true);
        $this->lead($origem, $b);
        $this->lead($origem, $b);
        $this->lead($origem, $b);

        $resumo = CrmConversaoService::resumoCampanhas();

        $this->assertSame(4, $resumo['leads']);
        $this->assertSame(1, $resumo['matriculados']);
        $this->assertSame(25.0, $resumo['taxa']);
        $this->assertSame(300.0, $resumo['custo']);
    }

    public function test_widgets_de_conversao_renderizam_os_dados(): void
    {
        $origem = OrigemInteressado::create(['nome' => 'Instagram']);
        $campanha = CampanhaMarketing::factory()->create(['nome' => 'Matrículas Abertas']);
        $this->lead($origem, $campanha, convertido: true);

        Livewire::actingAs($this->superAdmin())
            ->test(ConversaoCampanhaWidget::class)
            ->assertSee('Matrículas Abertas');

        Livewire::actingAs($this->superAdmin())
            ->test(ConversaoOrigemWidget::class)
            ->assertSee('Instagram');
    }

    public function test_lista_de_campanhas_mostra_contagem_de_leads_e_matriculas(): void
    {
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $campanha = CampanhaMarketing::factory()->create();
        $this->lead($origem, $campanha, convertido: true);
        $this->lead($origem, $campanha);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCampanhaMarketings::class)
            ->assertCanSeeTableRecords([$campanha])
            ->assertTableColumnStateSet('leads_count', 2, $campanha)
            ->assertTableColumnStateSet('matriculados_count', 1, $campanha)
            ->assertTableColumnStateSet('taxa_conversao', '50,0%', $campanha);
    }

    public function test_cadastro_de_campanha_normaliza_codigo_utm_e_exige_nome(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(CreateCampanhaMarketing::class)
            ->fillForm(['nome' => null])
            ->call('create')
            ->assertHasFormErrors(['nome' => 'required']);

        Livewire::actingAs($admin)
            ->test(CreateCampanhaMarketing::class)
            ->fillForm([
                'nome' => 'Black Friday',
                'canal' => 'google_ads',
                'codigo_utm' => 'Black-Friday',
                'custo' => 1500,
                'ativa' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('campanha_marketing', ['nome' => 'Black Friday', 'codigo_utm' => 'black-friday']);
    }

    public function test_codigo_utm_duplicado_e_rejeitado(): void
    {
        CampanhaMarketing::factory()->create(['codigo_utm' => 'promo']);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCampanhaMarketing::class)
            ->fillForm(['nome' => 'Outra', 'codigo_utm' => 'promo'])
            ->call('create')
            ->assertHasFormErrors(['codigo_utm' => 'unique']);
    }
}
