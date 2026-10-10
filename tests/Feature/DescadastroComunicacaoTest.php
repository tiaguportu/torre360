<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Services\ReguaFollowUpService;
use App\Support\DescadastroComunicacao;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Link de descadastro dos e-mails da régua (item 6 da auditoria): assinado, sem login, confirmado por POST.
 */
class DescadastroComunicacaoTest extends TestCase
{
    use RefreshDatabase;

    private Pessoa $pessoa;

    private Interessado $lead;

    protected function setUp(): void
    {
        parent::setUp();

        TipoContatoInteressado::firstOrCreate(['nome' => 'E-mail']);
        $this->pessoa = Pessoa::factory()->create(['nome' => 'Maria Silva', 'email' => 'maria@exemplo.com', 'aceita_comunicacao' => true]);
        $this->lead = Interessado::create([
            'pessoa_id' => $this->pessoa->id,
            'status_interessado_id' => StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false])->id,
            'origem_interessado_id' => OrigemInteressado::create(['nome' => 'Site'])->id,
        ]);
    }

    public function test_abrir_o_link_so_mostra_a_confirmacao_e_nao_cancela(): void
    {
        $this->get(DescadastroComunicacao::url($this->pessoa))
            ->assertOk()
            ->assertSee('Cancelar e-mails da escola?')
            ->assertSee('Sim, cancelar o envio')
            ->assertSee('noindex', false);

        $this->assertTrue($this->pessoa->fresh()->aceita_comunicacao, 'Scanners de e-mail abrem links sem que a pessoa tenha pedido.');
    }

    public function test_confirmar_descadastra_registra_a_data_e_deixa_rastro_no_lead(): void
    {
        $this->post(DescadastroComunicacao::url($this->pessoa))
            ->assertOk()
            ->assertSee('Você não receberá mais e-mails');

        $pessoa = $this->pessoa->fresh();
        $this->assertFalse($pessoa->aceita_comunicacao);
        $this->assertNotNull($pessoa->descadastrado_em);

        $historico = HistoricoContato::where('interessado_id', $this->lead->id)->firstOrFail();
        $this->assertTrue($historico->automatico, 'Registro do sistema: não conta como contato com a família.');
        $this->assertStringContainsString('pediu para não receber mais e-mails', $historico->relato);
    }

    public function test_confirmar_duas_vezes_nao_duplica_o_registro_nem_muda_a_data(): void
    {
        $url = DescadastroComunicacao::url($this->pessoa);

        $this->post($url)->assertOk();
        $primeiraData = $this->pessoa->fresh()->descadastrado_em;
        $this->travel(2)->days();
        $this->post($url)->assertOk();

        $this->assertSame(1, HistoricoContato::where('interessado_id', $this->lead->id)->count());
        $this->assertTrue($primeiraData->equalTo($this->pessoa->fresh()->descadastrado_em));
    }

    public function test_quem_ja_cancelou_ve_o_aviso_em_vez_do_botao(): void
    {
        $this->pessoa->descadastrarDeComunicacoes();

        $this->get(DescadastroComunicacao::url($this->pessoa))
            ->assertOk()
            ->assertSee('Você já cancelou')
            ->assertDontSee('Sim, cancelar o envio');
    }

    public function test_link_sem_assinatura_ou_adulterado_e_recusado(): void
    {
        $outra = Pessoa::factory()->create(['aceita_comunicacao' => true]);

        // Sem assinatura.
        $this->get('/comunicacao/descadastrar/'.$this->pessoa->id)->assertForbidden();
        $this->post('/comunicacao/descadastrar/'.$this->pessoa->id)->assertForbidden();

        // Assinatura de uma pessoa aplicada ao número de outra.
        $assinatura = parse_url(DescadastroComunicacao::url($this->pessoa), PHP_URL_QUERY);
        $this->post('/comunicacao/descadastrar/'.$outra->id.'?'.$assinatura)->assertForbidden();

        $this->assertTrue($outra->fresh()->aceita_comunicacao);
        $this->assertTrue($this->pessoa->fresh()->aceita_comunicacao);
    }

    public function test_link_continua_valido_meses_depois(): void
    {
        $url = DescadastroComunicacao::url($this->pessoa);

        $this->travel(200)->days();

        $this->post($url)->assertOk();
        $this->assertFalse($this->pessoa->fresh()->aceita_comunicacao);
        $this->assertTrue(URL::hasValidSignature(request()->create($url)));
    }

    public function test_regua_nao_envia_mais_depois_do_descadastro(): void
    {
        Mail::fake();
        ReguaFollowUp::query()->delete();
        ReguaFollowUp::create([
            'nome' => 'Boas-vindas',
            'gatilho' => GatilhoReguaFollowUp::LeadCriado,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Olá',
            'mensagem' => 'Oi',
            'is_ativo' => true,
        ]);
        DB::table('interessado')->where('id', $this->lead->id)->update(['created_at' => Carbon::today()->setHour(9)]);

        $this->post(DescadastroComunicacao::url($this->pessoa))->assertOk();

        $resultado = (new ReguaFollowUpService)->processarReguaDiaria(Carbon::today());

        $this->assertSame(0, $resultado['total_notificacoes_enviadas']);
        Mail::assertNothingSent();
    }
}
