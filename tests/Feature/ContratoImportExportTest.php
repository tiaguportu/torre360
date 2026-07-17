<?php

namespace Tests\Feature;

use App\Filament\Imports\ContratoImporter;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class ContratoImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Auxiliar para instanciar o ContratoImporter com os dados informados.
     */
    private function createImporterInstance(array $data): ContratoImporter
    {
        $import = new Import;
        $importer = new ContratoImporter($import, [], []);

        // Define a propriedade protegida 'data' para simular a linha do arquivo importado
        $reflection = new ReflectionClass(ContratoImporter::class);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);
        $property->setValue($importer, $data);

        return $importer;
    }

    public function test_deve_retornar_novo_contrato_se_nao_passar_id(): void
    {
        $importer = $this->createImporterInstance([
            'valor_total' => 5000.00,
        ]);

        $contrato = $importer->resolveRecord();

        $this->assertInstanceOf(Contrato::class, $contrato);
        $this->assertFalse($contrato->exists);
    }

    public function test_deve_retornar_contrato_existente_se_passar_id_cadastrado(): void
    {
        $aluno = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $contratoExistente = Contrato::create([
            'valor_total' => 10000.00,
            'matricula_id' => $matricula->id,
        ]);

        $importer = $this->createImporterInstance([
            'id' => $contratoExistente->id,
            'valor_total' => 12000.00,
        ]);

        $contrato = $importer->resolveRecord();

        $this->assertInstanceOf(Contrato::class, $contrato);
        $this->assertTrue($contrato->exists);
        $this->assertEquals($contratoExistente->id, $contrato->id);
    }

    public function test_deve_associar_matricula_por_nome_do_aluno_caso_id_esteja_vazio(): void
    {
        $aluno = Pessoa::factory()->create([
            'nome' => 'José Carlos de Alencar',
        ]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
        ]);

        $importer = $this->createImporterInstance([
            'matricula_aluno_nome' => 'José Carlos de Alencar',
            'valor_total' => 8000.00,
        ]);

        $contrato = $importer->resolveRecord();

        $this->assertEquals($matricula->id, $contrato->matricula_id);
    }

    public function test_deve_associar_template_por_nome_caso_id_esteja_vazio(): void
    {
        $template = TemplateContrato::create([
            'nome' => 'Contrato Fundamental Anos Finais',
            'conteudo' => '<p>Conteúdo de teste</p>',
        ]);

        $importer = $this->createImporterInstance([
            'template_contrato_nome' => 'Contrato Fundamental Anos Finais',
            'valor_total' => 9000.00,
        ]);

        $contrato = $importer->resolveRecord();

        $this->assertEquals($template->id, $contrato->template_contrato_id);
    }
}
