<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IndicacaoInteressado;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Família Indica Família": o link `/quero-matricular?indicacao=CODIGO` (gerado por
 * `Pessoa::linkIndicacao()`) precisa vincular o lead do formulário público à família que indicou.
 */
class CaptacaoIndicacaoTest extends TestCase
{
    use RefreshDatabase;

    private Pessoa $indicador;

    private string $codigo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        StatusInteressado::factory()->create(['nome' => 'Novo', 'is_final' => false, 'is_ganho' => false]);
        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Mail::fake();

        $this->indicador = Pessoa::create([
            'nome' => 'Maria Indicadora',
            'email' => 'maria.indicadora@example.com',
            'telefone' => '(11) 97777-1111',
            'cpf' => '11122233344',
        ]);
        $this->codigo = $this->indicador->obterOuCriarCodigoIndicacao();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        $unidade = Unidade::firstOrCreate(['nome' => 'Unidade Teste'], ['flag_ativo' => true]);

        return array_merge([
            'tipo_preenchimento' => 'responsavel',
            'responsavel_nome' => 'Carla Amiga',
            'responsavel_telefone' => '(11) 98888-2222',
            'responsavel_email' => 'carla.amiga@example.com',
            'alunos' => [['nome' => 'Lucas Amigo', 'unidade_id' => $unidade->id]],
        ], $extra);
    }

    public function test_link_de_indicacao_guarda_o_codigo_normalizado_na_sessao(): void
    {
        $this->get('/quero-matricular?indicacao='.strtolower($this->codigo))
            ->assertOk()
            ->assertSessionHas('captacao_indicacao', $this->codigo);
    }

    public function test_codigo_com_formato_invalido_e_descartado_sem_quebrar_a_pagina(): void
    {
        $this->get('/quero-matricular?indicacao='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertSessionMissing('captacao_indicacao');
    }

    public function test_envio_apos_o_link_vincula_o_lead_a_familia_indicadora(): void
    {
        $this->get('/quero-matricular?indicacao='.$this->codigo);

        $this->post('/quero-matricular', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('captacao.interessado.sucesso'));

        $lead = Interessado::with('pessoa', 'origem')->firstOrFail();

        $this->assertDatabaseHas('indicacao_interessados', [
            'indicador_pessoa_id' => $this->indicador->id,
            'interessado_id' => $lead->id,
            'codigo_indicacao' => $this->codigo,
            'status' => IndicacaoInteressado::STATUS_PENDENTE,
        ]);
        $this->assertSame('Indicação', $lead->origem->nome);
        $this->assertStringContainsString('Maria Indicadora', $lead->observacoes);
    }

    public function test_codigo_enviado_no_proprio_post_tambem_funciona_e_a_sessao_e_limpa(): void
    {
        $this->get('/quero-matricular?indicacao='.$this->codigo);

        $this->post('/quero-matricular', $this->payload())->assertSessionMissing('captacao_indicacao');
        $this->assertSame(1, IndicacaoInteressado::count());

        // Sem sessão: o código no corpo da requisição basta (formulários embutidos/externos).
        $this->flushSession();
        $this->post('/quero-matricular', $this->payload([
            'responsavel_email' => 'outra.familia@example.com',
            'responsavel_telefone' => '(11) 95555-3333',
            'indicacao' => $this->codigo,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, IndicacaoInteressado::count());
    }

    public function test_codigo_inexistente_nao_cria_indicacao_e_mantem_origem_site(): void
    {
        $this->get('/quero-matricular?indicacao=NAO-EXISTE');

        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(0, IndicacaoInteressado::count());
        $this->assertSame('Site', Interessado::with('origem')->firstOrFail()->origem->nome);
    }

    public function test_auto_indicacao_por_email_e_ignorada(): void
    {
        $this->get('/quero-matricular?indicacao='.$this->codigo);

        $this->post('/quero-matricular', $this->payload([
            'responsavel_email' => 'MARIA.INDICADORA@example.com',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, IndicacaoInteressado::count());
    }

    public function test_auto_indicacao_por_telefone_com_outra_mascara_e_ignorada(): void
    {
        $this->get('/quero-matricular?indicacao='.$this->codigo);

        $this->post('/quero-matricular', $this->payload([
            'responsavel_telefone' => '+55 11 97777-1111',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, IndicacaoInteressado::count());
    }

    public function test_reenviar_o_formulario_nao_duplica_nem_troca_quem_indicou(): void
    {
        $outra = Pessoa::create(['nome' => 'Outra Indicadora', 'email' => 'outra.ind@example.com', 'telefone' => '(11) 96666-0000']);
        $outroCodigo = $outra->obterOuCriarCodigoIndicacao();

        $this->get('/quero-matricular?indicacao='.$this->codigo);
        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();

        $this->get('/quero-matricular?indicacao='.$outroCodigo);
        $this->post('/quero-matricular', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, IndicacaoInteressado::count());
        $this->assertSame($this->indicador->id, IndicacaoInteressado::firstOrFail()->indicador_pessoa_id);
    }

    public function test_origem_escolhida_pela_familia_e_respeitada_mesmo_com_indicacao(): void
    {
        $instagram = OrigemInteressado::create(['nome' => 'Instagram']);

        $this->get('/quero-matricular?indicacao='.$this->codigo);
        $this->post('/quero-matricular', $this->payload(['como_conheceu' => $instagram->id]))->assertSessionHasNoErrors();

        $this->assertSame($instagram->id, Interessado::firstOrFail()->origem_interessado_id);
        $this->assertSame(1, IndicacaoInteressado::count());
    }
}
