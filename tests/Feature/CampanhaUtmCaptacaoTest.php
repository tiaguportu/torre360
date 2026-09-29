<?php

namespace Tests\Feature;

use App\Models\CampanhaMarketing;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampanhaUtmCaptacaoTest extends TestCase
{
    use RefreshDatabase;

    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        StatusInteressado::factory()->create(['nome' => 'Novo', 'is_final' => false, 'is_ganho' => false]);
        OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => $unidade->id,
        ]);
        $this->serie = Serie::create([
            'nome' => '1º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
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
            'alunos' => [['nome' => 'João Aluno', 'serie_id' => $this->serie->id]],
        ], $extra);
    }

    public function test_utm_da_url_e_gravado_e_atribui_campanha_ativa(): void
    {
        $campanha = CampanhaMarketing::factory()->create(['codigo_utm' => 'verao26']);

        $this->get('/quero-matricular?utm_source=Instagram&utm_medium=CPC&utm_campaign=Verao26')->assertOk();

        $this->post('/quero-matricular', $this->payload())->assertRedirect(route('captacao.interessado.sucesso'));

        $lead = Interessado::first();

        $this->assertSame('instagram', $lead->utm_source);
        $this->assertSame('cpc', $lead->utm_medium);
        $this->assertSame('verao26', $lead->utm_campaign);
        $this->assertSame($campanha->id, $lead->campanha_marketing_id);
    }

    public function test_utm_no_proprio_post_tem_prioridade_sobre_a_sessao(): void
    {
        $this->get('/quero-matricular?utm_source=facebook&utm_campaign=antiga');

        $this->post('/quero-matricular', $this->payload(['utm_source' => 'google', 'utm_campaign' => 'nova']));

        $lead = Interessado::first();

        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('nova', $lead->utm_campaign);
    }

    public function test_campanha_inativa_nao_recebe_atribuicao_mas_utm_e_preservado(): void
    {
        $campanha = CampanhaMarketing::factory()->inativa()->create(['codigo_utm' => 'encerrada']);

        $this->get('/quero-matricular?utm_source=email&utm_campaign=encerrada');
        $this->post('/quero-matricular', $this->payload());

        $lead = Interessado::first();

        $this->assertNull($lead->campanha_marketing_id);
        $this->assertSame('encerrada', $lead->utm_campaign);
        $this->assertNotNull($campanha->id);
    }

    public function test_acesso_sem_utm_nao_atribui_nada(): void
    {
        $this->post('/quero-matricular', $this->payload());

        $lead = Interessado::first();

        $this->assertNull($lead->utm_source);
        $this->assertNull($lead->utm_campaign);
        $this->assertNull($lead->campanha_marketing_id);
    }

    public function test_reenvio_do_mesmo_contato_nao_reescreve_a_atribuicao_original(): void
    {
        $primeira = CampanhaMarketing::factory()->create(['codigo_utm' => 'primeira']);
        CampanhaMarketing::factory()->create(['codigo_utm' => 'segunda']);

        $this->get('/quero-matricular?utm_source=instagram&utm_campaign=primeira');
        $this->post('/quero-matricular', $this->payload());

        $this->get('/quero-matricular?utm_source=google&utm_campaign=segunda');
        $this->post('/quero-matricular', $this->payload());

        $this->assertSame(1, Interessado::count());

        $lead = Interessado::first();

        $this->assertSame('instagram', $lead->utm_source);
        $this->assertSame($primeira->id, $lead->campanha_marketing_id);
    }

    public function test_codigo_utm_da_campanha_e_normalizado_em_minusculas(): void
    {
        $campanha = CampanhaMarketing::factory()->create(['codigo_utm' => '  Black-Friday ']);

        $this->assertSame('black-friday', $campanha->fresh()->codigo_utm);
        $this->assertTrue(CampanhaMarketing::porCodigoUtm('BLACK-FRIDAY')->is($campanha));
        $this->assertNull(CampanhaMarketing::porCodigoUtm(null));
        $this->assertNull(CampanhaMarketing::porCodigoUtm('inexistente'));
    }
}
