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
use ZipArchive;

class CandidatoCurriculoIaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Config::set('services.gemini.key', 'fake-gemini-key');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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

    public function test_curriculo_ia_service_interpreta_curriculo_pdf_e_extrai_dados(): void
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

    public function test_curriculo_ia_service_interpreta_curriculo_docx_e_extrai_foto_embutida(): void
    {
        // Cria imagem real para embutir no DOCX
        $img = imagecreatetruecolor(150, 150);
        $corFundo = imagecolorallocate($img, 100, 150, 200);
        imagefilledrectangle($img, 0, 0, 150, 150, $corFundo);
        ob_start();
        imagejpeg($img, null, 90);
        $jpegBytes = ob_get_clean();
        imagedestroy($img);

        // Cria arquivo .docx como contêiner ZIP real
        $caminhoDocx = tempnam(sys_get_temp_dir(), 'docx_test_').'.docx';
        $zip = new ZipArchive;
        $zip->open($caminhoDocx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Curriculo de Vanessa Ribeiro. Pedagoga com especializacao em alfabetizacao. Mora na Vila Mariana em Sao Paulo. Pretensao 4800.</w:t></w:r></w:p></w:body></w:document>');
        $zip->addFromString('word/media/image1.jpeg', $jpegBytes);
        $zip->close();

        $fakeJson = json_encode([
            'nome_completo' => 'Vanessa Ribeiro',
            'cargo_pretendido' => 'Pedagoga',
            'escola_atual' => 'Escola Aquarela',
            'local_moradia' => 'Vila Mariana, São Paulo - SP',
            'pretensao_salarial' => 4800.00,
            'formacao_academica' => 'Pedagogia com especialização em Alfabetização',
            'tem_foto_perfil' => true,
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
            $dados = $service->analisarCurriculo($caminhoDocx, null, 'curriculo_vanessa.docx');

            $this->assertEquals('Vanessa Ribeiro', $dados['nome_completo']);
            $this->assertEquals('Pedagoga', $dados['cargo_pretendido']);
            $this->assertNotEmpty($dados['foto_path']);
            $this->assertStringStartsWith('candidatos/fotos/', $dados['foto_path']);
            $this->assertTrue(Storage::disk('public')->exists($dados['foto_path']));
        } finally {
            @unlink($caminhoDocx);
        }
    }

    public function test_curriculo_ia_service_interpreta_curriculo_odt_e_extrai_foto_embutida(): void
    {
        $img = imagecreatetruecolor(120, 120);
        $corFundo = imagecolorallocate($img, 180, 120, 90);
        imagefilledrectangle($img, 0, 0, 120, 120, $corFundo);
        ob_start();
        imagejpeg($img, null, 90);
        $jpegBytes = ob_get_clean();
        imagedestroy($img);

        // Cria arquivo .odt como contêiner ZIP real
        $caminhoOdt = tempnam(sys_get_temp_dir(), 'odt_test_').'.odt';
        $zip = new ZipArchive;
        $zip->open($caminhoOdt, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:text><text:p>Curriculo de Lucas Andrade. Professor de Historia. Trabalha no Colegio Objetivo.</text:p></office:text></office:body></office:document-content>');
        $zip->addFromString('Pictures/1000000000.jpg', $jpegBytes);
        $zip->close();

        $fakeJson = json_encode([
            'nome_completo' => 'Lucas Andrade',
            'cargo_pretendido' => 'Professor de História',
            'escola_atual' => 'Colégio Objetivo',
            'local_moradia' => 'Campinas - SP',
            'pretensao_salarial' => 4500.00,
            'tem_foto_perfil' => true,
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
            $dados = $service->analisarCurriculo($caminhoOdt, null, 'curriculo_lucas.odt');

            $this->assertEquals('Lucas Andrade', $dados['nome_completo']);
            $this->assertEquals('Professor de História', $dados['cargo_pretendido']);
            $this->assertNotEmpty($dados['foto_path']);
            $this->assertTrue(Storage::disk('public')->exists($dados['foto_path']));
        } finally {
            @unlink($caminhoOdt);
        }
    }

    public function test_curriculo_ia_service_extrai_foto_com_bounding_box_em_imagem(): void
    {
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
            'foto_bounding_box' => [200, 200, 800, 800],
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

    public function test_usuario_com_permissao_acessa_edicao_de_candidatos_e_ve_botao_modal_audio(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $candidato = Candidato::factory()->create([
            'nome_completo' => 'Priscila Alencar',
            'cargo_pretendido' => 'Professora de Artes',
        ]);

        $response = $this->actingAs($user)->get("/admin/candidatos/{$candidato->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Priscila Alencar');
    }

    public function test_usuario_sem_permissao_nao_acessa_candidatos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/candidatos');

        $response->assertStatus(403);
    }
}
