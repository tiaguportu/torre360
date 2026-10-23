<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\User;
use App\Services\CurriculoIaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidatoCurriculoIaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Config::set('services.gemini.key', 'fake-gemini-key');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Garante as permissões e papéis necessários
        foreach (['ViewAny:Candidato', 'View:Candidato', 'Create:Candidato', 'Update:Candidato', 'Delete:Candidato', 'DeleteAny:Candidato'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());
    }

    public function test_modelo_candidato_persiste_dados_e_casts_corretamente(): void
    {
        $candidato = Candidato::create([
            'nome_completo' => 'Mariana Oliveira Souza',
            'email' => 'mariana.souza@exemplo.com',
            'telefone' => '(11) 98765-4321',
            'data_nascimento' => '1992-05-15',
            'idade' => 32,
            'cargo_pretendido' => 'Professora de Língua Portuguesa',
            'escola_atual' => 'Colégio Santo Agostinho',
            'local_moradia' => 'Bairro Gutierrez, Belo Horizonte - MG',
            'pretensao_salarial' => 4500.50,
            'formacao_academica' => 'Licenciatura em Letras - UFMG',
            'experiencias_profissionais' => '6 anos como docente do Ensino Fundamental II.',
            'habilidades' => 'Gramática aplicada, literatura juvenil, mediação de leitura.',
            'resumo_perfil' => 'Educadora comunicativa e experiente.',
            'status' => StatusCandidato::Novo,
            'curriculo_path' => 'candidatos/curriculos/curriculo_mariana.pdf',
            'foto_path' => 'candidatos/fotos/mariana.jpg',
            'audio_entrevista_path' => 'candidatos/audios/entrevista_mariana.ogg',
            'impressoes_entrevistador' => 'Excelente postura e domínio de conteúdo.',
            'audio_transcricao_resumo' => 'Entrevistador elogiou a didática e confirmou disponibilidade imediata.',
        ]);

        $this->assertDatabaseHas('candidatos', [
            'id' => $candidato->id,
            'nome_completo' => 'Mariana Oliveira Souza',
            'cargo_pretendido' => 'Professora de Língua Portuguesa',
            'escola_atual' => 'Colégio Santo Agostinho',
            'local_moradia' => 'Bairro Gutierrez, Belo Horizonte - MG',
            'status' => 'novo',
        ]);

        $this->assertEquals(32, $candidato->idade_calculada);
        $this->assertStringContainsString('candidatos/curriculos/curriculo_mariana.pdf', (string) $candidato->curriculo_url);
        $this->assertStringContainsString('candidatos/fotos/mariana.jpg', (string) $candidato->foto_url);
        $this->assertStringContainsString('candidatos/audios/entrevista_mariana.ogg', (string) $candidato->audio_url);
        $this->assertEquals(StatusCandidato::Novo, $candidato->status);
    }

    public function test_curriculo_ia_service_interpreta_curriculo_e_extrai_dados(): void
    {
        $fakeJson = json_encode([
            'nome_completo' => 'Prof. Roberto Mendes',
            'email' => 'roberto.mendes@escola.com',
            'telefone' => '(21) 97777-8888',
            'data_nascimento' => '1988-11-20',
            'idade' => 36,
            'cargo_pretendido' => 'Professor de Matemática',
            'escola_atual' => 'Colégio Batista',
            'local_moradia' => 'Tijuca, Rio de Janeiro - RJ',
            'pretensao_salarial' => 5200.00,
            'formacao_academica' => 'Bacharelado e Licenciatura em Matemática - UFRJ',
            'experiencias_profissionais' => '10 anos lecionando para o Ensino Médio.',
            'habilidades' => 'Raciocínio lógico, preparação para olimpíadas OBMEP.',
            'resumo_perfil' => 'Docente dedicado com alta taxa de aprovação.',
            'tem_foto_perfil' => false,
            'foto_bounding_box' => null,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $fakeJson],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $caminhoTemp = tempnam(sys_get_temp_dir(), 'curriculo_test_').'.pdf';
        file_put_contents($caminhoTemp, '%PDF-1.4 Fake PDF Content');

        try {
            /** @var CurriculoIaService $service */
            $service = app(CurriculoIaService::class);
            $dados = $service->analisarCurriculo($caminhoTemp, 'application/pdf', 'curriculo_roberto.pdf');

            $this->assertEquals('Prof. Roberto Mendes', $dados['nome_completo']);
            $this->assertEquals('roberto.mendes@escola.com', $dados['email']);
            $this->assertEquals(36, $dados['idade']);
            $this->assertEquals('Professor de Matemática', $dados['cargo_pretendido']);
            $this->assertEquals('Colégio Batista', $dados['escola_atual']);
            $this->assertEquals('Tijuca, Rio de Janeiro - RJ', $dados['local_moradia']);
            $this->assertEquals(5200.00, $dados['pretensao_salarial']);
            $this->assertEquals('Bacharelado e Licenciatura em Matemática - UFRJ', $dados['formacao_academica']);
        } finally {
            @unlink($caminhoTemp);
        }
    }

    public function test_curriculo_ia_service_extrai_foto_com_bounding_box_em_imagem(): void
    {
        // Cria uma imagem real temporária de 400x400 via GD
        $img = imagecreatetruecolor(400, 400);
        $corFundo = imagecolorallocate($img, 240, 240, 240);
        imagefilledrectangle($img, 0, 0, 400, 400, $corFundo);

        $corRosto = imagecolorallocate($img, 200, 100, 100);
        imagefilledellipse($img, 200, 200, 150, 150, $corRosto);

        $caminhoImagemTemp = tempnam(sys_get_temp_dir(), 'foto_test_').'.jpg';
        imagejpeg($img, $caminhoImagemTemp);
        imagedestroy($img);

        $fakeJson = json_encode([
            'nome_completo' => 'Juliana Paes Castro',
            'cargo_pretendido' => 'Pedagoga',
            'escola_atual' => 'Escola Arco-Íris',
            'local_moradia' => 'Belo Horizonte - MG',
            'pretensao_salarial' => 4000.00,
            'tem_foto_perfil' => true,
            'foto_bounding_box' => [200, 200, 800, 800], // [ymin, xmin, ymax, xmax] normalizado 0-1000
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $fakeJson],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        try {
            /** @var CurriculoIaService $service */
            $service = app(CurriculoIaService::class);
            $dados = $service->analisarCurriculo($caminhoImagemTemp, 'image/jpeg', 'curriculo_juliana.jpg');

            $this->assertNotEmpty($dados['foto_path']);
            $this->assertStringStartsWith('candidatos/fotos/', $dados['foto_path']);
            $this->assertTrue(Storage::disk('public')->exists($dados['foto_path']));
        } finally {
            @unlink($caminhoImagemTemp);
        }
    }

    public function test_curriculo_ia_service_interpreta_audio_de_whatsapp_e_sintetiza_impressoes(): void
    {
        $fakeAudioJson = json_encode([
            'impressoes_entrevistador' => 'A candidata demonstrou grande entusiasmo e alinhamento com a abordagem sociointeracionista da escola. Pontual, ótima oratória e facilidade de lidar com conflitos em sala de aula.',
            'audio_transcricao_resumo' => 'Entrevistador entrevistou Camila Rocha para vaga de Educação Infantil. Informou que ela mora no bairro Savassi, trabalha atualmente no Colégio Santa Doroteia e tem pretensão de R$ 4.200,00.',
            'parecer_sugerido' => 'aprovado',
            'dados_cadastrais_extraidos' => [
                'nome_completo' => 'Camila Rocha',
                'idade' => 29,
                'data_nascimento' => '1995-03-10',
                'cargo_pretendido' => 'Professora de Educação Infantil',
                'escola_atual' => 'Colégio Santa Doroteia',
                'local_moradia' => 'Savassi, Belo Horizonte - MG',
                'pretensao_salarial' => 4200.00,
                'formacao_academica' => 'Pedagogia com especialização em Psicomotricidade',
                'experiencias_profissionais' => '4 anos no Maternal e Jardim II',
                'habilidades' => 'Contação de histórias, musicalização infantil',
            ],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $fakeAudioJson],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $caminhoAudioTemp = tempnam(sys_get_temp_dir(), 'audio_whatsapp_').'.ogg';
        file_put_contents($caminhoAudioTemp, 'Fake Ogg Opus Audio Binary');

        try {
            /** @var CurriculoIaService $service */
            $service = app(CurriculoIaService::class);
            $dados = $service->analisarAudioEntrevista($caminhoAudioTemp, 'audio/ogg', []);

            $this->assertStringContainsString('sociointeracionista', $dados['impressoes_entrevistador']);
            $this->assertEquals('aprovado', $dados['parecer_sugerido']);
            $this->assertEquals('Camila Rocha', $dados['dados_cadastrais_extraidos']['nome_completo']);
            $this->assertEquals(4200.00, $dados['dados_cadastrais_extraidos']['pretensao_salarial']);
            $this->assertEquals('Colégio Santa Doroteia', $dados['dados_cadastrais_extraidos']['escola_atual']);
            $this->assertEquals('Savassi, Belo Horizonte - MG', $dados['dados_cadastrais_extraidos']['local_moradia']);
        } finally {
            @unlink($caminhoAudioTemp);
        }
    }

    public function test_usuario_com_permissao_acessa_listagem_de_candidatos(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        Candidato::factory()->create([
            'nome_completo' => 'Fernando Lima',
            'cargo_pretendido' => 'Monitor Escolar',
        ]);

        $response = $this->actingAs($user)->get('/admin/candidatos');

        $response->assertStatus(200);
        $response->assertSee('Fernando Lima');
    }

    public function test_usuario_sem_permissao_nao_acessa_candidatos(): void
    {
        $user = User::factory()->create(); // Sem role super_admin ou permissões

        $response = $this->actingAs($user)->get('/admin/candidatos');

        $response->assertStatus(403);
    }
}
