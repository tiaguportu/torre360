<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
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
}
