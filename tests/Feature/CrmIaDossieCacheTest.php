<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O modal do Dossiê IA monta o formulário a cada interação: gerar o dossiê dentro dele chamava o Gemini
 * de novo a cada clique. O dossiê agora é reaproveitado (cache de 15 min) e descartado quando o histórico
 * do lead muda; respostas de contingência nunca ficam guardadas.
 */
class CrmIaDossieCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'chave-de-teste']);
        Http::preventStrayRequests();
    }

    private function criarInteressado(): Interessado
    {
        return Interessado::create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Família Cache'])->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function respostaGemini(string $resumo = 'Família engajada.'): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'resumo_executivo' => $resumo,
            'temperatura_sugerida' => 'quente',
            'proxima_acao_sugerida' => 'Convidar para o tour.',
            'dossie_markdown' => '### Relatório',
        ])]]]]]];
    }

    private function mockGemini(int $chamadas, ?callable $resposta = null): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->times($chamadas)->andReturnUsing($resposta ?? fn (): array => $this->respostaGemini());
        $this->app->instance(GeminiAgentService::class, $mock);
    }

    public function test_dossie_do_lead_so_chama_o_gemini_uma_vez_e_reaproveita_o_resultado(): void
    {
        $lead = $this->criarInteressado();
        $this->mockGemini(1);

        $servico = app(CrmIaVendasService::class);
        $primeiro = $servico->dossieDoLead($lead);
        $segundo = $servico->dossieDoLead($lead);

        $this->assertSame($primeiro, $segundo);
        $this->assertSame('Família engajada.', $segundo['resumo_executivo']);
        $this->assertTrue(Cache::has(CrmIaVendasService::chaveCacheDossie($lead->id)));
    }

    public function test_resposta_de_contingencia_nao_fica_em_cache(): void
    {
        $lead = $this->criarInteressado();
        $chamada = 0;

        $this->mockGemini(2, function () use (&$chamada): array {
            if (++$chamada === 1) {
                throw new \RuntimeException('Gemini fora do ar');
            }

            return $this->respostaGemini('Dossiê real.');
        });

        $servico = app(CrmIaVendasService::class);

        $contingencia = $servico->dossieDoLead($lead);
        $this->assertTrue($contingencia['fallback']);
        $this->assertFalse(Cache::has(CrmIaVendasService::chaveCacheDossie($lead->id)));

        $real = $servico->dossieDoLead($lead);
        $this->assertArrayNotHasKey('fallback', $real);
        $this->assertSame('Dossiê real.', $real['resumo_executivo']);
    }

    public function test_novo_registro_no_historico_descarta_o_dossie_em_cache(): void
    {
        $lead = $this->criarInteressado();
        $this->mockGemini(2);

        $servico = app(CrmIaVendasService::class);
        $servico->dossieDoLead($lead);

        HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::create(['nome' => 'Ligação'])->id,
            'relato' => 'Mãe ligou pedindo valores.',
            'data_contato' => now(),
        ]);

        $this->assertFalse(Cache::has(CrmIaVendasService::chaveCacheDossie($lead->id)));

        $servico->dossieDoLead($lead->fresh());
    }

    public function test_pdf_reaproveita_o_dossie_ja_gerado(): void
    {
        $lead = $this->criarInteressado();
        $this->mockGemini(1);

        $servico = app(CrmIaVendasService::class);
        $servico->dossieDoLead($lead);

        $pdf = $servico->gerarPdfDossie($lead->fresh());

        $this->assertNotEmpty($pdf->output());
    }

    public function test_abrir_e_usar_o_modal_do_dossie_chama_o_gemini_apenas_uma_vez(): void
    {
        $lead = $this->criarInteressado();
        $this->mockGemini(1);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()->subDay()]);
        $admin->assignRole('super_admin');

        // Montar o modal e submeter reconstrói o formulário várias vezes: sem cache seriam várias chamadas.
        Livewire::actingAs($admin)
            ->test(EditInteressado::class, ['record' => $lead->getKey()])
            ->callAction('dossieIa', ['registrar_historico' => false, 'atualizar_temperatura' => false])
            ->assertHasNoActionErrors();
    }
}
