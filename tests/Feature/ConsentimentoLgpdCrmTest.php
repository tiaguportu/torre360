<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Services\ConviteMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Consentimento no formulário público de captação (item 6 da auditoria do CRM): o aceite é obrigatório e
 * fica registrado na pessoa (quando, qual texto, por onde e de qual IP).
 */
class ConsentimentoLgpdCrmTest extends TestCase
{
    use RefreshDatabase;

    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.recaptcha.site_key' => null,
            'services.recaptcha.secret' => null,
            'crm.lgpd.versao_consentimento' => '2026-10-teste',
        ]);

        StatusInteressado::factory()->create(['nome' => 'Novo', 'is_final' => false, 'is_ganho' => false]);
        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => Unidade::create(['nome' => 'Unidade Sede'])->id,
        ]);
        $this->serie = Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'tipo_preenchimento' => 'responsavel',
            'responsavel_nome' => 'Maria Responsável',
            'responsavel_telefone' => '11999990000',
            'responsavel_email' => 'maria@example.com',
            'consentimento' => '1',
            'alunos' => [['nome' => 'João Aluno', 'serie_id' => $this->serie->id]],
        ], $extra);
    }

    public function test_formulario_sem_o_aceite_e_recusado_e_nada_e_gravado(): void
    {
        $semAceite = $this->payload();
        unset($semAceite['consentimento']);

        $this->from('/quero-matricular')->post('/quero-matricular', $semAceite)
            ->assertRedirect('/quero-matricular')
            ->assertSessionHasErrors(['consentimento' => 'Para enviar, é preciso autorizar o uso dos dados para o atendimento de admissão.']);

        $this->assertSame(0, Pessoa::count());
        $this->assertSame(0, Interessado::count());
    }

    public function test_aceite_recusado_explicitamente_tambem_nao_passa(): void
    {
        $this->post('/quero-matricular', $this->payload(['consentimento' => '0']))->assertSessionHasErrors('consentimento');

        $this->assertSame(0, Interessado::count());
    }

    public function test_aceite_fica_registrado_com_data_versao_origem_e_ip(): void
    {
        $this->travelTo('2026-10-09 10:15:00');

        $this->post('/quero-matricular', $this->payload())->assertRedirect(route('captacao.interessado.sucesso'));

        $pessoa = Pessoa::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('2026-10-09 10:15:00', $pessoa->consentimento_em->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-teste', $pessoa->consentimento_versao);
        $this->assertSame('formulario_captacao', $pessoa->consentimento_origem);
        $this->assertSame('127.0.0.1', $pessoa->consentimento_ip);
        $this->assertTrue($pessoa->aceita_comunicacao);
    }

    public function test_novo_envio_atualiza_o_registro_do_aceite(): void
    {
        $this->travelTo('2026-10-09 10:00:00');
        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();

        config(['crm.lgpd.versao_consentimento' => '2027-01']);
        $this->travelTo('2027-01-15 08:00:00');
        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();

        $pessoa = Pessoa::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('2027-01', $pessoa->consentimento_versao);
        $this->assertSame('2027-01-15', $pessoa->consentimento_em->toDateString());
    }

    public function test_reenvio_nao_religa_quem_pediu_descadastro(): void
    {
        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();
        $pessoa = Pessoa::where('email', 'maria@example.com')->firstOrFail();
        $pessoa->descadastrarDeComunicacoes();

        // Qualquer pessoa pode digitar o e-mail de outra no formulário público: o aceite novo não desfaz o opt-out.
        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();

        $pessoa->refresh();
        $this->assertFalse($pessoa->aceita_comunicacao);
        $this->assertNotNull($pessoa->descadastrado_em);
    }

    public function test_formulario_exibe_a_caixa_de_aceite_com_politica_e_contato_quando_configurados(): void
    {
        config([
            'crm.lgpd.url_politica_privacidade' => 'https://escola.example/privacidade',
            'crm.lgpd.contato_privacidade' => 'privacidade@escola.example',
        ]);

        $this->get('/quero-matricular')
            ->assertOk()
            ->assertSee('name="consentimento"', false)
            ->assertSee('Autorizo a escola a usar os dados informados')
            ->assertSee('https://escola.example/privacidade', false)
            ->assertSee('privacidade@escola.example')
            ->assertSee('link que vai no rodapé de cada mensagem');
    }

    public function test_formulario_sem_politica_configurada_nao_mostra_link_quebrado(): void
    {
        config(['crm.lgpd.url_politica_privacidade' => null, 'crm.lgpd.contato_privacidade' => null]);

        $this->get('/quero-matricular')
            ->assertOk()
            ->assertSee('name="consentimento"', false)
            ->assertSee('exclusão à secretaria')
            ->assertDontSee('rel="noopener">Política de Privacidade</a>.', false);
    }

    public function test_aceite_da_pre_matricula_tambem_fica_registrado_na_pessoa(): void
    {
        $pessoa = Pessoa::factory()->create(['email' => 'convite@example.com']);
        $lead = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => StatusInteressado::firstOrFail()->id,
            'origem_interessado_id' => OrigemInteressado::firstOrFail()->id,
        ]);

        app(ConviteMatriculaService::class)->confirmar(
            $lead->load('pessoa'),
            [],
            [],
            ['responsaveis' => [['nome' => 'Ana']], 'lgpd_aceite_em' => now()->toIso8601String(), 'lgpd_ip' => '203.0.113.9'],
        );

        $pessoa->refresh();
        $this->assertSame('pre_matricula', $pessoa->consentimento_origem);
        $this->assertSame('203.0.113.9', $pessoa->consentimento_ip);
        $this->assertNotNull($lead->fresh()->dados_pre_matricula_em);
    }
}
