<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\LinkPortalAdmissaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Revogação do link do Portal de Admissão: validade máxima de 7 dias, link expirado nunca revivido e
 * "Gerar novo link" que realmente invalida a URL anterior (antes só trocava o convite legado, e o token do
 * portal — o que a família usa — seguia valendo por 90 dias).
 */
class LinkPortalAdmissaoRevogacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function criarLead(array $atributos = []): Interessado
    {
        $status = StatusInteressado::firstOrCreate(['nome' => 'Novo'], ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $pessoa = Pessoa::create(['nome' => 'Ana Paula', 'email' => 'ana@example.com', 'telefone' => '11999998888']);

        $lead = Interessado::create($atributos + [
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);

        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas Paula']);
        TipoDocumento::create(['nome' => 'RG', 'flag_obrigatorio' => true, 'status' => 'ativo']);

        return $lead;
    }

    private function portal(string $token)
    {
        return $this->get(route('candidato.documentos.show', ['token' => $token]));
    }

    // ------------------------------------------------------------------ validade de 7 dias

    public function test_token_sem_data_de_validade_nao_abre_mais_o_portal(): void
    {
        $lead = $this->criarLead(['token_documentos' => 'token-legado-sem-validade-xyz', 'token_documentos_expira_em' => null]);

        $this->portal('token-legado-sem-validade-xyz')->assertStatus(410);
    }

    public function test_token_legado_sem_validade_e_substituido_quando_a_equipe_gera_o_link(): void
    {
        $lead = $this->criarLead(['token_documentos' => 'token-legado-sem-validade-xyz', 'token_documentos_expira_em' => null]);

        $novo = $lead->obterOuCriarTokenDocumentos();

        $this->assertNotSame('token-legado-sem-validade-xyz', $novo);
        $this->portal('token-legado-sem-validade-xyz')->assertStatus(410);
        $this->portal($novo)->assertOk();
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $lead->fresh()->token_documentos_expira_em->timestamp, 10);
    }

    public function test_link_para_de_funcionar_ao_completar_sete_dias(): void
    {
        $lead = $this->criarLead();
        $token = $lead->obterOuCriarTokenDocumentos();

        $this->travelTo(now()->addDays(6)->addHours(23));
        $this->portal($token)->assertOk();

        $this->travelTo(now()->addHours(2));
        $this->portal($token)->assertStatus(410);
    }

    public function test_abrir_o_portal_pela_familia_nao_estende_a_validade(): void
    {
        $lead = $this->criarLead();
        $token = $lead->obterOuCriarTokenDocumentos();
        $expiracao = $lead->fresh()->token_documentos_expira_em;

        $this->travelTo(now()->addDays(3));
        $this->portal($token)->assertOk();

        $this->assertTrue($expiracao->equalTo($lead->fresh()->token_documentos_expira_em));
    }

    public function test_migracao_reduz_para_sete_dias_os_links_antigos_de_noventa_sem_estender_nenhum(): void
    {
        $antigo = $this->criarLead(['token_documentos' => 'token-90-dias', 'token_documentos_expira_em' => now()->addDays(90)]);
        $curto = $this->criarLead(['token_documentos' => 'token-3-dias', 'token_documentos_expira_em' => now()->addDays(3)]);
        $vencido = $this->criarLead(['token_documentos' => 'token-vencido', 'token_documentos_expira_em' => now()->subDay()]);
        $semToken = $this->criarLead(['token_documentos' => null, 'token_documentos_expira_em' => null]);
        $expiracaoCurta = $curto->fresh()->token_documentos_expira_em;
        $expiracaoVencida = $vencido->fresh()->token_documentos_expira_em;

        (require database_path('migrations/2026_10_08_090000_limita_validade_dos_links_do_portal_a_sete_dias.php'))->up();

        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $antigo->fresh()->token_documentos_expira_em->timestamp, 10);
        $this->assertTrue($expiracaoCurta->equalTo($curto->fresh()->token_documentos_expira_em));
        $this->assertTrue($expiracaoVencida->equalTo($vencido->fresh()->token_documentos_expira_em));
        $this->assertNull($semToken->fresh()->token_documentos_expira_em);

        // O link de 90 dias deixa de valer ao completar 7 dias, como qualquer outro.
        $this->portal('token-90-dias')->assertOk();
        $this->travelTo(now()->addDays(7)->addMinute());
        $this->portal('token-90-dias')->assertStatus(410);
    }

    // ------------------------------------------------------------------ revogação

    public function test_gerar_novo_link_revoga_a_url_anterior_em_todas_as_acoes_do_portal(): void
    {
        $lead = $this->criarLead();
        $antigo = $lead->obterOuCriarTokenDocumentos();
        $this->portal($antigo)->assertOk();

        $urlNova = app(LinkPortalAdmissaoService::class)->gerarNovo($lead->fresh());
        $novo = basename($urlNova);

        $this->assertNotSame($antigo, $novo);

        $this->portal($antigo)->assertStatus(410);
        $this->post(route('candidato.documentos.upload', ['token' => $antigo]), [])->assertStatus(410);
        $this->delete(route('candidato.documentos.remover', ['token' => $antigo, 'documento' => 1]))->assertStatus(410);
        $this->post(route('candidato.documentos.dados', ['token' => $antigo]), [])->assertStatus(403);

        $this->portal($novo)->assertOk();
    }

    public function test_gerar_novo_link_tambem_invalida_o_convite_legado(): void
    {
        $lead = $this->criarLead(['token_convite' => 'convite-legado-enviado-por-whatsapp', 'token_convite_expira_em' => now()->addDays(5)]);
        $this->get('/quero-matricular/convite/convite-legado-enviado-por-whatsapp')->assertRedirect();

        app(LinkPortalAdmissaoService::class)->gerarNovo($lead->fresh());

        $this->get('/quero-matricular/convite/convite-legado-enviado-por-whatsapp')->assertStatus(410);
        $this->assertNull($lead->fresh()->token_convite);
    }

    public function test_gerar_novo_link_registra_na_linha_do_tempo_quem_revogou(): void
    {
        $lead = $this->criarLead();
        $lead->obterOuCriarTokenDocumentos();
        $usuario = User::factory()->create();

        app(LinkPortalAdmissaoService::class)->gerarNovo($lead->fresh(), $usuario->id);

        $registro = HistoricoContato::where('interessado_id', $lead->id)->latest('id')->firstOrFail();
        $this->assertTrue($registro->automatico);
        $this->assertSame($usuario->id, $registro->usuario_id);
        $this->assertStringContainsString('revogado', $registro->relato);
    }

    public function test_convite_legado_nao_abre_o_portal_diretamente(): void
    {
        $this->criarLead(['token_convite' => 'convite-legado-aaaa', 'token_convite_expira_em' => now()->addDays(5)]);

        $this->portal('convite-legado-aaaa')->assertStatus(410);
    }

    // ------------------------------------------------------------------ convite legado (redirecionamento)

    public function test_convite_legado_vigente_redireciona_sem_renovar_o_portal(): void
    {
        $lead = $this->criarLead(['token_convite' => 'convite-legado-bbbb', 'token_convite_expira_em' => now()->addDays(5)]);
        $token = $lead->obterOuCriarTokenDocumentos();
        $lead->update(['token_documentos_expira_em' => now()->addHours(2)]);
        $expiracao = $lead->fresh()->token_documentos_expira_em;

        $this->get('/quero-matricular/convite/convite-legado-bbbb')
            ->assertRedirect(route('candidato.documentos.show', ['token' => $token]));

        $this->assertTrue($expiracao->equalTo($lead->fresh()->token_documentos_expira_em));
    }

    public function test_convite_legado_expirado_responde_410(): void
    {
        $this->criarLead(['token_convite' => 'convite-legado-cccc', 'token_convite_expira_em' => now()->subMinute()]);

        $this->get('/quero-matricular/convite/convite-legado-cccc')->assertStatus(410);
    }

    public function test_convite_legado_ja_usado_ainda_redireciona_dentro_da_validade(): void
    {
        $lead = $this->criarLead([
            'token_convite' => 'convite-legado-dddd',
            'token_convite_expira_em' => now()->addDays(2),
            'token_convite_usado_em' => now()->subHour(),
        ]);

        $this->get('/quero-matricular/convite/convite-legado-dddd')->assertRedirect();

        $this->assertNotNull($lead->fresh()->token_documentos);
    }

    public function test_portal_emitido_pelo_convite_legado_nao_passa_da_validade_do_proprio_convite(): void
    {
        $lead = $this->criarLead(['token_convite' => 'convite-legado-eeee', 'token_convite_expira_em' => now()->addDays(2)]);

        $this->get('/quero-matricular/convite/convite-legado-eeee')->assertRedirect();

        $this->assertEqualsWithDelta(now()->addDays(2)->timestamp, $lead->fresh()->token_documentos_expira_em->timestamp, 10);
    }

    // ------------------------------------------------------------------ ação "Gerar novo link"

    private function usuarioCom(array $permissoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);

        foreach ($permissoes as $nome) {
            $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        return $usuario;
    }

    public function test_acao_gerar_novo_link_so_aparece_para_quem_pode_editar_e_existindo_link(): void
    {
        $lead = $this->criarLead();

        // Sem nenhum link emitido ainda: nada a revogar.
        Livewire::actingAs($this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado']))
            ->test(ListInteressados::class)
            ->assertTableActionHidden('regenerarConvite', $lead);

        $lead->obterOuCriarTokenDocumentos();

        Livewire::actingAs($this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado']))
            ->test(ListInteressados::class)
            ->assertTableActionVisible('regenerarConvite', $lead);

        Livewire::actingAs($this->usuarioCom(['ViewAny:Interessado', 'View:Interessado']))
            ->test(ListInteressados::class)
            ->assertTableActionHidden('regenerarConvite', $lead);
    }

    public function test_acao_gerar_novo_link_revoga_o_token_do_portal(): void
    {
        $lead = $this->criarLead();
        $antigo = $lead->obterOuCriarTokenDocumentos();

        Livewire::actingAs($this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado']))
            ->test(ListInteressados::class)
            ->callTableAction('regenerarConvite', $lead)
            ->assertNotified('Novo link gerado — o anterior foi revogado');

        $this->portal($antigo)->assertStatus(410);
        $this->portal($lead->fresh()->token_documentos)->assertOk();
    }
}
