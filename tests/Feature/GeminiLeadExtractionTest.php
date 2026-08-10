<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLeadExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extrair_lead_de_texto_usando_gemini_service_mockado(): void
    {
        config(['services.gemini.key' => 'fake-gemini-key']);

        $mockResponseJson = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'responsavel_nome' => 'Fernanda Lima',
                                    'responsavel_email' => 'fernanda@gmail.com',
                                    'responsavel_telefone' => '(11) 98888-9999',
                                    'responsavel_cpf' => '12345678901',
                                    'origem_sugerida' => 'WhatsApp',
                                    'temperatura' => 'quente',
                                    'valor_estimado' => 1800.00,
                                    'observacoes' => 'Interesse no 2º ano para o filho Gabriel.',
                                    'alunos' => [
                                        [
                                            'nome' => 'Gabriel Lima',
                                            'data_nascimento' => '2017-04-10',
                                            'serie_pretendida' => '2º Ano',
                                            'vinculo' => 'Mãe',
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($mockResponseJson, 200),
        ]);

        $service = app(GeminiAgentService::class);
        $resultado = $service->extrairLeadDeTexto('Olá, sou a Fernanda Lima, email fernanda@gmail.com...');

        $this->assertEquals('Fernanda Lima', $resultado['responsavel_nome']);
        $this->assertEquals('fernanda@gmail.com', $resultado['responsavel_email']);
        $this->assertEquals('quente', $resultado['temperatura']);
        $this->assertCount(1, $resultado['alunos']);
        $this->assertEquals('Gabriel Lima', $resultado['alunos'][0]['nome']);
    }

    public function test_salvar_lead_extraido_cria_pessoa_interessado_e_dependentes(): void
    {
        $consultor = User::factory()->create();
        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => $unidade->id,
        ]);
        $serie = Serie::create([
            'nome' => '2º Ano Ensino Fundamental',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
        StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1]);

        $extractedData = [
            'responsavel_nome' => 'Roberto Alves',
            'responsavel_email' => 'roberto@empresa.com',
            'responsavel_telefone' => '(21) 97777-6666',
            'responsavel_cpf' => '98765432100',
            'origem_sugerida' => 'Instagram',
            'temperatura' => 'quente',
            'valor_estimado' => 2200.50,
            'observacoes' => 'Procura vaga urgente para transferência.',
            'alunos' => [
                [
                    'nome' => 'Matheus Alves',
                    'data_nascimento' => '2016-08-20',
                    'serie_pretendida' => '2º Ano Ensino Fundamental',
                    'vinculo' => 'Pai',
                ],
            ],
        ];

        $interessado = ImportarLeadIaAction::salvarLeadExtraido($extractedData, $consultor->id);

        $this->assertDatabaseHas('pessoa', [
            'nome' => 'Roberto Alves',
            'email' => 'roberto@empresa.com',
            'telefone' => '(21) 97777-6666',
        ]);

        $this->assertDatabaseHas('interessado', [
            'id' => $interessado->id,
            'usuario_id' => $consultor->id,
            'temperatura' => 'quente',
            'valor_estimado' => 2200.50,
        ]);

        $this->assertDatabaseHas('interessado_dependente', [
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Matheus Alves',
            'serie_id' => $serie->id,
            'vinculo' => 'Pai',
        ]);
    }

    public function test_salvar_lead_extraido_reutiliza_pessoa_existente_por_email(): void
    {
        $consultor = User::factory()->create();
        $pessoaExistente = Pessoa::factory()->create([
            'nome' => 'Pessoa Já Existente',
            'email' => 'existente@email.com',
        ]);
        StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1]);

        $extractedData = [
            'responsavel_nome' => 'Outro Nome',
            'responsavel_email' => 'existente@email.com',
            'responsavel_telefone' => '(11) 91111-2222',
            'origem_sugerida' => 'Site',
            'temperatura' => 'morno',
            'valor_estimado' => 1500.00,
            'observacoes' => 'Lead repetido',
            'alunos' => [],
        ];

        $interessado = ImportarLeadIaAction::salvarLeadExtraido($extractedData, $consultor->id);

        $this->assertEquals($pessoaExistente->id, $interessado->pessoa_id);
        $this->assertEquals(1, Interessado::where('pessoa_id', $pessoaExistente->id)->count());
    }
}
