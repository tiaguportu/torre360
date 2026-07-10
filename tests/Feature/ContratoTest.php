<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\TipoVinculo;
use App\Services\ContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
