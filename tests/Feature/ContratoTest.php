<?php

namespace Tests\Feature;

use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\InstituicaoEnsino;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\ContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContratoTest extends TestCase
{
    use RefreshDatabase;

    public function test_contrato_pode_ser_associado_a_uma_unica_matricula(): void
    {
        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        $this->assertNotNull($contrato->matricula);
        $this->assertEquals($matricula->id, $contrato->matricula->id);
        $this->assertEquals('Joãozinho da Silva', $contrato->matricula->pessoa->nome);
    }

    public function test_template_contrato_suporta_variavel_aluno_no_blade(): void
    {
        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        $service = new ContractTemplateService;
        $htmlOriginal = 'Nome do aluno: {{ $aluno->nome }} - CPF: {{ $aluno->cpf }}';
        $htmlResult = $service->process($contrato, $htmlOriginal);

        $this->assertStringContainsString('Nome do aluno: Joãozinho da Silva - CPF: 123.456.789-00', $htmlResult);
    }

    public function test_template_contrato_suporta_condicionais_e_loops_do_blade(): void
    {
        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        $service = new ContractTemplateService;

        // Template usando condicionais e loops do Blade
        $htmlOriginal = '@if($aluno->nome == "Joãozinho da Silva") Nome correto: {{ $aluno->nome }} @else Outro nome @endif';
        $htmlResult = $service->process($contrato, $htmlOriginal);

        $this->assertStringContainsString('Nome correto: Joãozinho da Silva', $htmlResult);
        $this->assertStringNotContainsString('Outro nome', $htmlResult);
    }

    public function test_assinaturas_quando_pai_e_responsavel_financeiro(): void
    {
        // Garante os tipos de vinculo no banco
        TipoVinculo::updateOrCreate(['id' => 1], ['nome' => 'Pai']);
        TipoVinculo::updateOrCreate(['id' => 2], ['nome' => 'Mãe']);

        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $pai = Pessoa::factory()->create([
            'nome' => 'José da Silva',
            'cpf' => '111.111.111-11',
        ]);

        $aluno->responsaveis()->attach($pai->id, ['tipo_vinculo_id' => 1]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        // Associa o pai como responsável financeiro
        ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $pai->id,
        ]);

        $service = new ContractTemplateService;

        // 1. Testa substituindo as variáveis dinâmicas (Blade compilado das configurações)
        $htmlTemplate = 'Pai: {{!! assinatura_pai !!}} | Mae: {{!! assinatura_mae !!}} | Resp: {{!! assinatura_responsavel_financeiro !!}}';
        $htmlResult = $service->process($contrato, $htmlTemplate);

        $this->assertStringContainsString('José da Silva - Pai e Responsável Financeiro', $htmlResult);
        $this->assertStringNotContainsString('Responsável Financeiro</', $htmlResult); // A assinatura do resp financeiro deve ficar vazia

        // 2. Testa as variáveis puras do render do Blade para garantir compatibilidade retroativa
        $htmlResultBlade = $service->process($contrato, 'Pai: {!! $assinaturaPai !!} | Resp: {!! $assinaturaResponsavelFinanceiro !!}');
        $this->assertStringContainsString('José da Silva - Pai e Responsável Financeiro', $htmlResultBlade);
        $this->assertStringNotContainsString('José da Silva - Responsável Financeiro', $htmlResultBlade);
    }

    public function test_assinaturas_quando_mae_e_responsavel_financeira(): void
    {
        // Garante os tipos de vinculo no banco
        TipoVinculo::updateOrCreate(['id' => 1], ['nome' => 'Pai']);
        TipoVinculo::updateOrCreate(['id' => 2], ['nome' => 'Mãe']);

        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $mae = Pessoa::factory()->create([
            'nome' => 'Maria da Silva',
            'cpf' => '222.222.222-22',
        ]);

        $aluno->responsaveis()->attach($mae->id, ['tipo_vinculo_id' => 2]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        // Associa a mãe como responsável financeira
        ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $mae->id,
        ]);

        $service = new ContractTemplateService;

        // 1. Testa substituindo as variáveis dinâmicas
        $htmlTemplate = 'Pai: {{!! assinatura_pai !!}} | Mae: {{!! assinatura_mae !!}} | Resp: {{!! assinatura_responsavel_financeiro !!}}';
        $htmlResult = $service->process($contrato, $htmlTemplate);

        $this->assertStringContainsString('Maria da Silva - Mãe e Responsável Financeira', $htmlResult);
        $this->assertStringNotContainsString('Responsável Financeiro</', $htmlResult); // A assinatura do resp financeiro deve ficar vazia

        // 2. Testa as variáveis puras do render do Blade
        $htmlResultBlade = $service->process($contrato, 'Mae: {!! $assinaturaMae !!} | Resp: {!! $assinaturaResponsavelFinanceiro !!}');
        $this->assertStringContainsString('Maria da Silva - Mãe e Responsável Financeira', $htmlResultBlade);
        $this->assertStringNotContainsString('Maria da Silva - Responsável Financeiro', $htmlResultBlade);
    }

    public function test_assinaturas_quando_terceiro_e_responsavel_financeiro(): void
    {
        // Garante os tipos de vinculo no banco
        TipoVinculo::updateOrCreate(['id' => 1], ['nome' => 'Pai']);
        TipoVinculo::updateOrCreate(['id' => 2], ['nome' => 'Mãe']);

        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $pai = Pessoa::factory()->create([
            'nome' => 'José da Silva',
            'cpf' => '111.111.111-11',
        ]);

        $mae = Pessoa::factory()->create([
            'nome' => 'Maria da Silva',
            'cpf' => '222.222.222-22',
        ]);

        $terceiro = Pessoa::factory()->create([
            'nome' => 'Tio Patinhas',
            'cpf' => '333.333.333-33',
        ]);

        $aluno->responsaveis()->attach($pai->id, ['tipo_vinculo_id' => 1]);
        $aluno->responsaveis()->attach($mae->id, ['tipo_vinculo_id' => 2]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        // Associa o terceiro como responsável financeiro
        ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $terceiro->id,
        ]);

        $service = new ContractTemplateService;

        // 1. Testa substituindo as variáveis dinâmicas
        $htmlTemplate = 'Pai: {{!! assinatura_pai !!}} | Mae: {{!! assinatura_mae !!}} | Resp: {{!! assinatura_responsavel_financeiro !!}}';
        $htmlResult = $service->process($contrato, $htmlTemplate);

        $this->assertStringContainsString('José da Silva - Pai', $htmlResult);
        $this->assertStringNotContainsString('José da Silva - Pai e Responsável Financeiro', $htmlResult);
        $this->assertStringContainsString('Maria da Silva - Mãe', $htmlResult);
        $this->assertStringNotContainsString('Maria da Silva - Mãe e Responsável Financeira', $htmlResult);
        $this->assertStringContainsString('Tio Patinhas - Responsável Financeiro', $htmlResult);

        // 2. Testa as variáveis puras do render do Blade
        $htmlResultBlade = $service->process($contrato, 'Pai: {!! $assinaturaPai !!} | Mae: {!! $assinaturaMae !!} | Resp: {!! $assinaturaResponsavelFinanceiro !!}');
        $this->assertStringContainsString('José da Silva - Pai', $htmlResultBlade);
        $this->assertStringContainsString('Maria da Silva - Mãe', $htmlResultBlade);
        $this->assertStringContainsString('Tio Patinhas - Responsável Financeiro', $htmlResultBlade);
    }

    public function test_assinaturas_responsaveis_consolidada(): void
    {
        // Garante os tipos de vinculo no banco
        TipoVinculo::updateOrCreate(['id' => 1], ['nome' => 'Pai']);
        TipoVinculo::updateOrCreate(['id' => 2], ['nome' => 'Mãe']);

        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $pai = Pessoa::factory()->create([
            'nome' => 'José da Silva',
            'cpf' => '111.111.111-11',
        ]);

        $mae = Pessoa::factory()->create([
            'nome' => 'Maria da Silva',
            'cpf' => '222.222.222-22',
        ]);

        $terceiro = Pessoa::factory()->create([
            'nome' => 'Tio Patinhas',
            'cpf' => '333.333.333-33',
        ]);

        $aluno->responsaveis()->attach($pai->id, ['tipo_vinculo_id' => 1]);
        $aluno->responsaveis()->attach($mae->id, ['tipo_vinculo_id' => 2]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        $service = new ContractTemplateService;

        // CENÁRIO 1: Pai é o responsável financeiro
        $rfPai = ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $pai->id,
        ]);
        $contrato->refresh();

        // Testa com a macro
        $htmlResult = $service->process($contrato, 'Assinaturas: {{!! assinaturas_responsaveis !!}}');
        $this->assertStringContainsString('José da Silva - Pai e Responsável Financeiro', $htmlResult);
        $this->assertStringContainsString('Maria da Silva - Mãe', $htmlResult);
        $this->assertStringNotContainsString('Maria da Silva - Mãe e Responsável Financeira', $htmlResult);
        $this->assertStringNotContainsString('Responsável Financeiro</', $htmlResult); // Tio Patinhas não é responsável ainda

        // Limpa responsavel financeiro
        $rfPai->delete();
        $contrato->refresh();

        // CENÁRIO 2: Mãe é a responsável financeira
        $rfMae = ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $mae->id,
        ]);
        $contrato->refresh();

        $htmlResult = $service->process($contrato, 'Assinaturas: {{!! assinaturas_responsaveis !!}}');
        $this->assertStringContainsString('José da Silva - Pai', $htmlResult);
        $this->assertStringNotContainsString('José da Silva - Pai e Responsável Financeiro', $htmlResult);
        $this->assertStringContainsString('Maria da Silva - Mãe e Responsável Financeira', $htmlResult);
        $this->assertStringNotContainsString('Responsável Financeiro</', $htmlResult);

        $rfMae->delete();
        $contrato->refresh();

        // CENÁRIO 3: Terceiro é o responsável financeiro
        ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $terceiro->id,
        ]);
        $contrato->refresh();

        $htmlResult = $service->process($contrato, 'Assinaturas: {{!! assinaturas_responsaveis !!}}');
        $this->assertStringContainsString('José da Silva - Pai', $htmlResult);
        $this->assertStringNotContainsString('José da Silva - Pai e Responsável Financeiro', $htmlResult);
        $this->assertStringContainsString('Maria da Silva - Mãe', $htmlResult);
        $this->assertStringNotContainsString('Maria da Silva - Mãe e Responsável Financeira', $htmlResult);
        $this->assertStringContainsString('Tio Patinhas - Responsável Financeiro', $htmlResult);
    }

    public function test_assinatura_responsavel_legal_unidade(): void
    {
        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
        ]);

        // Cria a instituição
        $instituicao = InstituicaoEnsino::create([
            'nome' => 'Escola Teste',
            'flag_ativo' => true,
        ]);

        // Cria a unidade e o representante legal
        $unidade = Unidade::create([
            'nome' => 'Unidade Teste',
            'instituicao_ensino_id' => $instituicao->id,
            'flag_ativo' => true,
        ]);

        $rep = Pessoa::factory()->create([
            'nome' => 'Diretor Presidente',
            'cpf' => '999.999.999-99',
        ]);

        $unidade->representantesLegais()->attach($rep->id, ['cargo' => 'Diretor']);

        // Configura curso -> serie -> turma vinculada a unidade
        $curso = Curso::create([
            'nome_interno' => 'Curso Teste',
            'nome_externo' => 'Curso Teste',
            'unidade_id' => $unidade->id,
        ]);

        $serie = Serie::create([
            'nome' => 'Serie Teste',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Parecer',
        ]);

        $turno = Turno::create([
            'nome' => 'Tarde',
            'hora_inicio' => '13:00:00',
            'hora_fim' => '18:00:00',
        ]);

        $turma = Turma::create([
            'nome' => 'Turma Teste',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'tipo_avaliacao' => 'notas',
        ]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
        ]);

        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        $service = new ContractTemplateService;

        // 1. Testa com a macro
        $htmlResult = $service->process($contrato, 'Assinatura Unidade: {{!! assinatura_responsavel_legal_unidade !!}}');
        $this->assertStringContainsString('Diretor Presidente - Diretor', $htmlResult);

        // 2. Testa com a variável Blade
        $htmlResultBlade = $service->process($contrato, 'Assinatura Unidade: {!! $assinaturaResponsavelLegalUnidade !!}');
        $this->assertStringContainsString('Diretor Presidente - Diretor', $htmlResultBlade);
    }

    public function test_action_gerar_contrato_na_matricula(): void
    {
        $adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $adminUser->assignRole($role);
        session(['active_role' => 'super_admin']);

        $this->actingAs($adminUser);

        // Garante o tipo de vinculo 'Pai' no banco
        TipoVinculo::updateOrCreate(['id' => 1], ['nome' => 'Pai']);

        $aluno = Pessoa::factory()->create([
            'nome' => 'Joãozinho da Silva',
            'data_nascimento' => '2015-05-15',
            'cpf' => '123.456.789-00',
        ]);

        $responsavel = Pessoa::factory()->create([
            'nome' => 'José da Silva',
            'cpf' => '111.111.111-11',
        ]);

        $aluno->responsaveis()->attach($responsavel->id, ['tipo_vinculo_id' => 1]); // 1 = Pai

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        // Simula a action de tabela "gerarContrato"
        Livewire::test(ListMatriculas::class)
            ->callTableAction('gerarContrato', $matricula)
            ->assertHasNoTableActionErrors();

        // Verifica que o contrato foi criado corretamente no BD
        $this->assertDatabaseHas('contrato', [
            'matricula_id' => $matricula->id,
            'valor_total' => 0.00,
        ]);

        // Verifica que o responsável financeiro foi criado
        $contrato = Contrato::where('matricula_id', $matricula->id)->first();
        $this->assertNotNull($contrato);
        $this->assertDatabaseHas('responsavel_financeiro', [
            'contrato_id' => $contrato->id,
            'pessoa_id' => $responsavel->id,
            'percentual' => 100.00,
        ]);
    }

    public function test_total_paginas_e_calculado_corretamente_no_pdf(): void
    {
        $aluno = Pessoa::factory()->create(['nome' => 'Joãozinho']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $contrato = Contrato::create([
            'valor_total' => 12000.00,
            'matricula_id' => $matricula->id,
        ]);

        $service = new ContractTemplateService;

        $viewData = [
            'contrato' => $contrato,
            'matricula' => $matricula,
            'aluno' => $aluno,
            'responsavel' => null,
            'responsaveisFinanceiros' => collect(),
            'serie' => null,
            'curso' => null,
            'periodoLetivo' => null,
            'conteudo_template' => 'Este contrato contém {TOTAL_PAGINAS} páginas no total. E também {PAGE_COUNT}.',
            'cabecalho_template' => null,
            'rodape_template' => null,
        ];

        // Processa as variáveis e macros
        $viewData['conteudo_template'] = $service->process($contrato, $viewData['conteudo_template']);

        // Verifica que o process substituiu a chave pelo placeholder %%TOTAL_PAGINAS%%
        $this->assertStringContainsString('%%TOTAL_PAGINAS%%', $viewData['conteudo_template']);
        $this->assertStringNotContainsString('{TOTAL_PAGINAS}', $viewData['conteudo_template']);

        // Executa a geração do PDF (duas passagens)
        $pdf = $service->generatePdf($viewData);

        // O canvas do DomPDF deve ter exatamente 1 página
        $this->assertEquals(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_assinafy_service_processa_template_sem_erro_de_variavel_indefinida(): void
    {
        Http::fake([
            '*/accounts/*/documents' => Http::response(['data' => []], 200),
            '*/documents' => Http::response(['id' => 'doc_123', 'data' => ['id' => 'doc_123']], 200),
            '*/accounts/*/signers' => Http::response(['data' => [['id' => 'sig_123', 'email' => 'pai@example.com']]], 200),
            '*/documents/*/assignments' => Http::response([
                'signing_urls' => [
                    ['signer_id' => 'sig_123', 'url' => 'https://sandbox.assinafy.com.br/sign/123'],
                ],
            ], 200),
        ]);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Pai Teste', 'email' => 'pai@example.com']);
        $responsavel->users()->create([
            'name' => 'Pai Teste',
            'email' => 'pai@example.com',
            'password' => bcrypt('password'),
        ]);

        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $contrato = Contrato::create([
            'valor_total' => 5000.00,
            'matricula_id' => $matricula->id,
        ]);

        $contrato->responsaveisFinanceiros()->create([
            'pessoa_id' => $responsavel->id,
            'percentual' => 100,
        ]);

        $assinafyService = new AssinafyService;
        $result = $assinafyService->enviarContrato($contrato);

        $this->assertTrue($result['success']);
        $this->assertEquals('https://sandbox.assinafy.com.br/sign/123', $result['redirect_url']);
    }

    public function test_rota_post_gerar_assinatura_funciona_sem_erro_de_template(): void
    {
        Http::fake([
            '*/accounts/*/documents' => Http::response(['data' => []], 200),
            '*/documents' => Http::response(['id' => 'doc_123', 'data' => ['id' => 'doc_123']], 200),
            '*/accounts/*/signers' => Http::response(['data' => [['id' => 'sig_123', 'email' => 'pai@example.com']]], 200),
            '*/documents/*/assignments' => Http::response([
                'signing_urls' => [
                    ['signer_id' => 'sig_123', 'url' => 'https://sandbox.assinafy.com.br/sign/123'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Teste']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Pai Teste', 'email' => 'pai@example.com']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $contrato = Contrato::create([
            'valor_total' => 5000.00,
            'matricula_id' => $matricula->id,
        ]);
        $contrato->responsaveisFinanceiros()->create([
            'pessoa_id' => $responsavel->id,
            'percentual' => 100,
        ]);

        $response = $this->actingAs($user)
            ->post(route('contratos.gerar-assinatura', $contrato));

        $response->assertRedirect('https://sandbox.assinafy.com.br/sign/123');
    }
}
