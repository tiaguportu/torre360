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

    public function test_service_v2_substitui_variaveis_usando_classes_css(): void
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Maria Silva',
            'cpf' => '111.222.333-44',
        ]);

        $turma = Turma::factory()->create([
            'nome' => 'Segunda Série B',
        ]);

        // SVG contendo classes nos elementos
        $svgOriginal = '<svg><text class="nome">Placeholder Nome</text><text class="cpf text-style">Placeholder CPF</text><text class="turma_nome">Placeholder Turma</text><image class="foto border-img" href="placeholder.jpg" /></svg>';

        // Processa
        $svgProcessado = TemplateCrachaV2Service::processarSvg($svgOriginal, $pessoa, $turma);

        $this->assertStringContainsString('Maria Silva', $svgProcessado);
        $this->assertStringContainsString('111.222.333-44', $svgProcessado);
        $this->assertStringContainsString('Segunda Série B', $svgProcessado);
        // Deve ter substituído a imagem original pela url de avatar
        $this->assertStringNotContainsString('href="placeholder.jpg"', $svgProcessado);
        $this->assertStringContainsString('href="https://ui-avatars.com', $svgProcessado);
    }

    public function test_service_v2_injeta_foto_usando_pattern_em_formas_geometricas(): void
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Julio Cesar',
        ]);

        // Círculo e retângulo com a classe 'foto'
        $svgOriginal = '<svg><rect class="foto" width="100" height="150" /><circle class="foto" r="50" /></svg>';

        // Processa
        $svgProcessado = TemplateCrachaV2Service::processarSvg($svgOriginal, $pessoa);

        // Deve conter a tag <defs> e o <pattern> com ID dinâmico da pessoa
        $this->assertStringContainsString('<defs', $svgProcessado);
        $this->assertStringContainsString('id="pattern-foto-aluno-'.$pessoa->id.'"', $svgProcessado);

        // As formas geométricas devem ter o fill apontando para o pattern
        $this->assertStringContainsString('fill="url(#pattern-foto-aluno-'.$pessoa->id.')"', $svgProcessado);
    }

    public function test_service_v2_planifica_tags_use_do_svg_para_compatibilidade_pdf(): void
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Renato Abreu',
        ]);

        // SVG contendo um símbolo em defs e uma referência use na camada ativa
        $svgOriginal = '<svg><defs><symbol id="simbolo_teste"><rect width="50" height="50" fill="blue" /></symbol></defs><g class="layer"><use href="#simbolo_teste" id="use_1" transform="matrix(1,0,0,1,10,10)" /></g></svg>';

        // Processa
        $svgProcessado = TemplateCrachaV2Service::processarSvg($svgOriginal, $pessoa);

        // O SVG resultante NÃO deve conter a tag <use>
        $this->assertStringNotContainsString('<use', $svgProcessado);
        // Deve conter o wrapper <g> com o transform correspondente herdado e o rect clonado
        $this->assertStringContainsString('transform="matrix(1,0,0,1,10,10)"', $svgProcessado);
        $this->assertStringContainsString('<rect width="50" height="50" fill="blue"/>', $svgProcessado);
    }

    public function test_service_v2_converte_css_em_atributos_inline_do_svg(): void
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Carlos Magno',
        ]);

        // SVG contendo folha de estilo e elementos de classe
        $svgOriginal = '<svg><style>.cls-azul { fill: #0000ff; stroke-width: 2px; } .cls-borda { stroke: #ff0000; }</style><rect class="cls-azul" width="50" height="50" /><circle class="cls-borda" r="25" /></svg>';

        // Processa
        $svgProcessado = TemplateCrachaV2Service::processarSvg($svgOriginal, $pessoa);

        // O rect deve ter recebido as propriedades fill e stroke-width do CSS diretamente como atributos
        $this->assertStringContainsString('fill="#0000ff"', $svgProcessado);
        $this->assertStringContainsString('stroke-width="2px"', $svgProcessado);
        // O circle deve ter recebido stroke do CSS
        $this->assertStringContainsString('stroke="#ff0000"', $svgProcessado);
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
