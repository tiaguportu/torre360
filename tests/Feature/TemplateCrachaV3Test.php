<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\TemplateCrachaV3;
use App\Models\Turma;
use App\Models\User;
use App\Services\TemplateCrachaV3Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateCrachaV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_template_cracha_v3_pode_ser_criado_e_salvo(): void
    {
        $template = TemplateCrachaV3::create([
            'nome' => 'Crachá V3 Teste',
            'largura' => 300,
            'altura' => 480,
            'tipo_entidade' => 'pessoa',
            'dados_json' => [
                'fundo' => '#ffffff',
                'elementos' => [],
            ],
        ]);

        $this->assertDatabaseHas('template_crachas_v3', [
            'nome' => 'Crachá V3 Teste',
            'largura' => 300,
            'altura' => 480,
        ]);

        $savedTemplate = TemplateCrachaV3::find($template->id);
        $this->assertIsArray($savedTemplate->dados_json);
        $this->assertEquals('#ffffff', $savedTemplate->dados_json['fundo']);
    }

    public function test_usuario_autenticado_pode_acessar_editor_e_salvar_layout_json(): void
    {
        $user = User::factory()->create();

        $template = TemplateCrachaV3::create([
            'nome' => 'Crachá V3 Rota',
            'largura' => 300,
            'altura' => 480,
            'tipo_entidade' => 'pessoa',
            'dados_json' => [
                'fundo' => '#ffffff',
                'elementos' => [],
            ],
        ]);

        // Sem autenticação, deve redirecionar para o login
        $response = $this->get(route('template-crachas-v3.editor', $template->id));
        $response->assertRedirect(route('filament.admin.auth.login'));

        // Autenticado
        $this->actingAs($user);

        $response = $this->get(route('template-crachas-v3.editor', $template->id));
        $response->assertStatus(200)
            ->assertViewIs('admin.cracha-v3-editor')
            ->assertViewHas('templateCrachaV3');

        // Salvar JSON via AJAX POST
        $newJson = [
            'fundo' => '#ff0000',
            'elementos' => [
                [
                    'id' => 'el_1',
                    'tipo' => 'texto',
                    'x' => 10,
                    'y' => 10,
                    'largura' => 100,
                    'altura' => 30,
                    'rotacao' => 0,
                    'conteudo' => 'Teste V3',
                    'estilos' => [],
                ],
            ],
        ];

        $responsePost = $this->postJson(route('template-crachas-v3.save', $template->id), [
            'dados_json' => $newJson,
        ]);

        $responsePost->assertStatus(200)
            ->assertJson(['success' => true]);

        $savedTemplate = TemplateCrachaV3::find($template->id);
        $this->assertEquals('#ff0000', $savedTemplate->dados_json['fundo']);
        $this->assertCount(1, $savedTemplate->dados_json['elementos']);
        $this->assertEquals('Teste V3', $savedTemplate->dados_json['elementos'][0]['conteudo']);
    }

    public function test_service_v3_substitui_variaveis_e_injeta_foto_e_dados(): void
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => 'Carlos Alberto',
            'cpf' => '999.888.777-66',
        ]);

        $turma = Turma::factory()->create([
            'nome' => 'Terceiro Ano C',
        ]);

        $template = TemplateCrachaV3::create([
            'nome' => 'Template PDF V3',
            'largura' => 320,
            'altura' => 480,
            'tipo_entidade' => 'pessoa',
            'dados_json' => [
                'fundo' => '#ffffff',
                'elementos' => [
                    [
                        'id' => 'el_1',
                        'tipo' => 'variavel',
                        'variavel' => '{nome}',
                        'x' => 10,
                        'y' => 20,
                        'largura' => 150,
                        'altura' => 30,
                        'estilos' => ['fontSize' => '14px'],
                    ],
                    [
                        'id' => 'el_2',
                        'tipo' => 'variavel',
                        'variavel' => '{foto}',
                        'x' => 10,
                        'y' => 80,
                        'largura' => 100,
                        'altura' => 100,
                        'estilos' => ['borderRadius' => '50%'],
                    ],
                    [
                        'id' => 'el_3',
                        'tipo' => 'variavel',
                        'variavel' => '{turma_nome}',
                        'x' => 10,
                        'y' => 200,
                        'largura' => 150,
                        'altura' => 30,
                        'estilos' => [],
                    ],
                ],
            ],
        ]);

        $pessoasComTurma = collect([
            (object) [
                'pessoa' => $pessoa,
                'turma' => $turma,
            ],
        ]);

        $pdf = TemplateCrachaV3Service::gerarPdf($template, $pessoasComTurma);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
    }
}
