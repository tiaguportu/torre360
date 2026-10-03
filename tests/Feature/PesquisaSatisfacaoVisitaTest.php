<?php

namespace Tests\Feature;

use App\Enums\StatusVisitaInteressado;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\MensagemWhatsappTemplate;
use App\Models\PesquisaSatisfacaoVisita;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\VisitaInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesquisaSatisfacaoVisitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_visita_obter_ou_criar_pesquisa_gera_token_unico_e_url_publica(): void
    {
        $pessoa = Pessoa::factory()->create(['telefone' => '11999998888']);
        $lead = Interessado::factory()->create(['pessoa_id' => $pessoa->id]);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now()->addDay(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = $visita->obterOuCriarPesquisa();

        $this->assertNotNull($pesquisa);
        $this->assertNotEmpty($pesquisa->token);
        $this->assertSame($visita->id, $pesquisa->visita_interessado_id);
        $this->assertSame($lead->id, $pesquisa->interessado_id);
        $this->assertFalse($pesquisa->isRespondida());
        $this->assertStringContainsString('/pesquisa-visita/'.$pesquisa->token, $pesquisa->url_publica);

        // Chamar novamente deve retornar a mesma instância sem duplicar
        $pesquisaMesma = $visita->obterOuCriarPesquisa();
        $this->assertSame($pesquisa->id, $pesquisaMesma->id);
        $this->assertSame(1, PesquisaSatisfacaoVisita::where('visita_interessado_id', $visita->id)->count());
    }

    public function test_link_whatsapp_da_pesquisa_contem_telefone_e_link_codificado(): void
    {
        $pessoa = Pessoa::factory()->create(['telefone' => '11988887777']);
        $lead = Interessado::factory()->create(['pessoa_id' => $pessoa->id]);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = $visita->obterOuCriarPesquisa();
        $linkWhatsapp = $pesquisa->linkWhatsapp();

        $this->assertNotNull($linkWhatsapp);
        $this->assertStringContainsString('api.whatsapp.com/send', $linkWhatsapp);
        $this->assertStringContainsString('5511988887777', $linkWhatsapp);
        $this->assertStringContainsString($pesquisa->token, $linkWhatsapp);
    }

    public function test_acesso_a_pagina_publica_da_pesquisa_com_token_valido(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Maria Silva']);
        $lead = Interessado::factory()->create(['pessoa_id' => $pessoa->id]);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = $visita->obterOuCriarPesquisa();

        $response = $this->get(route('pesquisa-visita.show', ['token' => $pesquisa->token]));

        $response->assertOk();
        $response->assertSee('Maria');
        $response->assertSee('Como foi sua experiência no');
    }

    public function test_submeter_pesquisa_de_satisfacao_grava_notas_e_registra_historico_contato(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Carlos Souza']);
        $lead = Interessado::factory()->create(['pessoa_id' => $pessoa->id]);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = $visita->obterOuCriarPesquisa();

        $dados = [
            'nota_nps' => 10,
            'nota_atendimento' => 5,
            'nota_infraestrutura' => 4,
            'nota_proposta_pedagogica' => 5,
            'comentario' => 'Equipe extremamente acolhedora e estrutura impecável!',
        ];

        $response = $this->post(route('pesquisa-visita.store', ['token' => $pesquisa->token]), $dados);

        $response->assertRedirect(route('pesquisa-visita.sucesso', ['token' => $pesquisa->token]));

        $pesquisa->refresh();
        $this->assertTrue($pesquisa->isRespondida());
        $this->assertSame(10, $pesquisa->nota_nps);
        $this->assertSame('Promotor', $pesquisa->classificacaoNps());
        $this->assertSame(5, $pesquisa->nota_atendimento);
        $this->assertSame('Equipe extremamente acolhedora e estrutura impecável!', $pesquisa->comentario);
        $this->assertNotNull($pesquisa->respondido_em);

        // Verifica o registro automático na timeline do lead
        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $lead->id,
        ]);

        $historico = HistoricoContato::where('interessado_id', $lead->id)->latest('id')->first();
        $this->assertStringContainsString('NPS: 10/10 (Promotor)', $historico->relato);
        $this->assertStringContainsString('Equipe extremamente acolhedora', $historico->relato);
    }

    public function test_pesquisa_ja_respondida_redireciona_direto_para_tela_de_sucesso(): void
    {
        $lead = Interessado::factory()->create();
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = $visita->obterOuCriarPesquisa();
        $pesquisa->update([
            'nota_nps' => 9,
            'respondido_em' => now(),
        ]);

        $response = $this->get(route('pesquisa-visita.show', ['token' => $pesquisa->token]));

        $response->assertRedirect(route('pesquisa-visita.sucesso', ['token' => $pesquisa->token]));
    }

    public function test_regua_followup_interpola_link_pesquisa(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Ana Clara']);
        $lead = Interessado::factory()->create(['pessoa_id' => $pessoa->id]);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $regua = new ReguaFollowUp([
            'assunto' => 'O que achou da visita, {{PRIMEIRO_NOME}}?',
            'mensagem' => 'Acesse nossa pesquisa: {{LINK_PESQUISA}} ou [LinkPesquisa]',
        ]);

        $interpolado = $regua->interpolarMensagem($lead, $visita);

        $this->assertStringContainsString('O que achou da visita, Ana?', $interpolado['assunto']);
        $this->assertStringContainsString('/pesquisa-visita/', $interpolado['mensagem']);
        $this->assertStringNotContainsString('{{LINK_PESQUISA}}', $interpolado['mensagem']);
        $this->assertStringNotContainsString('[LinkPesquisa]', $interpolado['mensagem']);
    }

    public function test_gerar_mensagem_whatsapp_utiliza_template_cadastrado_no_banco(): void
    {
        MensagemWhatsappTemplate::create([
            'nome' => 'Pesquisa de Satisfação Pós-Visita',
            'conteudo' => 'Olá, [Nome do Responsável]! Avalie o tour de [Nome do Aluno]: [Link da Pesquisa da Visita]',
            'ativo' => true,
        ]);

        $pessoa = Pessoa::factory()->create(['nome' => 'Juliana Mendes']);
        $lead = Interessado::factory()->create(['pessoa_id' => $pessoa->id]);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'data_hora' => now(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = $visita->obterOuCriarPesquisa();
        $mensagem = $pesquisa->gerarMensagemWhatsapp();

        $this->assertStringContainsString('Olá, Juliana Mendes!', $mensagem);
        $this->assertStringContainsString($pesquisa->url_publica, $mensagem);
        $this->assertStringNotContainsString('[Nome do Responsável]', $mensagem);
        $this->assertStringNotContainsString('[Link da Pesquisa da Visita]', $mensagem);
    }

    public function test_filtro_situacao_visita_e_relacionamento_ultima_visita(): void
    {
        // Lead 1: Realizou visita
        $leadRealizado = Interessado::factory()->create();
        $visitaRealizada = VisitaInteressado::create([
            'interessado_id' => $leadRealizado->id,
            'data_hora' => now()->subDay(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);
        $pesquisa = $visitaRealizada->obterOuCriarPesquisa();
        $pesquisa->update(['nota_nps' => 10, 'respondido_em' => now()]);

        // Lead 2: Tem visita agendada futura
        $leadAgendado = Interessado::factory()->create();
        VisitaInteressado::create([
            'interessado_id' => $leadAgendado->id,
            'data_hora' => now()->addDays(2),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        // Lead 3: Sem nenhuma visita
        $leadSemVisita = Interessado::factory()->create();

        // Testa relacionamentos do model
        $this->assertNotNull($leadRealizado->ultimaVisitaRealizada);
        $this->assertSame($visitaRealizada->id, $leadRealizado->ultimaVisitaRealizada->id);
        $this->assertSame($visitaRealizada->id, $leadRealizado->ultimaVisita->id);
        $this->assertNull($leadAgendado->ultimaVisitaRealizada);
        $this->assertNotNull($leadAgendado->ultimaVisita);
        $this->assertNull($leadSemVisita->ultimaVisita);

        // Testa queries do filtro
        $realizados = Interessado::whereHas('visitas', fn ($q) => $q->where('status', StatusVisitaInteressado::Realizada))->pluck('id');
        $this->assertTrue($realizados->contains($leadRealizado->id));
        $this->assertFalse($realizados->contains($leadAgendado->id));
        $this->assertFalse($realizados->contains($leadSemVisita->id));

        $agendados = Interessado::whereHas('visitas', fn ($q) => $q->where('status', StatusVisitaInteressado::Agendada)->where('data_hora', '>=', now()))->pluck('id');
        $this->assertTrue($agendados->contains($leadAgendado->id));
        $this->assertFalse($agendados->contains($leadRealizado->id));
        $this->assertFalse($agendados->contains($leadSemVisita->id));

        $semVisitas = Interessado::whereDoesntHave('visitas')->pluck('id');
        $this->assertTrue($semVisitas->contains($leadSemVisita->id));
        $this->assertFalse($semVisitas->contains($leadRealizado->id));
        $this->assertFalse($semVisitas->contains($leadAgendado->id));
    }
}
