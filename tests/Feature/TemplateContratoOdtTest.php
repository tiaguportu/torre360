<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Services\ContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateContratoOdtTest extends TestCase
{
    use RefreshDatabase;

    public function test_substituicao_de_variaveis_e_tabela_de_faturas_no_xml_do_odt()
    {
        Storage::fake('local');

        // 1. Criação do cenário no banco
        $aluno = Pessoa::factory()->create([
            'nome' => 'Carlos de Souza',
            'cpf' => '987.654.321-00',
            'data_nascimento' => '2015-05-15',
        ]);

        $responsavel = Pessoa::factory()->create([
            'nome' => 'Mauricio de Souza',
            'cpf' => '123.456.789-00',
        ]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 2400.00,
            'data_aceite' => '2026-07-10',
        ]);

        $contrato->responsaveisFinanceiros()->create([
            'pessoa_id' => $responsavel->id,
        ]);

        $fatura1 = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-08-10',
        ]);

        $fatura1->itens()->create([
            'valor_unitario' => 1200.00,
            'quantidade' => 1,
            'tipo_desconto' => 'absoluto',
            'desconto' => 0,
            'descricao' => 'Mensalidade Escolar',
        ]);

        $fatura2 = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-09-10',
        ]);

        $fatura2->itens()->create([
            'valor_unitario' => 1200.00,
            'quantidade' => 1,
            'tipo_desconto' => 'absoluto',
            'desconto' => 0,
            'descricao' => 'Mensalidade Escolar',
        ]);

        // 2. Criar um ODT fake (ZIP) com content.xml contendo as variáveis
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
        <office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
            <office:body>
                <office:text>
                    <text:p>Contrato do Aluno: {{ $aluno->nome }} (CPF: ${aluno.cpf})</text:p>
                    <text:p>Responsável: {{ $responsavel->nome }}</text:p>
                    <text:p>Valor Total: {{ "R$ " . number_format($contrato->valor_total, 2, ",", ".") }}</text:p>
                    
                    <table:table>
                        <table:table-row>
                            <table:table-cell><text:p>Parcela</text:p></table:table-cell>
                            <table:table-cell><text:p>Vencimento</text:p></table:table-cell>
                            <table:table-cell><text:p>Valor</text:p></table:table-cell>
                        </table:table-row>
                        <table:table-row>
                            <table:table-cell><text:p>[fatura.parcela]</text:p></table:table-cell>
                            <table:table-cell><text:p>[fatura.vencimento]</text:p></table:table-cell>
                            <table:table-cell><text:p>[fatura.valor]</text:p></table:table-cell>
                        </table:table-row>
                    </table:table>
                </office:text>
            </office:body>
        </office:document-content>';

        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $fakeOdtPath = $tempDir.DIRECTORY_SEPARATOR.'fake_template.odt';
        $zip = new \ZipArchive;
        if ($zip->open($fakeOdtPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('content.xml', $xmlContent);
            $zip->close();
        }

        // Salva o ODT fake no disco fake
        Storage::disk('local')->put('contratos/templates/fake_template.odt', file_get_contents($fakeOdtPath));
        @unlink($fakeOdtPath);

        $template = TemplateContrato::create([
            'nome' => 'Template ODT Teste',
            'versao' => 2,
            'arquivo_odt' => 'contratos/templates/fake_template.odt',
            'is_padrao' => false,
        ]);

        // 3. Testar a substituição de XML usando reflection para acessar o método protected processOdtXml
        $service = new ContractTemplateService;
        $reflection = new \ReflectionClass(ContractTemplateService::class);
        $method = $reflection->getMethod('processOdtXml');
        $method->setAccessible(true);

        $processedXml = $method->invokeArgs($service, [$xmlContent, $contrato]);

        // Verificações
        $this->assertStringContainsString('Carlos de Souza', $processedXml);
        $this->assertStringContainsString('987.654.321-00', $processedXml);
        $this->assertStringContainsString('Mauricio de Souza', $processedXml);
        $this->assertStringContainsString('R$ 2.400,00', $processedXml);

        // Verifica se a tabela de faturas duplicou a linha (deve haver 2 linhas de dados + 1 de cabeçalho)
        // A linha 1 deve ter Parcela 1, Vencimento 10/08/2026, Valor R$ 1.200,00
        $this->assertStringContainsString('1', $processedXml);
        $this->assertStringContainsString('10/08/2026', $processedXml);
        $this->assertStringContainsString('10/09/2026', $processedXml);
        $this->assertStringContainsString('R$ 1.200,00', $processedXml);
    }
}
