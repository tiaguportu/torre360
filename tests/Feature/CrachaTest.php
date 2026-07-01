<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\TemplateCracha;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrachaTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_cracha_pode_ser_criado_e_salvo(): void
    {
        $template = TemplateCracha::factory()->create([
            'nome' => 'Crachá de Visitante',
            'largura' => 300,
            'altura' => 450,
            'dados_layout' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'text',
                        'text' => '{nome}',
                        'fill' => '#000000',
                    ],
                ],
            ],
        ]);

        $this->assertDatabaseHas('template_crachas', [
            'nome' => 'Crachá de Visitante',
            'largura' => 300,
            'altura' => 450,
        ]);

        $savedTemplate = TemplateCracha::find($template->id);
        $this->assertIsArray($savedTemplate->dados_layout);
        $this->assertEquals('{nome}', $savedTemplate->dados_layout['objects'][0]['text']);
    }

    public function test_geracao_de_pdf_de_crachas_com_substituicao_de_variaveis(): void
    {
        // Cria template
        $template = TemplateCracha::factory()->create([
            'nome' => 'Template Teste',
            'largura' => 300,
            'altura' => 500,
            'dados_layout' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'text',
                        'left' => 10,
                        'top' => 20,
                        'width' => 200,
                        'height' => 30,
                        'text' => 'Nome: {nome}',
                        'fill' => '#111111',
                    ],
                    [
                        'type' => 'text',
                        'left' => 10,
                        'top' => 60,
                        'width' => 200,
                        'height' => 30,
                        'text' => 'Cargo: {profissao}',
                        'fill' => '#222222',
                    ],
                ],
            ],
        ]);

        // Cria pessoas
        $pessoa1 = Pessoa::factory()->create([
            'nome' => 'Carlos Henrique',
            'profissao' => 'Desenvolvedor',
        ]);

        $pessoa2 = Pessoa::factory()->create([
            'nome' => 'Ana Clara',
            'profissao' => 'Designer',
        ]);

        $records = collect([$pessoa1, $pessoa2]);
        $layout = $template->dados_layout;
        $objects = $layout['objects'] ?? [];
        $backgroundImage = $layout['backgroundImage']['src'] ?? null;

        // Renderiza a view do PDF manualmente para inspecionar a substituição de strings
        $html = view('pdf.cracha-lote', [
            'pessoas' => $records,
            'objects' => $objects,
            'backgroundImage' => $backgroundImage,
            'largura' => $template->largura * 0.75,
            'altura' => $template->altura * 0.75,
        ])->render();

        // Verifica se os dados dinâmicos foram inseridos no HTML que é mandado ao DomPDF
        $this->assertStringContainsString('Carlos Henrique', $html);
        $this->assertStringContainsString('Desenvolvedor', $html);
        $this->assertStringContainsString('Ana Clara', $html);
        $this->assertStringContainsString('Designer', $html);

        // Verifica se o DomPDF consegue processar o HTML sem lançar exceções
        $pdf = Pdf::loadHTML($html);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
    }
}
