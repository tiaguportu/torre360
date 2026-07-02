<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\TemplateCrachaV2;
use App\Models\Turma;
use App\Models\User;
use App\Services\TemplateCrachaV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateCrachaV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_template_cracha_v2_pode_ser_criado_e_salvo(): void
    {
        $template = TemplateCrachaV2::create([
            'nome' => 'Crachá V2 Teste',
            'largura' => 300,
            'altura' => 480,
            'tipo_entidade' => 'pessoa',
            'svg_content' => '<svg></svg>',
        ]);

        $this->assertDatabaseHas('template_crachas_v2', [
            'nome' => 'Crachá V2 Teste',
            'largura' => 300,
            'altura' => 480,
        ]);

        $savedTemplate = TemplateCrachaV2::find($template->id);
        $this->assertEquals('<svg></svg>', $savedTemplate->svg_content);
    }

    public function test_usuario_autenticado_pode_acessar_editor_e_salvar_svg(): void
    {
        $user = User::factory()->create();

        $template = TemplateCrachaV2::create([
            'nome' => 'Crachá V2 Rota',
            'largura' => 300,
            'altura' => 480,
            'tipo_entidade' => 'pessoa',
            'svg_content' => '<svg>Inicial</svg>',
        ]);

        // Sem autenticação, deve redirecionar para o login
        $response = $this->get(route('template-crachas-v2.editor', $template->id));
        $response->assertRedirect(route('filament.admin.auth.login'));

        // Autenticado
        $this->actingAs($user);

        $response = $this->get(route('template-crachas-v2.editor', $template->id));
        $response->assertStatus(200)
            ->assertViewIs('admin.cracha-v2-editor')
            ->assertViewHas('templateCrachaV2');

        // Salvar SVG via AJAX POST
        $newSvg = '<svg><text>{nome}</text></svg>';
        $responsePost = $this->postJson(route('template-crachas-v2.save', $template->id), [
            'svg_content' => $newSvg,
        ]);

        $responsePost->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('template_crachas_v2', [
            'id' => $template->id,
            'svg_content' => $newSvg,
        ]);
    }

    public function test_service_v2_substitui_variaveis_e_injeta_foto_no_svg(): void
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Tiago Souza',
            'cpf' => '987.654.321-00',
        ]);

        $turma = Turma::factory()->create([
            'nome' => 'Primeira Série A',
        ]);

        $svgOriginal = '<svg><text>Nome: {nome}</text><text>Turma: {turma_nome}</text><image id="foto-aluno-v2" href="placeholder.jpg" /></svg>';

        // Processa
        $svgProcessado = TemplateCrachaV2Service::processarSvg($svgOriginal, $pessoa, $turma);

        $this->assertStringContainsString('Nome: Tiago Souza', $svgProcessado);
        $this->assertStringContainsString('Turma: Primeira Série A', $svgProcessado);
        // Deve ter substituído o href original da foto por ui-avatars ou base64
        $this->assertStringNotContainsString('href="placeholder.jpg"', $svgProcessado);
        $this->assertStringContainsString('id="foto-aluno-v2"', $svgProcessado);
        $this->assertStringContainsString('href="https://ui-avatars.com', $svgProcessado);
    }

    public function test_geracao_de_pdf_de_crachas_v2(): void
    {
        $template = TemplateCrachaV2::create([
            'nome' => 'Template PDF V2',
            'largura' => 300,
            'altura' => 480,
            'tipo_entidade' => 'pessoa',
            'svg_content' => '<svg><text>{nome}</text><image id="foto-aluno-v2" href="" /></svg>',
        ]);

        $pessoa = Pessoa::factory()->create(['nome' => 'Juliana Lima']);

        $pessoasComTurma = collect([
            (object) [
                'pessoa' => $pessoa,
                'turma' => null,
            ],
        ]);

        $pdf = TemplateCrachaV2Service::gerarPdf($template, $pessoasComTurma);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
    }
}
