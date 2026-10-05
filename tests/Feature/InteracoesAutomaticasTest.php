<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Services\ConsultorWhatsappService;
use App\Services\LeadScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registros gerados pelo sistema/IA (`historico_contato.automatico`) ficam na linha do tempo, mas
 * não contam como interação: não tiram o lead de "estagnado", não pontuam no Lead Score e não
 * entram no resumo repassado ao consultor.
 */
class InteracoesAutomaticasTest extends TestCase
{
    use RefreshDatabase;

    private TipoContatoInteressado $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipo = TipoContatoInteressado::firstOrCreate(['nome' => 'E-mail']);
    }

    private function criarLeadAntigo(int $diasDeCriacao = 10): Interessado
    {
        $status = StatusInteressado::firstOrCreate(['nome' => 'Novo'], ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);

        $lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);

        $lead->forceFill(['created_at' => now()->subDays($diasDeCriacao)])->saveQuietly();

        return $lead->fresh();
    }

    private function registrar(Interessado $lead, bool $automatico, int $diasAtras = 1, ?string $resultado = null): HistoricoContato
    {
        return HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => $this->tipo->id,
            'relato' => $automatico ? 'E-mail automático da régua' : 'Conversa com a mãe',
            'data_contato' => now()->subDays($diasAtras),
            'resultado' => $resultado,
            'automatico' => $automatico,
        ]);
    }

    public function test_registro_automatico_nao_tira_o_lead_de_estagnado(): void
    {
        $lead = $this->criarLeadAntigo();
        $this->registrar($lead, automatico: true, diasAtras: 1);

        $lead = $lead->fresh();

        $this->assertTrue($lead->estaEstagnado());
        $this->assertSame(10, $lead->diasSemInteracao());
        $this->assertTrue(Interessado::estagnados()->whereKey($lead->id)->exists());
    }

    public function test_contato_humano_recente_tira_o_lead_de_estagnado(): void
    {
        $lead = $this->criarLeadAntigo();
        $this->registrar($lead, automatico: false, diasAtras: 1);

        $lead = $lead->fresh();

        $this->assertFalse($lead->estaEstagnado());
        $this->assertSame(1, $lead->diasSemInteracao());
        $this->assertFalse(Interessado::estagnados()->whereKey($lead->id)->exists());
    }

    public function test_lead_com_contato_humano_antigo_e_automatico_recente_continua_estagnado(): void
    {
        $lead = $this->criarLeadAntigo(30);
        $this->registrar($lead, automatico: false, diasAtras: 15);
        $this->registrar($lead, automatico: true, diasAtras: 1);

        $lead = $lead->fresh();

        $this->assertSame(15, $lead->diasSemInteracao());
        $this->assertTrue(Interessado::estagnados()->whereKey($lead->id)->exists());
    }

    public function test_ultimo_historico_e_total_de_contatos_ignoram_automaticos(): void
    {
        $lead = $this->criarLeadAntigo();
        $humano = $this->registrar($lead, automatico: false, diasAtras: 5);
        $this->registrar($lead, automatico: true, diasAtras: 1);

        $lead = $lead->fresh();

        $this->assertSame($humano->id, $lead->ultimoHistorico->id);
        $this->assertSame(1, $lead->totalContatos());
        $this->assertSame(2, $lead->historicos()->count());
    }

    public function test_lead_score_nao_pontua_registros_automaticos(): void
    {
        $lead = $this->criarLeadAntigo();
        $semContatos = LeadScoreService::calcular($lead->fresh());

        $this->registrar($lead, automatico: true, diasAtras: 0, resultado: 'agendou_visita');
        $this->registrar($lead, automatico: true, diasAtras: 0, resultado: 'agendou_visita');
        $comAutomaticos = LeadScoreService::calcular($lead->fresh());

        $this->assertSame($semContatos, $comAutomaticos);

        $this->registrar($lead, automatico: false, diasAtras: 0, resultado: 'agendou_visita');
        $comHumano = LeadScoreService::calcular($lead->fresh());

        $this->assertGreaterThan($semContatos, $comHumano);
    }

    public function test_resumo_para_o_consultor_nao_lista_registros_automaticos(): void
    {
        $lead = $this->criarLeadAntigo();
        $this->registrar($lead, automatico: false, diasAtras: 3);
        $this->registrar($lead, automatico: true, diasAtras: 1);

        $lead = Interessado::with(ConsultorWhatsappService::RELACOES)->findOrFail($lead->id);
        $mensagem = app(ConsultorWhatsappService::class)->mensagemDoInteressado($lead);

        $this->assertStringContainsString('Conversa com a mãe', $mensagem);
        $this->assertStringNotContainsString('E-mail automático da régua', $mensagem);
    }

    public function test_registro_manual_e_o_padrao(): void
    {
        $lead = $this->criarLeadAntigo();

        $contato = HistoricoContato::create([
            'interessado_id' => $lead->id,
            'tipo_contato_interessado_id' => $this->tipo->id,
            'relato' => 'Ligação',
            'data_contato' => now(),
        ])->fresh();

        $this->assertFalse($contato->automatico);
    }
}
